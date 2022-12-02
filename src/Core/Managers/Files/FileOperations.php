<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Files;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\SearchDataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSNotFoundException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\FSOpServerException;
use CodeLathe\Core\Exception\MemoizingException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnconfiguredPolicyException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UnknownUserException;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Exception\UnsupportedPolicyTypeException;
use CodeLathe\Core\Indexers\FileIndexer;
use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\FSAddFileEvent;
use CodeLathe\Core\Messaging\Events\FSCopyEvent;
use CodeLathe\Core\Messaging\Events\FSCreateFolderEvent;
use CodeLathe\Core\Messaging\Events\FSDeleteEvent;
use CodeLathe\Core\Messaging\Events\FSEvent;
use CodeLathe\Core\Messaging\Events\FSUpdateEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelFileResource;
use CodeLathe\Core\Objects\ChannelPath;
use CodeLathe\Core\Objects\ChannelWikiResource;
use CodeLathe\Core\Objects\DownloadResource;
use CodeLathe\Core\Objects\File;
use CodeLathe\Core\Objects\FileResource;
use CodeLathe\Core\Objects\FileSystemObject;
use CodeLathe\Core\Objects\Folder;
use CodeLathe\Core\Objects\FolderProps;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Objects\Resource;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Policy\Policies\StorageQuota;
use CodeLathe\Core\Policy\PolicyManager;
use CodeLathe\Core\Policy\PolicyTypes;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\Image;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\Memoizer;
use CodeLathe\Core\Utility\MimeType;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FolderInterface;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use CodeLathe\Service\Storage\Exceptions\DestinyPathAlreadyExistsException;
use CodeLathe\Service\Storage\Exceptions\InvalidPathException;
use CodeLathe\Service\Storage\Exceptions\NotAFileException;
use CodeLathe\Service\Storage\Exceptions\NotAFolderException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use CodeLathe\Service\Storage\Implementation\Objects\FolderProperties;
use CodeLathe\Service\Storage\Shared\StorageObject;
use Elasticsearch\Client as ElasticSearchClient;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use ZipStream\ZipStream;


/**
 * Class ASFileSystem
 *
 * Wraps all the file system related functions for AirSend
 * All application code should use this to work with FileSystem
 *
 * @package CodeLathe\Core\Files
 */
class FileOperations
{
    protected $logger;
    protected $dc;

    /**
     * @var FileController
     * @deprecated
     */
    protected $fc;

    protected $eventManager;
    protected $policyManager;
    protected $csec;
    protected $globalAuthContext;
    protected $config;
    public $errorMessage;

    CONST ROOTS_TYPE_SELF            = 1;
    CONST ROOTS_TYPE_USER_LIST       = 2;
    CONST ROOTS_TYPE_CHANNEL_LIST   = 3;

    const THUMB_DIMENSIONS_WHITELIST = [
        [100, 100],
        [1024, 1024],
        [120, 120],
        [1280, 1280],
        [135, 135],
        [1600, 1280],
        [160, 160],
        [200, 200],
        [240, 180],
        [2560, 1440],
        [300, 300],
        [30, 30],
        [400, 400],
        [40, 40],
        [480, 360],
        [500, 500],
        [60, 60],
        [720, 540],
        [80, 80],
        [90, 90],
    ];

