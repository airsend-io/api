<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Service\Cache\CacheService;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class UrlManager
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * BackgroundDispatcher constructor.
     * @param LoggerInterface $logger
     * @param DataController $dataController
     * @param ServiceRegistryInterface $config
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(LoggerInterface $logger, DataController $dataController, ServiceRegistryInterface $config, CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $urlArgs
     * @return Response
     * @throws InvalidArgumentException
     */
    public function shorten(Request $request, Response $response, array $urlArgs): Response
    {

        $urlHash = $urlArgs['hash'];

        $uiBaseDomain = $this->config->get('/app/ui/baseurl');

        $this->logger->debug("URL shortener: Translating short URL hash: `$urlHash`");

        // first check the cache
        $cacheKey = "airsend.shorturl.$urlHash";
        $cacheItem = $this->cache->getItem($cacheKey);
        if (!$cacheItem->isHit()) {

            // not in cache, try database
            $url = $this->dataController->translateShortUrl($urlHash);

            // not found on database, redirect to 404
            if ($url === null) {

                $this->logger->debug("URL shortener: hash `$urlHash` not found. Redirecting to 404");
                return $response->withStatus(307)->withHeader('Location', "$uiBaseDomain/404");
            }

            // found on database...
            $this->logger->debug("URL shortener: hash `$urlHash` found on the database: `$url`");

            // prefix with baseuri if it's not an absolute URL
            if (!preg_match('#^https?://#', $url)) {

                $this->logger->debug("URL shortener: `$url` is not an absolute URL. Prefixing with `$uiBaseDomain`");
                $url = $uiBaseDomain . $url;

            }

            // save the cache
            $cacheItem->set($url);
            $cacheItem->expiresAfter(60 * 60 * 24 * 7); // one week ttl
            $this->cache->save($cacheItem);

        } else {
            $this->logger->debug("URL shortener: hash `$urlHash` found on cache.");
        }

        $url = $cacheItem->get();

        $this->logger->debug("URL shortener: hash `$urlHash` translated to `$url`");

        return $response->withStatus(307)->withHeader('Location', $url);

    }

}

