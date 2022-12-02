<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\PublicHashHandlers;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class File extends AbstractPublicHashHandler
{

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cachePool;

    /**
     * @var FileOperations
     */
    private $fileOperations;

    public function __construct(DataController $dataController, ConfigRegistry $config, FileOperations $fileOperations, CacheItemPoolInterface $cachePool)
    {
        $this->config = $config;
        $this->cachePool = $cachePool;
        $this->fileOperations = $fileOperations;
        parent::__construct($dataController);
    }

    public function allow(ServerRequestInterface $request, string $resourceId): bool
    {

        // On this method, we just extract the fspath provided on the request, and compare it with the one
        // found on the public hash record ($resourceId)

        // split the route
        $route = $this->splitRoute($request);

        $path = null;

        // file routes (anything that starts with `file.`)
        if (preg_match('/^file\./', $route)) {
            // grab params from the request
            $params = $request->getQueryParams() ?? [];
            $path = $params['fspath'] ?? null;
        }

        // wiki routes
        if (preg_match('/^wiki\.[^\/]+(\/.+)/', $route, $matches)) {
            $path = preg_replace('/^\/{2,}/', '/', $matches[1]);
        }

        // Subpaths - for some routes we allow permission to any sub-path of the public link path, for the others,
        // we only allow if the path and resource id exactly matches
        $routesAllowedForSubPaths = [
            'file.thumb',
            'file.download',
            'file.list',
        ];

        if (in_array($route, $routesAllowedForSubPaths)) {
            if (strpos($path, $resourceId) === 0) {
                return true;
            }
        }

        // wiki references - wiki files and images referenced by a shared wiki page, must be authorized
        // with the same hash that authorizes the parent page.
        $routesAllowedForWikiReferences = [
            'wiki.get/',
            'file.download'
        ];
        $referenceRoute = array_reduce($routesAllowedForWikiReferences, function($carry, $route) {
            return strpos($route, 'wiki.get/') === 0 || $carry;
        }, false);

        if ($referenceRoute && $path !== $resourceId) {
            try {
                return $this->verifyClaimedWikiParent($path, $resourceId);
            } catch (Throwable $e) {
                return false;
            }
        }

        // we use hash equals to avoid time attacks
        return hash_equals($path, $resourceId);

    }

    /**
     * Request claims that $path is referenced by $claimedParent. At this case we allow
     * access to $path using the parent public hash.
     *
     * This function verifies if the parent really references the requested path.
     *
     * @param string $requestedPath
     * @param string $claimedParent
     * @return bool
     * @throws BadResourceTranslationException
     * @throws DatabaseException
     * @throws UnknownResourceException
     * @throws InvalidArgumentException
     */
    protected function verifyClaimedWikiParent(string $requestedPath, string $claimedParent): bool
    {
        // first of all, path and parent must be on the same wiki root, otherwise, we halt
        // we get this opportunity to split the relative path of the requested path (relative to the wiki root)
        if (!preg_match('/^(\/wf\/[0-9]+\/)(.*)/', $requestedPath, $matches)) {
            return false;
        }
        $wikiRoot = $matches[1];
        $relativePath = $matches[2];

        // claimed parent must be on the same wiki root
        if (strpos($claimedParent, $wikiRoot) !== 0) {
            return false;
        }

        // claimed parent must be an md file
        if (!preg_match('/\.md$/', $claimedParent)) {
            return false;
        }

        // we store the wiki references on cache for a short amount of time.
        // we do that because one shared wiki page can reference multiple images/pages
        // and to render it, we may need to calculate this permission multiple times
        $cacheKey = 'wiki_references_' . base64_encode($claimedParent);
        $cacheItem = $this->cachePool->getItem($cacheKey);
        if (!$cacheItem->isHit()) {

            // just get the file contents (we don't pass a user, because the content will never be returned)
            try {
                $downloadResource = $this->fileOperations->download($claimedParent, null, null, 'local');
            } catch (FSAuthorizationException | FSOpException $e) {
                return false;
            }

            $tmpFile = $downloadResource->getPayload()['tmpfile'] ?? null;
            if ($tmpFile === null) {
                return false;
            }
            $data = file_get_contents($tmpFile);
            unlink($tmpFile);

            // find all references on the parent file...
            preg_match_all('/\[[^]]+\]\(([^)]+)\)/', $data, $matches);

            // save the references to the cache (just for 1 minute)
            $cacheItem->set($matches[1])->expiresAfter(60);

            $this->cachePool->save($cacheItem);

        }

        // if the relative path is reference by the parent, return true
        return in_array($relativePath, $cacheItem->get());

    }
}