    const MEDIA_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];

    /**
     * @var ElasticSearchClient
     */
    protected $elasticClient;

    /**
     * @var FileIndexer
     */
    protected $fileIndexer;

    /**
     * @var SearchDataController
     */
    protected $searchDataController;
    /**
     * @var CacheItemPoolInterface
     */
    protected $cachePool;
    /**
     * @var StorageServiceInterface
     */
    protected $storageService;

    public function __construct(LoggerInterface $logger,
                                DataController $dc,
                                FileController $fc,
                                EventManager $eventManager,
                                PolicyManager $policyManager,
                                CriticalSection $csec,
                                ConfigRegistry $config,
                                GlobalAuthContext $globalAuthContext,
                                ElasticSearchClient $elasticClient,
                                FileIndexer $fileIndexer,
                                SearchDataController $searchDataController,
                                CacheItemPoolInterface $cachePool,
                                StorageServiceInterface $storageService
    )
    {
        $this->logger = $logger;
        $this->dc = $dc;
        $this->fc = $fc;
        $this->eventManager = $eventManager;
        $this->policyManager = $policyManager;
        $this->csec = $csec;
        $this->globalAuthContext = $globalAuthContext;
        $this->config = $config;
        $this->elasticClient = $elasticClient;
        $this->fileIndexer = $fileIndexer;
        $this->searchDataController = $searchDataController;
        $this->cachePool = $cachePool;
        $this->storageService = $storageService;
    }

    protected function translatePhysicalPath(string $path): TranslatedPath
    {
        // split the path
        if (!preg_match('/^\/f\/([0-9]+)(\/.*)?/', $path, $matches)) {
            throw new FSOpException('Invalid physical path format');
        }
        $teamId = (int) $matches[1];
        $subPath = $matches[2] ?? '';

        // find the team
        $team = $this->dc->getTeamByTeamId($teamId);
        if ($team == null) {
            throw new FSOpException("Invalid physical path: Invalid team: $teamId");
        }

        // display path...
        $displayPath = $team->getTeamType() === Team::TEAM_TYPE_SELF ? '/My Files' : "/{$team->getName()} files";
        $displayPath .= $subPath;

        return new TranslatedPath($path, $displayPath, $team);
    }

    /**
     * @param string $path
     * @param int $pathType
     * @return TranslatedPath
     * @throws DatabaseException
     * @throws FSOpException
     */
    protected function translateChannelPath(string $path, int $pathType): TranslatedPath
    {
        // split the path
        if (!preg_match('/^\/[cw]f\/([0-9]+)(\/.+)?$/', $path, $matches)) {
            throw new FSOpException('Invalid channel path format');
        }
        $channelPathId = (int) $matches[1];
        $subPath = $matches[2] ?? '';

        // find channel path and check if the channel path type is correct
        $channelPath = $this->dc->getChannelPathById($channelPathId);
        if ($channelPath === null || ($channelPath->getPathType() !== $pathType)) {
            throw new FSOpException("Invalid channel path format: Invalid ChannelPath: {$channelPathId}");
        }

        // find channel
        $channel = $this->dc->getChannelById($channelPath->getChannelId());
        if ($channel === null) {
            throw new FSOpException("Invalid channel path format: Invalid Channel: {$channelPath->getChannelId()}");
        }

        // find team (every channel have a team)
        $team = $this->dc->getTeamByTeamId($channel->getTeamId());

        // display name ...
        $displayName = "/Shared Channels/{$channel->getName()}$subPath";

        $type = null;
        switch ($pathType) {
            case ChannelPath::CHANNEL_PATH_TYPE_FILE:
                $type = 'files';
                break;
            case ChannelPath::CHANNEL_PATH_TYPE_WIKI:
                $type = 'wiki';
                break;
        }
        return new TranslatedPath($channelPath->getPath() . $subPath,
            $displayName,
            $team,
            $path,
            $subPath,
            $channel,
            $type);
    }

    /**
     * @param string $path
     * @return TranslatedPath
     * @throws FSOpException
     * @throws DatabaseException
     */
    public function translatePath(string $path): TranslatedPath
    {
        if (!preg_match('/^\/([a-z]+)/', $path, $matches)) {
            throw new FSOpException('Invalid path format.');
        }

        switch ($matches[1]) {
            case 'f':
                return $this->translatePhysicalPath($path);
            case 'cf':
                return $this->translateChannelPath($path, ChannelPath::CHANNEL_PATH_TYPE_FILE);
            case 'wf':
                return $this->translateChannelPath($path, ChannelPath::CHANNEL_PATH_TYPE_WIKI);
            default:
                throw new FSOpException("Invalid path format: Invalid prefix /{$matches[1]}");
        }
    }

    /**
     * Must receive a /f like path, and translate it to translated path including the relative (channel) path
     *
     * @param ChannelPath $channelPath
     * @return TranslatedPath
     * @throws DatabaseException
     * @throws FSOpException
     */
    protected function extractPathsFromChannelPath(ChannelPath $channelPath): TranslatedPath
    {
        $path = $channelPath->getPath();
        if (!preg_match('/^\/f\/([0-9]+)(\/.*)?/', $path)) {
            throw new FSOpException("Invalid path format: /f$path");
        }
        if (preg_match('/^\/f(\/([0-9]+)\/Channels\/([^\/]+)\/(files|wiki)(?:\/(.*))?)$/', $path, $matches)) {
            $physicalPath = $matches[1];
            $teamId = (int)$matches[2];
            $channelName = $matches[3];
            $root = $matches[4];
            $subPath = $matches[5] ?? '';
        } else {
            return $this->translatePhysicalPath($path);
        }

        // find the team
        $team = $this->dc->getTeamByTeamId($teamId);
        if ($team === null) {
            throw new FSOpException("Invalid team id: $teamId. Path: $path");
        }

        // define the prefix and root type (files or wiki)
        $prefix = $root === 'files' ? 'cf' : 'wf';
        $typeId = $root === 'files' ? ChannelPath::CHANNEL_PATH_TYPE_FILE : ChannelPath::CHANNEL_PATH_TYPE_WIKI;

        // find channel and relative path

        $channel = $this->dc->getChannelById($channelPath->getChannelId());
        if ($channel === null) {
            return $this->translatePhysicalPath($path);
        }

        $relativePath = "/$prefix/{$channelPath->getId()}";

        // display name ...
        $displayName = "/Shared Channels/{$channelName}$subPath";

        return new TranslatedPath($physicalPath,
            $displayName,
            $team,
            $relativePath,
            $subPath,
            $channel,
            $root);
    }

    /**
     * Returns information related to a specific file or folder
     *
     * @param string $path
     * @param User|null $user
     * @return bool|File|Folder
     * @throws FSAuthorizationException
     * @throws FSNotFoundException
     * @throws FSOpException
     * @throws DatabaseException
     */
    public function info(string $path, ?User $user): FileSystemObject
    {

        $translatedPath = $this->translatePath($path);

        // if the user is provided, we check permissions
        if ($user !== null && $user->cannot('read', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        // grab data from storage service
        try {
            $fsEntry = $this->storageService->info($translatedPath->getPhysicalPath());
        } catch (NotFoundException $e) {
            throw new FSNotFoundException('Invalid path');
        }

        return FileSystemObject::createFromFSEntry($fsEntry, $translatedPath->getRelativePath(), $translatedPath->getDisplayPath());

    }

    /**
     * @param string $path
     * @param User|null $loggedUser
     * @param string $searchQuery
     * @param string $sortBy Can be updated_on or name
     * @param bool $sortDescendent
     * @param string|null $cursor
     * @param int|null $limitBefore
     * @param int|null $limitAfter
     * @param bool $inDepth
     * @param bool $ignoreFolders
     * @param string|null $typeFilter
     * @return FileSystemObject[]
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSOpException
     */
    public function list(string $path,
                         ?User $loggedUser = null,
                         string $searchQuery = '',
                         string $sortBy = 'updated_on',
                         bool $sortDescendent = false,
                         ?string $cursor = null,
                         ?int $limitBefore = null,
                         ?int $limitAfter = null,
                         bool $inDepth = false,
                         bool $ignoreFolders = false,
                         ?string $typeFilter = null
    ): array
    {

        // list main fs root
        if (empty($path)) {
            return $this->listRoot($loggedUser);
        }

        // list shared channels
        if ($path === '/cf') {
            return $this->listSharedChannelsRoots($loggedUser);
        }

        /** @var TranslatedPath $translatedPath */
        $translatedPath = Memoizer::memoized([$this, 'translatePath'])($path);

        // if the user is provided, we check permissions. User must have read permissions on the path
        if ($loggedUser !== null && $loggedUser->cannot('read', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        // handle search
        $searchIds = null;
        if (!empty($searchQuery)) {
            $channel = $translatedPath->getChannel();
            $channelId = $channel instanceof Channel ? $channel->getId() : null;
            $searchResult = $this->searchDataController->searchFiles($loggedUser->getId(), $searchQuery, $channelId);
            $searchIds = array_reduce($searchResult, function ($carry, $item) {
                $carry[] = (int)$item['id'];
                return $carry;
            }, []);
        }

        // handle the cursor
        $cursorPath = null;
        if ($cursor !== null) {
            // translate the cursor
            $translatedCursor = $this->translatePath($cursor);
            $cursorPath = $translatedCursor->getPhysicalPath();
        }

        $extensionBlacklist = [];
        $extensionWhitelist = [];
        if ($typeFilter !== null) {
            if ($typeFilter === 'media') {
                $extensionWhitelist = array_map(function ($item) { return "'$item'";}, static::MEDIA_EXTENSIONS);
            } else { // docs
                $extensionBlacklist = array_map(function ($item) { return "'$item'";}, static::MEDIA_EXTENSIONS);
            }
        }

        try {
            $items = $this->storageService->list($translatedPath->getPhysicalPath(),
                $cursorPath,
                $inDepth,
                $searchIds,
                $sortBy,
                $sortDescendent,
                $limitBefore,
                $limitAfter,
                $ignoreFolders,
                $extensionWhitelist,
                $extensionBlacklist);
        } catch (NotAFolderException $e) {
            throw new FSOpException('Destiny path is not a folder');
        } catch (NotFoundException $e) {
            throw new FSOpException('Destiny path was not found');
        } catch (StorageServiceException $e) {
            throw new FSOpException("Storage error: {$e->getMessage()}");
        }

        return array_map(function ($item) use ($translatedPath) {

            $pattern = '/^' . preg_quote($translatedPath->getPhysicalPath(), '/') . '/';

            $relativePath = $translatedPath->getRelativePath();
            if ($relativePath !== null) {
                $relativePath = preg_replace($pattern, $relativePath, $item->getPath());
            }

            $displayPath = $translatedPath->getDisplayPath();
            if ($displayPath !== null) {
                $displayPath = preg_replace($pattern, $displayPath, $item->getPath());
            }

            return FileSystemObject::createFromFSEntry($item, $relativePath, $displayPath);

        }, $items);

    }

    /**
     * @param User $loggedUser
     * @return FileSystemObject[]
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSNotFoundException
     * @throws FSOpException
     */
    public function listRoot(User $loggedUser): array
    {

        // find team paths for the user
        $paths = [];
        foreach ($this->dc->getTeamUsersForUser($loggedUser->getId()) as $teamUser) {
            if ($teamUser->getUserRole() >= TeamUser::TEAM_USER_ROLE_MANAGER) {
                $paths[] = $this->info("/f/{$teamUser->getTeamId()}", $loggedUser);
            }
        }

        // include the virtual shared channels entry '/cf'
        $sharedChannelsPath = FileSystemObject::createFromData('cf',
            '',
            '',
            'folder',
            0,
            '',
            '',
            '',
            'Shared Channels',
            '/Shared Channels',
            '',
            ['SYSTEM']);

        $paths[] = $sharedChannelsPath;
        return $paths;

    }

    protected function listSharedChannelsRoots(User $loggedUser): array
    {

        $paths = [];

        // first find the teams that user is owner
        $teams = $this->dc->getTeamsForUser($loggedUser->getId(), TeamUser::TEAM_USER_ROLE_OWNER);

        // map only the team ids
        $teamIds = array_map(function(Team $team) {
            return $team->getId();
        }, $teams);

        // first find the channels that user is part of
        foreach ($this->dc->getChannelsForUser($loggedUser->getId()) as $channelRow) {

            // ignore channels that is part of a team where the user is owner
            if (in_array($channelRow['team_id'], $teamIds)) {
                continue;
            }

            // go through the paths for each channel and find the files root
            foreach ($this->dc->getChannelPathsByChannelId((int)$channelRow['id']) as $channelPathRow) {

                // for each files root, create an entry on the output
                if (((int)$channelPathRow['path_type']) === ChannelPath::CHANNEL_PATH_TYPE_FILE) {
                    $paths[] = FileSystemObject::createFromData($channelPathRow['id'],
                        "/cf",
                        '',
                        'folder',
                        0,
                        '',
                        '',
                        '',
                        $channelRow['channel_name'],
                        "/Shared Channels/{$channelRow['channel_name']}",
                        '',
                        ['SYSTEM']);
                }
            }
        }
        return $paths;
    }

    /**
     * @param string $fromPath
     * @param $toPath
     * @param User $loggedUser
     * @param bool $copy
     * @throws FSAuthorizationException
     * @throws FSOpException
     */
    public function copyOrMove(string $fromPath, $toPath, User $loggedUser, bool $copy = false)
    {
        /** @var TranslatedPath $translatedPath */
        $fromTranslatedPath = Memoizer::memoized([$this, 'translatePath'])($fromPath);

        /** @var TranslatedPath $translatedPath */
        $toTranslatedPath = Memoizer::memoized([$this, 'translatePath'])($toPath);

        // if user is provided, it needs to be able to read from the from-path, and write to the to-path
        if ($loggedUser->cannot('read', $fromTranslatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }
        if ($loggedUser->cannot('upload', $toTranslatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        // save from entry to use on event handling later
        $fromEntry = $this->storageService->info($fromTranslatedPath->getPhysicalPath());

        if ($copy) {
            $this->storageService->copy($fromTranslatedPath->getPhysicalPath(), $toTranslatedPath->getPhysicalPath());
        } else {
            try {
                $this->storageService->move($fromTranslatedPath->getPhysicalPath(), $toTranslatedPath->getPhysicalPath());
            } catch (DestinyPathAlreadyExistsException $e) {
                throw new FSOpException('Destiny path already exists');
            } catch (InvalidPathException $e) {
                throw new FSOpException('The paths are invalid');
            } catch (NotFoundException $e) {
                throw new FSOpException('The source path or destiny folder was not found');
            } catch (StorageServiceException $e) {
                throw new FSOpException("Unhandled move error: {$e->getMessage()}");
            }
        }

        // trigger update the event...
        $toEntry = $this->storageService->info($toTranslatedPath->getPhysicalPath());
        $eventClass = $copy ? FSCopyEvent::class : FSUpdateEvent::class;
        $event = new $eventClass($fromTranslatedPath,
            $toTranslatedPath,
            $fromEntry->getId(),
            $toEntry->getId(),
            $fromEntry->getName(),
            $toEntry->getName(),
            $loggedUser->getId(),
            $toEntry instanceof File ? 'file' : 'folder',
            $toEntry instanceof File ? $toEntry->getSize() : null,
            false);
        $this->eventManager->publishEvent($event);
    }

    /**
     *
     * Creates a new folder
     *
     * @param string $dstPath
     * @param string $name
     * @param User $loggedUser
     * @throws FSOpException|FSAuthorizationException
     */
    public function create(string $dstPath, string $name, User $loggedUser): void
    {

        /** @var TranslatedPath $dstTranslatedPath */
        $dstTranslatedPath = Memoizer::memoized([$this, 'translatePath'])($dstPath);

        // check permission on destiny path
        if ($loggedUser->cannot('createFolder', $dstTranslatedPath)) {
            throw new FSAuthorizationException('User is not allowed to create folders under this path');
        }

        try {
            $entryId = $this->storageService->createFolder($dstTranslatedPath->getPhysicalPath(), $name, (string)$loggedUser->getId());
        } catch (DestinyPathAlreadyExistsException $e) {
            throw new FSOpException('Folder already exists');
        } catch (NotAFolderException $e) {
            throw new FSOpException('Parent folder is not a folder');
        } catch (NotFoundException $e) {
            throw new FSOpException('Parent folder doesn\'t exist');
        }

        // trigger event
        $event = new FSCreateFolderEvent($dstTranslatedPath, $entryId, $name, $loggedUser->getId(), true);
        $this->eventManager->publishEvent($event);

    }

    private function getTeamChannelsFolderMapping(int $teamID, bool $isPersonalTeam)
    {
        if ($isPersonalTeam)


        return "/f/$teamID/Common/Channels";
    }

    public static function getDeletedItemsName()
    {
        return "deleted items";
    }

    public static function getChannelFilesName()
    {
        return "files";
    }

    public static function getChannelAttachmentsName()
    {
        return "attachments";
    }

    public static function getChannelWikiName()
    {
        return "wiki";
    }

    public static function getChannelsName()
    {
        return "Channels";
    }

    /**
     * Creates the associated folder structure when a team is created
     *
     * @param Team $team the new Team Object
     * @param User $user
     * @return bool
     * @throws DestinyPathAlreadyExistsException
     * @throws NotAFolderException
     * @throws NotFoundException
     */
    public function onNewTeam(Team $team, User $user): bool
    {
        // ... Create the new team folder for the team
        $this->logger->debug(__CLASS__.":".__FUNCTION__ . " : ".$team->getId());

        $teamID = (int)$team->getId();
        $owner = (string)$user->getId();

        // create the team root
        $this->storageService->createRoot((string)$teamID, $owner);
        $this->storageService->createFolder("/$teamID", 'Channels', $owner);
        $this->storageService->createFolder("/$teamID", FileOperations::getDeletedItemsName(), $owner);

        //$this->fc->addMetaTags($teamDeleted,  StorageObject::OBJECTTYPE_FOLDER, array('ATTR', 'DELETED:1'));

        // ... TODO: Add Sample Getting Started Files to this folder
        return true;
    }

    /**
     * Creates the associated folder structure when a channel is created
     *
     * @param Channel $channel $channelID ID for the new channel
     * @param int $userID name of the owner creating this channel
     * @param array $paths returns an array of associated Resource objects with paths
     * @return bool
     */
    public function onNewChannel(Channel $channel, int $userID, array &$paths): bool
    {

        $team = $this->dc->getTeamByTeamId((int)$channel->getTeamId());
        $teamID = (int)$team->getId();
        $this->logger->debug(__CLASS__.":".__FUNCTION__ . " : Channel: ".$channel->getName()." : TeamID: ".$teamID);

        $teamChannelsRoot = "/$teamID/Channels";


        // ... Create /mapped/channel name
        $channelName = $channel->getName();

        try {

            // create the channel root folder
            $this->storageService->createFolder($teamChannelsRoot, $channelName, (string)$userID);
            $channelRoot = "$teamChannelsRoot/$channelName";

            // create files folder
            $this->storageService->createFolder($channelRoot, FileOperations::getChannelFilesName(), (string)$userID);
            $channelFilesRoot = "$channelRoot/" . FileOperations::getChannelFilesName();

            // create attachments folder
            $this->storageService->createFolder($channelFilesRoot, FileOperations::getChannelAttachmentsName(), (string)$userID);

            // create wiki folder
            $this->storageService->createFolder($channelRoot, FileOperations::getChannelWikiName(), (string)$userID);
            $channelWikiRoot = "$channelRoot/" . FileOperations::getChannelWikiName();

            // create deleted folder
            $this->storageService->createFolder($channelRoot, FileOperations::getDeletedItemsName(), (string)$userID);
            $channelDeletedRoot = "$channelRoot/" . FileOperations::getDeletedItemsName();

        } catch (StorageServiceException $e) {
            $this->logger->debug('Failed to create channel roots. ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }

        // ... Store paths in mapping table
        $channelID = (int)$channel->getId();
        $cp = ChannelPath::create($channelID, ChannelPath::CHANNEL_PATH_TYPE_FILE, "/f$channelFilesRoot", $userID);
        if (!$this->dc->createChannelPath($cp)) {
            $this->logger->debug(__CLASS__.":".__FUNCTION__ . " : create Path Mapping Failed: ".print_r($cp, true) );
            return false;
        }

        $wp = ChannelPath::create($channelID, ChannelPath::CHANNEL_PATH_TYPE_WIKI, "/f$channelWikiRoot", $userID);
        if (!$this->dc->createChannelPath($wp)) {
            $this->logger->debug(__CLASS__.":".__FUNCTION__ . " : create Path Mapping Failed: ".print_r($cp, true) );
            return false;
        }

        $dip = ChannelPath::create($channelID, ChannelPath::CHANNEL_PATH_TYPE_DELETED, "/f$channelDeletedRoot", $userID);
        if (!$this->dc->createChannelPath($dip)) {
            $this->logger->debug(__CLASS__.":".__FUNCTION__ . " : create Path Mapping Failed: ".print_r($cp, true) );
            return false;
        }


        $wikiRoot = "/wf/{$dip->getId()}";

        // ... Now load up the default wiki data into files
        $wiki_files = SafeFile::scandir(CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.FileOperations::getChannelWikiName());
        foreach ($wiki_files as $wiki_file)
        {
            if ($wiki_file == '.' || $wiki_file == '..')
                continue;

            $fullFilePath = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.FileOperations::getChannelWikiName().DIRECTORY_SEPARATOR.$wiki_file;
            if (SafeFile::is_dir($fullFilePath))
                continue;

            try {
                $file = $this->storageService->uploadFile($channelWikiRoot, $wiki_file, $fullFilePath, 0, true, (string)$userID);
            } catch (StorageServiceException $e) {
                $this->logger->error(__CLASS__.":".__FUNCTION__ . " Failed to Upload Wiki Files to newly created channel..");
                continue;
            }

            $pattern = '/^' . preg_quote($channelWikiRoot, '/') .'/';
            $relativePath = preg_replace($pattern, $wikiRoot, $file->getPath());

            $this->fileIndexer->indexDocumentName($file->getId(), $file->getName(), $file->getPath(), $relativePath, $channelID, $file->getExtension(), 'file');

            if ($file->getExtension() === 'md') {
                $contentParsersConfig = $this->config->get('/search/content_parsers');
                $parser = ContainerFacade::get($contentParsersConfig['md']);
                $this->fileIndexer->indexDocumentContent(
                    $file->getId(),
                    $file->getName(),
                    $file->getPath(),
                    $relativePath,
                    $channelID,
                    $file->getExtension(),
                    $parser,
                    $fullFilePath
                );
            }

        }
        return true;
    }

    public function onRenameChannel(Channel $channel)
    {
        // ... Get the Channel Paths associated with this channel
        // ... For now use the FILE path and use the parent path
        $channelFilePath = "";
        $channelPaths = $this->dc->getChannelPathsByChannelId((int)$channel->getId());
        foreach($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            // ... Convert the file path to channel path
            if ($channelPath->getPathType() == ChannelPath::CHANNEL_PATH_TYPE_FILE)
            {
                $channelFilePath = $channelPath->getPath();
            }
        }

        if ($channelFilePath == "")
            return false;

        $channelRoot = Path::createFromPath($channelFilePath)->getParent();
        // For now, we are assuming there is only one single channel root associated with a channel
        // In the future, this might not be true

        /*
        // ... Sanity check herem need
        $teamID = $channel->getTeamId();
        $computedPath = "/f/$teamID/Channels/".$oldName;
        if ($computedPath != $channelRoot)
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " Computed Channel Path Doesn't Match Channel Root for Channel Rename ". $computedPath." ".$channelRoot);
            return false;
        }
        */

        // ... Move to the new name
        $newPath = Path::createFromPath($channelRoot)->getParent()."/".$channel->getName();
        $this->logger->debug("Moving Channel Folder from ".$channelRoot . ' to '.$newPath);
        if (!$this->fc->move($channelRoot, $newPath))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " Failed Channel Folder Rename for Channel Rename ". $channelRoot." ".$newPath);
            return false;
        }


        // ... Update the channel paths in the Channel Path
        $updatedChannelPaths = array();
        $channelPaths = $this->dc->getChannelPathsByChannelId((int)$channel->getId());
        foreach($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            // ... Convert the file path to channel path
            $updatedPath = StringUtility::str_replace_first($channelRoot, $newPath, $channelPath->getPath());
            $this->logger->debug("Moving DB Channel Path from ".$updatedPath . ' to '.$channelPath->getPath());
            $channelPath->setPath($updatedPath);
            $updatedChannelPaths[] = $channelPath;
        }

        foreach ($updatedChannelPaths as $channelPath) {
            $this->dc->updateChannelPath($channelPath);
        }

        return true;

    }

    /**
     * @param User $user
     * @return array|int[]
     * @throws DatabaseException
     * @throws FSOpException
     * @throws UnknownPolicyEntityException
     * @throws UnconfiguredPolicyException
     * @throws UnsupportedPolicyException
     * @throws UnsupportedPolicyTypeException
     */
    public function getUserQuota(User $user): array
    {
        $team = $this->dc->getDefaultTeamForUser((int)$user->getId());
        if ($team === null) {
            return ['quota' => 0];
        }

        $quota = Utility::GBTobytes((float)$this->policyManager->getPolicyValue($team, StorageQuota::class));
        try {
            $folderProperties = $this->storageService->folderProperties("/{$team->getId()}");
        } catch (NotFoundException $e) {
            return ['quota' => $quota];
        }

        return [
            'quota' => $quota,
            'total_fs_count' => $folderProperties->getTotalCount(),
            'total_fs_size' => $folderProperties->getTotalSize(),
        ];
    }

    /**
     * Gets the top level folder roots given a user
     *
     * @param User $user
     * @param array $paths Array of FileResource resources
     * @param int $type
     * @return bool
     * @throws DatabaseException
     * @throws NotImplementedException
     * @throws TeamOpException
     */
    public function getUserRoots(User $user, array &$paths, int $type = self::ROOTS_TYPE_SELF): bool
    {

        // Figure out all the teams the user is part of
        $teamUsers = $this->dc->getTeamUsersForUser((int)$user->getId());

        $teams = [];
        foreach($teamUsers as $teamUser) {
            $team = $this->dc->getTeamByTeamId((int)$teamUser->getTeamId());
            if (empty($team)) {
                $this->logger->error(__CLASS__.":".__FUNCTION__ . " Team doesn't exist for ".$teamUser->getTeamId());
                throw new TeamOpException("Team does not exist for ".$teamUser->getTeamId());
            }

            if ($team->getTeamType() == TEAM::TEAM_TYPE_SELF) {
                if ($type !== self::ROOTS_TYPE_CHANNEL_LIST) {
                    $paths[] = new FileResource("/f/".$team->getId());
                }
                $teams[] = $team->getId();
            } else {
                // for now we just ignore regular teams here (it's not used at all)
                continue;
            }
        }

        if ($type === self::ROOTS_TYPE_SELF) {
            return true;
        }

        $sharedCount = 0;

        $sharedChannels = $this->dc->getChannelsForUser((int)$user->getId());
        foreach($sharedChannels as $channelData)
        {
            $channel = Channel::withDBData($channelData);
            // ... If we wanted to hide our own channels
            if (!in_array($channel->getTeamId(), $teams))
            {
               // $this->dc->getCacheDataController()->forgetGlobalAuthContextCache((int)$user->getId());
                $authContext = $this->globalAuthContext->getEffectivePermission($user->getId(), GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $channel->getId());
                //$logger->debug("Channel Translate Path: Auth Context: ".print_r($authContext, true));
                if ($authContext >= GlobalAuthContext::AUTH_WRITE) {
                    $channelPaths = $this->dc->getChannelPathsByChannelId($channel->getId());
                    foreach($channelPaths as $channelPathData) {
                        $channelPath = ChannelPath::withDBData($channelPathData);
                        // ... Convert the file path to channel path
                        if ($channelPath->getPathType() == ChannelPath::CHANNEL_PATH_TYPE_FILE)
                        {
                            if ($type == self::ROOTS_TYPE_USER_LIST)
                                $sharedCount++;
                            else if ($type == self::ROOTS_TYPE_CHANNEL_LIST)
                                $paths[] = new ChannelFileResource('/cf/'.$channelPath->getId());
                        }
                    }
                }
            }
        }

        if (($type == self::ROOTS_TYPE_USER_LIST) && $sharedCount > 0)
        {
            $paths[] = new ChannelFileResource('/cf');
        }


        return true;
    }

    /**
     * Gets the top level Channel Roots
     *
     * @param Channel $channel
     * @param int|null $type
     * @return TranslatedPath[]|TranslatedPath
     * @throws DatabaseException
     * @throws FSOpException
     */
    public function getChannelRoots(Channel $channel, ?int $type = null)
    {

        // we only support files and wiki roots
        $supportedTypes = [ChannelPath::CHANNEL_PATH_TYPE_FILE, ChannelPath::CHANNEL_PATH_TYPE_WIKI];

        if ($type !== null && !in_array($type, $supportedTypes)) {
            throw new FSOpException('Invalid path type');
        }

        $channelPaths = $this->dc->getChannelPathsByChannelId((int)$channel->getId());

        $translatedPaths = [];
        foreach($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);


            // if type is an integer, return the requested type
            if ($type !== null && $channelPath->getPathType() === $type) {
                return $this->extractPathsFromChannelPath($channelPath);
            }

            // if type is null, return all supported types into an array
            if (in_array($channelPath->getPathType(), $supportedTypes)) {
                $translatedPaths[] = $this->extractPathsFromChannelPath($channelPath);
            }
        }
        return $translatedPaths;
    }


    /**
     * Handles a channel close event
     *
     * @param Channel $channel
     */
    public function onCloseChannel(Channel $channel)
    {
        // ... Nothing
    }

    /**
     * Handles a channel delete event
     *
     * @param Channel $channel
     */
    public function onDeleteChannel(Channel $channel)
    {
        $this->dc->deleteChannelPathsByChannelId((int)$channel->getId());
        return true;
    }

    /**
     * Handles a Team Delete
     *
     * @param Team $team
     * @return bool
     */
    public function onDeleteTeam(Team $team)
    {

        // ... Remove File Object Mappings
        // ... All delete for channels should be called before the team comes in
        // ... But we are just being defensive
        $teamID = (int)$team->getId();
        $this->logger->debug(__CLASS__.":".__FUNCTION__. " On Delete Team ". $teamID);
        $channels = $this->dc->getChannelsByTeamId($teamID);
        foreach ($channels as $channelData)
        {
            $channel = Channel::withDBData($channelData);
            $this->logger->warning(__CLASS__.":".__FUNCTION__. " Found Uncleaned channels when deleting team: ".$channel->getId());
            if (!$this->dc->deleteChannelPathsByChannelId((int)$channel->getId()))
            {
                $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed to delete channel Path when deleting channel ID ". $channel->getId());
            }
        }

        // ... Delete all data associated
        $teamPath = "/f/$teamID";
        return $this->fc->delete($teamPath);
    }

    /**
     * Handles a User Delete
     *
     * @param User $user
     */
    public function onDeleteUser(User $user)
    {

    }

    /**
     * @param Resource $resource
     * @param User|int|null $user
     * @throws DatabaseException
     * @throws UnknownUserException
     * @throws BadResourceTranslationException
     * @deprecated
     */
    private function translate(Resource $resource, $user)
    {
        if (!($user instanceof User)) {
            $user = $this->dc->getUserById((int)$user);
        }
        if (!isset($user)) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed to find user by User ID provided ". $user->getId());
            throw new UnknownUserException();
        }

        ResourceManager::translatePath($resource, $user, $this->dc);
    }

    /**
     * Uploads a file
     *
     * @param string $parentPath
     * @param string $name
     * @param string $localFilePath
     * @param User $loggedUser
     * @param int $chunkStart
     * @param bool $finalChunk
     * @param float $sessionId
     * @return void
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSNotFoundException
     * @throws FSOpException
     * @throws FSOpServerException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function upload(string $parentPath, string $name, string $localFilePath, User $loggedUser, int $chunkStart = 0, bool $finalChunk = true, $sessionId = 0.1): void
    {

        $translatedParentPath = $this->translatePath($parentPath);

        // User must have upload permissions on the parent path
        if ($loggedUser->cannot('upload', $translatedParentPath)) {
            throw new FSAuthorizationException('User is not allowed to upload to this folder');
        }

        // TODO - Check if the team is blocked by quota limit
        // ... find the team that holds the parent path
        //$team = $translatedParentPath->getTeam();

        // TODO - move this to event handling...
//        $props = $this->fc->folderProps($translatedPath);
//        if ($props == false)
//        {
//            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: folder Props failed during upload for ". $fsparent."/".$fsname." by ".$userid);
//            //return false;
//        }

//        if ($user->getUserRole() != User::USER_ROLE_SERVICE_ADMIN) {
//            $team = $this->dc->getDefaultTeamForUser($userid);
//            $quota = $this->policyManager->getEffectivePolicyValueForKey($team, PolicyTypes::STORAGE_QUOTA_IN_GB);
//            $fileSize = SafeFile::filesize($srcpath);
//            $usedSize = $props->getTotalSize() + $fileSize;
//            $maxSize = Utility::GBTobytes((float)$quota);
//            if ($usedSize > $maxSize) {
//                $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Used: " . Utility::bytesToGB($usedSize) . " Max Allowed: " . Utility::bytesToGB($maxSize));
//                $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Error: Quota exceeded cannot upload " . $fsparent . "/" . $fsname . " by " . $userid);
//                $this->errorMessage = "Error: Quota exceeded. Cannot upload";
//
//                //return false;
//            }
//        }

        // ... if the file already exists (we're uploading a new version) ...
        $dstPath = "{$translatedParentPath->getPhysicalPath()}/$name";
        $isNew = !$this->storageService->exists($dstPath);

        // Wait for 10s to get access to upload this file.
        if ($this->csec->acquireSection($dstPath, $sessionId, 10)) {

            // upload the file
            try {
                $file = $this->storageService->uploadFile($translatedParentPath->getPhysicalPath(),
                    $name,
                    $localFilePath,
                    $chunkStart,
                    (bool)$finalChunk,
                    (string)$loggedUser->getId());
            } catch (DestinyPathAlreadyExistsException $e) {
                throw new FSOpException('Destination path is a folder.');
            } catch (NotAFolderException $e) {
                throw new FSNotFoundException('Destination folder is not valid');
            } catch (NotFoundException $e) {
                throw new FSNotFoundException('Destination folder was not found');
            } catch (StorageServiceException $e) {
                throw new FSOpServerException($e->getMessage());
            }

            $this->csec->releaseSection($dstPath, $sessionId);

            // if we completed the upload, emmit the add file event

            if ($file instanceof FileInterface && ($loggedUser->getUserRole() != User::USER_ROLE_SERVICE_ADMIN)) {

                // Generate Airbot message if the upload is been made to a channel (files or wiki) and it's not the attachments folder
                $genAirBotMessage = $translatedParentPath->getChannel() !== null && !preg_match('/^\/cf\/[0-9]+\/attachments$/', $translatedParentPath->getRelativePath());

                $event = new FSAddFileEvent($translatedParentPath, $file->getId(), $name, $loggedUser->getId(), $file->getFileSize(), $isNew, $genAirBotMessage);
                $this->eventManager->publishEvent($event);

            }

        } else {
            $this->logger->error(__FUNCTION__ . " : Failed to get critsec lock for $dstPath");
            throw new FSOpException("Failed to get critsec lock for $dstPath");
        }

    }

    /**
     * Uploads a file as an attachment to a channel, and returns the path of the updated file.
     * Returns null if it's not possible to upload the file.
     *
     * @param Channel $channel
     * @param User $fileOwner
     * @param string $destFileName
     * @param string $srcFilePath
     * @return string|null
     * @throws SecurityException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     * @throws UnknownPolicyEntityException
     */
    public function uploadAttachmentToChannel(Channel $channel, User $fileOwner, string $destFileName, string $srcFilePath): ?string
    {
        // get the channel files root
        $channelRoots = $this->getChannelRoots($channel);
        foreach ($channelRoots as $channelRoot) {
            if ($channelRoot->getType() === 'files') {
                $channelFilesRoot = $channelRoot->getRelativePath();
            }
        }

        // upload the file
        if (isset($channelFilesRoot)) {
            $parentPath = "$channelFilesRoot/attachments";
            $this->upload($parentPath, $destFileName, $srcFilePath, $fileOwner);
            return "$parentPath/$destFileName";
        }

        return null;
    }

    /**
     * @param string $mainPath
     * @param string $localPath
     * @throws DatabaseException
     * @throws FSNotFoundException
     * @throws FSOpException
     */
    public function uploadSideCar(string $mainPath, string $localPath)
    {
        $translatedPath = $this->translatePath($mainPath);

        // main path must exist
        if (!$this->storageService->exists($translatedPath->getPhysicalPath())) {
            throw new FSNotFoundException($mainPath);
        }

        $thumbwidth = 120; // ... TODO: Hardcoded
        $thumbheight = 120; // ... TODO: Hardcoded
        $sidecarMetadata = "THUMB|".$thumbwidth."x".$thumbheight;

        // upload the sidecar to the storage
        try {
            $this->storageService->uploadSidecar($translatedPath->getPhysicalPath(), $sidecarMetadata, $localPath);
        } catch (StorageServiceException $e) {
            $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ . " Failed uploading sidecar for " . $mainPath);
            throw new FSOpException('Failed to upload the sidecar.');
        } finally {
            // cleanup the local file
            unlink($localPath);
        }
    }


    /**
     * Downloads a file
     *
     * @param string $path
     * @param string|null $versionId
     * @param User|null $loggedUser
     * @param string $downloadType Can be 'redirect', 'local' or 'stream'.
     * @return DownloadResource
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSOpException
     */
    public function download(string $path, ?string $versionId = null, ?User $loggedUser = null, string $downloadType = 'redirect'): DownloadResource
    {

        $translatedPath = $this->translatePath($path);

        // if the user is provided, we check permissions
        // user must have read access
        if ($loggedUser !== null && $loggedUser->cannot('read', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        try {
            $downloadResponse = $this->storageService->download($translatedPath->getPhysicalPath(), $versionId, $downloadType);
        } catch (NotAFileException $e) {
            throw new FSOpException('Required path is not a file');
        } catch (NotFoundException $e) {
            throw new FSOpException("Required path doesn't exist");
        }

        return new DownloadResource($downloadResponse->getType(), $downloadResponse->getPayload());

    }

    /**
     * @param string $path
     * @param User|null $user
     * @throws DatabaseException
     * @throws DestinyPathAlreadyExistsException
     * @throws FSAuthorizationException
     * @throws FSOpException
     * @throws FSOpServerException
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function delete(string $path, ?User $user): void
    {

        // we can't delete special channel paths
        if (
            preg_match('/^\/cf\/[0-9]+\/attachments$/', $path) ||
            preg_match('/^\/wf\/[0-9]+\/index.md$/', $path)
        ) {
            throw new FSAuthorizationException("Not allowed");
        }

        $translatedPath = $this->translatePath($path);

        // if the user is provided, we check permissions
        if ($user !== null && $user->cannot('delete', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        // find the storage entry to be able to trigger the event later
        $entry = $this->storageService->info($translatedPath->getPhysicalPath());

        // if it's a channel path...
        if (($channel = $translatedPath->getChannel()) !== null) {

            // find the deleted files folder for the channel
            $channelPaths = $this->dc->getChannelPathsByChannelId($channel->getId());
            $deletedPath = null;
            $filesPath = null;
            foreach ($channelPaths as $channelPath) {
                if ((int)$channelPath['path_type'] === ChannelPath::CHANNEL_PATH_TYPE_DELETED) {
                    $deletedPath = $channelPath['path_value'];
                }
                if ((int)$channelPath['path_type'] === ChannelPath::CHANNEL_PATH_TYPE_FILE) {
                    $filesPath = $channelPath['path_value'];
                }
            }
            $deletedPath = preg_replace('/^\/f/', '', $deletedPath);
            $filesPath = preg_replace('/^\/f/', '', $filesPath);

            $srcPath = $translatedPath->getPhysicalPath();
            $pattern = '/^' . preg_quote($filesPath, '/') . '/';
            $dstPath = preg_replace($pattern, $deletedPath, $srcPath);


            // ensure all folders on the path exists
            $parentPath = trim(preg_replace('/\/[^\/]+$/', '', $dstPath), '/');
            $steps = explode('/', $parentPath);
            $currentPath = '';
            foreach ($steps as $step) {

                // check if the path exists, if not, create
                if (!$this->storageService->exists("$currentPath/$step")) {
                    $this->storageService->createFolder($currentPath, $step, (string)$user->getId());
                }

                $currentPath = "$currentPath/$step";

            }

            // finally move the entry to deleted items (doing merge if necessary)...
            $this->storageService->move($srcPath, $dstPath, true);

        } else {
            // it's a storage (no channel) path, so truly delete the entry
            $this->storageService->delete($translatedPath->getPhysicalPath());
        }

        // trigger the event
        $event = new FSDeleteEvent($translatedPath, $entry->getId(), $entry->getName(), $user->getId(), false);
        $this->eventManager->publishEvent($event);

    }

    /**
     * @param int $userid
     * @return array|false
     * @todo Need to refactor this method to use the new translatedPath platform/authorization system
     */
    public function synclist(int $userid)
    {
        $user = $this->dc->getUserById($userid);
        if (!isset($user))
            return false;

        $resources = [];
        $items = [];
        // TODO - getUserRoots signature changed, need to check
        if (!$this->getUserRoots($user, $resources))
            return false;

        foreach ($resources as $resource)
        {
            $this->translate($resource, $userid);
            $path = Path::createFromPath($resource->getResourceIdentifier());

            $syncversion = '';
            $item =  $this->info($resource->getResourceIdentifier(), $userid);
            if ($item == false)
                return false;

            $items[] = FileSystemObject::createFromData($path->getName(), $path->getParent(), "", "folder", 0,
                $item->getCreationDate(), $item->getModificationDate(),
                $item->getAccessDate(),
                $resource->getResourceDisplayName(), $resource->getResourceDisplayPath(), $item->getSyncVersion());
        }

        $returnArr = array('total' => sizeof($items), 'items' => $items);
        return $items;
    }

    /**
     * Returns the version information related to a specific file
     *
     * @param string $path
     * @param User $loggedUser
     * @return FileSystemObject[]
     * @throws FSNotFoundException
     * @throws FSAuthorizationException
     */
    public function versions(string $path, User $loggedUser): array
    {
        /** @var TranslatedPath $translatedPath */
        $translatedPath = Memoizer::memoized([$this, 'translatePath'])($path);

        // if the user is provided, we check permissions. User must have read permissions on the path
        if ($loggedUser !== null && $loggedUser->cannot('read', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        try {
            $items = $this->storageService->versions($translatedPath->getPhysicalPath());
        } catch (NotFoundException $e) {
            throw new FSNotFoundException("The file was not found");
        }

        return array_map(function ($item) use ($translatedPath) {

            $pattern = '/^' . preg_quote($translatedPath->getPhysicalPath(), '/') . '/';

            $relativePath = $translatedPath->getRelativePath();
            if ($relativePath !== null) {
                $relativePath = preg_replace($pattern, $relativePath, $item->getPath());
            }

            $displayPath = $translatedPath->getDisplayPath();
            if ($displayPath !== null) {
                $displayPath = preg_replace($pattern, $displayPath, $item->getPath());
            }
            return FileSystemObject::createFromFSEntry($item, $relativePath, $displayPath, $item->getVersionedDate());

        }, $items);

    }

    private function isThumbSupported(string $fileExt)
    {
        //$this->logger->info("EXTENSION = $fileExt");
        $formats = array("jpg", "jpeg", "png", "gif", 'pdf', 'docx', 'doc', 'pptx', 'ppt', 'xlsx', 'xls', 'numbers', 'pages', 'key', 'mp4', 'mov', 'avi');
        return in_array(strtolower($fileExt), $formats);
    }

    private function isDocThumbSupported(string $fileExt)
    {
        //$this->logger->info("EXTENSION = $fileExt");
        $formats = array('pdf', 'docx', 'doc', 'pptx', 'ppt', 'xlsx', 'xls', 'numbers', 'pages', 'key');
        return in_array(strtolower($fileExt), $formats);
    }

    private function isVideoThumbSupported(string $fileExt)
    {
        //$this->logger->info("EXTENSION = $fileExt");
        $formats = array('mp4', 'mov', 'avi');
        return in_array(strtolower($fileExt), $formats);
    }


    /**
     * Should only be used by internal APIs, not meant for external use
     *
     * @param string $path
     * @param int $thumbwidth
     * @param int $thumbheight
     * @return bool
     * @throws DatabaseException
     * @throws FSOpException
     */
    public function hasThumb(string $path, int $thumbwidth, int $thumbheight): bool
    {
        $translatedPath = $this->translatePath($path);

        $sideCarMetaData = "THUMB|".$thumbwidth.'x'.$thumbheight;

        try {
            $this->storageService->infoSideCar($translatedPath->getPhysicalPath(), $sideCarMetaData);
            return true;
        } catch (NotFoundException $e) {
            return false;
        }
    }

    /**
     * @param ZipStream $zip
     * @param string $rootPath
     * @param User $user
     * @param string $subFolder
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function processZipFolder(ZipStream $zip, string $rootPath, User $user, string $subFolder = '')
    {
        $cursor = null;
        while (true) {

            $items = $this->storageService->list(rtrim("$rootPath/$subFolder", '/'), $cursor);

            if (empty($items)) {
                return;
            }

            foreach ($items as $item) {

                $cursor = $item->getPath();

                if ($item instanceof FolderInterface) {
                    $this->processZipFolder($zip, $rootPath, $user, trim("$subFolder/{$item->getName()}", '/'));
                } else {
                    $internalPath = trim("$subFolder/{$item->getName()}", '/');
                    $physicalPath = "$rootPath/$internalPath";

                    $download = $this->storageService->download($physicalPath, null, 'stream');
                    $stream = $download->getPayload()['stream'];

                    $zip->addFileFromPsr7Stream($internalPath,$stream);
                }
            }
        }
    }

    // ... Callback invoked from Response processing
    // ... This directly sends output to the caller
    public function sendZipCallback(string $path, User $loggedUser)
    {

        $translatedPath = $this->translatePath($path);

        $this->logger->debug("Download Zip Started: {$translatedPath->getPhysicalPath()}");

        # enable output of HTTP headers
        $options = new \ZipStream\Option\Archive();
        $options->setSendHttpHeaders(true);
        $options->setFlushOutput(true);

        # create a new zipstream object
        $zip = new ZipStream($translatedPath->getName().'.zip', $options);

        // ... Adding a small file starts the download fast so user gets save prompt
        if (($channel = $translatedPath->getChannel()) !== null) {
            $content = <<<content
Zip generated by download from AirSend
Channel: {$channel->getName()}
Folder: {$translatedPath->getChannelDisplayPath()}
Created by: {$loggedUser->getEmail()}
content;
        } else {
            $content = <<<content
Zip generated by download from AirSend
Folder: {$translatedPath->getDisplayPath()}
Created by: {$loggedUser->getEmail()}
content;
        }

        $zip->addFile('readme.txt', $content);

        // ... Process the actual folder
        $this->processZipFolder($zip, $translatedPath->getPhysicalPath(), $loggedUser);

        $zip->finish();

        $this->logger->debug("Download Zip Completed: {$translatedPath->getPhysicalPath()}");
    }

    /**
     * @param string $path
     * @param int $width
     * @param int $height
     * @param User|null $loggedUser
     * @param float $sessionid
     * @param string $downloadType
     * @return DownloadResource
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSNotFoundException
     * @throws FSOpException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function thumb(string $path, int $width, int $height, ?User $loggedUser, $sessionid = 0.1, string $downloadType = 'redirect'): DownloadResource
    {

        $translatedPath = $this->translatePath($path);

        // if the user is provided, we check permissions
        // user must have read access
        if ($loggedUser !== null && $loggedUser->cannot('read', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        // main file must exist and it must be a file
        try {
            $mainFile = $this->storageService->info($translatedPath->getPhysicalPath());
        } catch (NotFoundException $e) {
            throw new FSNotFoundException('Invalid path');
        }
        if (!($mainFile instanceof FileInterface)) {
            throw new FSNotFoundException('Invalid path');
        }
        $extension = $mainFile->getExtension();
        $mainPath = $mainFile->getPath();

        // find the default image, just in case it's necessary
        $stockImage = $this->getDefaultImage($extension, $width, $height);

        // check if thumb is supported for the main file extension
        if (!$this->isThumbSupported($extension)) {
            return new DownloadResource('file', ['file' => $stockImage]);
        }

        if ($this->csec->acquireSection($mainPath, $sessionid, 30)) {

            // try to download the sidecar
            $sideCarMetaData = "THUMB|{$width}x{$height}";
            try {
                $downloadResponse = $this->storageService->downloadSidecar($translatedPath->getPhysicalPath(), $sideCarMetaData, $downloadType);
                $this->csec->releaseSection($mainPath, $sessionid);
                return new DownloadResource($downloadResponse->getType(), $downloadResponse->getPayload());
            } catch (NotFoundException $e) {
                // the side car doesn't exists, just go forward...
            }

            if ($this->isDocThumbSupported($extension) || $this->isVideoThumbSupported($extension)) {
                // ... We cannot create doc or video thumbs on the fly
                $this->csec->releaseSection($mainPath, $sessionid);
                return new DownloadResource('file', ['file' => $stockImage]);
            }

            // find the maximum file size to generate a thumb
            $maxThumbMb = (int)$this->config['/thumbnail/max_size_mb'];
            if (empty($maxThumbMb)) {
                $maxThumbMb = 20;
            }

            // ... Dont generate for large files
            if ($mainFile->getFileSize() > $maxThumbMb * 1000 * 1000) {
                $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ . " : Skipping thumb gen for Size " . $mainFile->getFileSize() . " for file $path");
                $this->csec->releaseSection($mainPath, $sessionid);
                return new DownloadResource('file', ['file' => $stockImage]);
            }

            // download the main file
            try {
                $download = $this->storageService->download($mainPath, null, 'local');
            } catch (NotAFileException | NotFoundException $e) {
                // anything whent wrong, we don't generate the thumb
                $this->csec->releaseSection($mainPath, $sessionid);
                return new DownloadResource('file', ['file' => $stockImage]);
            }
            $localFile = $download->getPayload()['tmpfile'];

            // resize it
            $basename = bin2hex(random_bytes(8));
            $localThumbFile = Directories::scratch('tmp/' . sprintf('tmp_thumb_%s.%0.8s', $basename, $extension));
            if (!Image::resizeImage($localFile, $extension, $width, $height, $localThumbFile)) {
                $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ . " Failed Creating Thumb Image for " . $path);
                $this->csec->releaseSection($mainPath, $sessionid);
                return new DownloadResource('file', ['file' => $stockImage]);
            }

            // upload the thumb to the storage (next time we get it directly
            try {
                $this->storageService->uploadSidecar($mainPath, $sideCarMetaData, $localThumbFile);
            } catch (StorageServiceException $e) {
                $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ . " Failed uploading Thumb Image for " . $path);
                $this->csec->releaseSection($mainPath, $sessionid);
                return new DownloadResource('file', ['file' => $stockImage]);
            }

            // cleanup everything and return the thumb content
            $content = file_get_contents($localThumbFile);
            unlink($localThumbFile);
            unlink($localFile);
            $this->csec->releaseSection($mainPath, $sessionid);

            return new DownloadResource('content', ['content' => $content, 'type' => MimeType::getFileExtension($extension)]);

        } else {
            $this->logger->error(__FUNCTION__ . " : Failed to get critsec lock for $mainPath");
            throw new FSOpException("Failed to get critsec lock for $mainPath");
        }
    }

    private function get404Thumb(int $thumbwidth, int $thumbheight)
    {
        $rootDir = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'images';
        $stockfolderjpg = $rootDir.DIRECTORY_SEPARATOR.'file512404.png';
        return $stockfolderjpg;
    }

    private function getDefaultImage(string $fileExt, int $thumbwidth, int $thumbheight)
    {
        $rootDir = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'images';

        $musicFormats = array("mp3", "wav", "wma", "m4a", "ogg", "flac", "aac", "m4b");
        $videoFormats = array("avi", "mpg", "mpeg", "mp4", "mov", "wmv", "webm", "ogg", "mkv", "m4v", "vob", "wmv",
            "xvid", "flv", "3gp", "swf", "divx");
        $availableIconFormats = array("doc", "docx", "pdf", "ppt", "pptx", "txt", "xls", "xlsx", "zip");

        $stockfolderjpg = $rootDir.DIRECTORY_SEPARATOR.'file512image.png';
        if (in_array($fileExt, $musicFormats))
        {
            $stockfolderjpg = $rootDir.DIRECTORY_SEPARATOR.'file512music.png';
        }
        else if (in_array($fileExt, $videoFormats))
        {
            $stockfolderjpg = $rootDir.DIRECTORY_SEPARATOR.'file512video.png';
        }
        else if (in_array($fileExt, $availableIconFormats))
        {
            $stockfolderjpg = $rootDir.DIRECTORY_SEPARATOR."file512$fileExt.png";
        }

        return $stockfolderjpg;
    }

    public function cleanTmpFiles()
    {
        // ... Scan the tmp folder and clean up files
        $tmpdir = CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'scratch'.DIRECTORY_SEPARATOR.'tmp';
        $DAY_SEC=86400;

        $filecount=0;
        $maxdays = 1; // ... delete after maxdays, right now 1 day

        if(!SafeFile::file_exists($tmpdir)) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Tmp Folder is missing");
            return;//dont proceed further if xampp\tmp folder is not found
        }

        $handle=null;

        try {
            //check if the xampp\tmp folder is opened successfully
            if ($handle = SafeFile::opendir($tmpdir))
            {
                $pattern="/^tmp_.*|^s3d.*|^chunk.*/"; //tmp_ or s3d or chunk files only
                while(($file = readdir($handle)) !== FALSE)
                {
                    if(preg_match($pattern,$file))
                    {
                        $fpath=$tmpdir.DIRECTORY_SEPARATOR.$file;
                        $days=intval((time()-SafeFile::filectime($fpath))/($DAY_SEC));
                        if($days>$maxdays)
                        {
                            SafeFile::unlink($fpath);
                            $filecount++;
                        }
                    }
                }

                SafeFile::closedir($handle);
                if ($filecount > 0)
                    $this->logger->debug(__CLASS__.":".__FUNCTION__. " Successfully deleted ".$filecount." temp files on cron");
            }

        }catch (\Exception $ex) {
            if(!is_null($handle))
                SafeFile::closedir($handle);

            $this->logger->error(__CLASS__.":".__FUNCTION__. " Deletion of temp files failed. Exception message: ".$ex->getMessage());
        }
    }

    public function onCron()
    {
        try {
            $this->cleanTmpFiles();
        }
        catch(\Exception $ex)
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " cleanTmpFiles Exception message: ".$ex->getMessage());
        }
    }

    /**
     * @param string $path
     * @return FolderProperties
     * @throws NotFoundException
     */
    public function folderProps(string $path): FolderProperties
    {
        return $this->storageService->folderProperties($path);
    }


    private function getChannelRootPath(Channel $channel)
    {
        // ... Get the Channel Paths associated with this channel
        // ... For now use the FILE path and use the parent path
        $channelFilePath = "";
        $channelPaths = $this->dc->getChannelPathsByChannelId((int)$channel->getId());
        foreach($channelPaths as $channelPathData) {
            $channelPath = ChannelPath::withDBData($channelPathData);
            // ... Convert the file path to channel path
            if ($channelPath->getPathType() == ChannelPath::CHANNEL_PATH_TYPE_FILE)
            {
                $channelFilePath = $channelPath->getPath();
            }
        }

        if ($channelFilePath == "")
            return "";

        $channelRoot = Path::createFromPath($channelFilePath)->getParent();
        // For now, we are assuming there is only one single channel root associated with a channel
        // In the future, this might not be true

        return $channelRoot;
    }

    /**
     *
     * Copy all file and wiki assets between channels
     *
     * @param Channel $source Source channel object
     * @param Channel $target Target channel object
     * @param User $copiedBy User who initated the copy
     */
    public function onCopyChannel(Channel $source, Channel $target, User $copiedBy)
    {
        $sourceRoot = $this->getChannelRootPath($source);
        if ($sourceRoot == "")
            return false;

        $targetRoot = $this->getChannelRootPath($target);
        if ($targetRoot == "")
            return false;

        // Wipe the $targetRoot first
        $this->logger->debug("Wiping Target  ".$targetRoot );
        if (!$this->fc->delete($targetRoot))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " Failed Channel Root Delete for Channel Copy ". $targetRoot);
            return false;
        }

        // ... Copy to the new name
        $this->logger->debug("Copying Channel Folder from ".$sourceRoot . ' to '.$targetRoot);
        if (!$this->fc->copy($sourceRoot, $targetRoot))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " Failed Channel Folder Root Copy for Channel Copy ". $sourceRoot." ".$targetRoot);
            return false;
        }

        if (!$this->fc->delete($targetRoot."/".$this->getDeletedItemsName()))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " Failed delete deleted items for Channel Copy ". $targetRoot);
            return false;
        }

        if (!$this->fc->create($targetRoot, $this->getDeletedItemsName(), $copiedBy->getId()))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " Failed delete deleted items for Channel Copy ". $targetRoot);
            return false;
        }

        $this->logger->debug("Copying Channel Folder from ".$sourceRoot . ' to '.$targetRoot." OK");
        return true;

    }

    /**
     * @param string $wikiPath
     * @param User $loggedUser
     * @return array
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSOpException
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     * @throws MemoizingException
     */
    public function wikiTree(string $wikiPath, User $loggedUser): array
    {

        /** @var TranslatedPath $translatedPath */
        $translatedPath = Memoizer::memoized([$this, 'translatePath'])($wikiPath);

        // if the user is provided, we check permissions. User must have read permissions on the path
        if ($loggedUser !== null && $loggedUser->cannot('read', $translatedPath)) {
            throw new FSAuthorizationException('User is not allowed to access this path');
        }

        $items = $this->storageService->list($translatedPath->getPhysicalPath());

        $output = [];
        foreach ($items ?? [] as $item) {

            // find the wiki path for the item
            $pattern = '/^' . preg_quote($translatedPath->getPhysicalPath(), '/') . '/';
            $relativePath = $translatedPath->getRelativePath();
            $wikiPath = preg_replace($pattern, $relativePath, $item->getPath());

            if ($item instanceof FileInterface) {

                // skip files there are not .md
                if ($item->getExtension() !== 'md') {
                    continue;
                }

                // include the base output info
                $outputItem = [
                    'name' => $item->getName(),
                    'path' => $wikiPath,
                ];

                // download md content
                try {
                    $download = $this->storageService->download($item->getPath(), null, 'local');
                } catch (NotAFileException|NotFoundException $e) {
                    $output[] = $outputItem;
                    continue;
                }
                $localFile = $download->getPayload()['tmpfile'] ?? '';
                $content = file_get_contents($localFile);
                unlink($localFile);

                // cook the content for the summary
                $lines = explode(PHP_EOL, $content);
                $summary = [];
                while (count($summary) < 2 && !empty($lines)) {

                    $line = trim(array_shift($lines));

                    // skip empty lines and --- === ___
                    if (!empty($line) && !preg_match('/^[\s-_=]*$/', $line)) {
                        $summary[] = $line;
                    }

                }

                $outputItem['summary'] = $summary;
                $output[] = $outputItem;

            } elseif ($item instanceof FolderInterface) {
                $output[] = [
                    'name' => $item->getName(),
                    'path' => $wikiPath,
                    'items' => $this->wikiTree($wikiPath, $loggedUser),
                ];
            }
        }

        return $output;

    }

    /**
     * @param string $fspath
     * @param User $user
     * @return string
     * @throws BadResourceTranslationException
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     */
    public function createLink(string $fspath, User $user): string
    {
        $translatedPath = $this->translatePath($fspath);

        if ($user->cannot('upload', $translatedPath)) {
            throw new FSAuthorizationException('Write permission is needed to share a file through external link');
        }

        $hash = $this->dc->createPublicHash('File', $fspath);
        $fspath = preg_match('/^\//', $fspath) ? $fspath : "/{$fspath}";
        $url = "/files{$fspath}";
        $url .= "?hash=pub-{$hash}";

        $shortUrlHash = $this->dc->generateUniqueShortUrlHash();
        $this->dc->createShortUrl($url, $shortUrlHash, 'File', $fspath);

        return StringUtility::generateShortUrlFromHash($shortUrlHash);

    }

    public function validateThumbDimensions(int $width, int $height): bool
    {
        return in_array([$width, $height], static::THUMB_DIMENSIONS_WHITELIST);
    }

}