<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Lock;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\WopiDiscoveryException;
use CodeLathe\Core\Exception\WopiOpsException;
use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\LockAcquireEvent;
use CodeLathe\Core\Messaging\Events\LockReleaseEvent;
use CodeLathe\Core\Objects\ChannelFileResource;
use CodeLathe\Core\Objects\FileResource;
use CodeLathe\Core\Objects\Lock;
use CodeLathe\Core\Objects\Resource;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Objects\User;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

class LockOperations
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @var CriticalSection
     */
    protected $csec;

    protected $fOps;

    protected $eventManager;


    /**
     * ChannelManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param CriticalSection $csec
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                CriticalSection $csec,
                                FileOperations $fOps,
                                EventManager $eventManager
                                )
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->csec = $csec;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
    }


    /**
     *
     * Lock a file
     *
     * @param int $user_id
     * @param string $path
     * @param string $context
     * @param string|NULL $expiry
     * @return Lock|null
     * @throws DatabaseException
     * @throws UnknownResourceException
     * @throws InvalidArgumentException
     */
    public function lock(int $user_id, string $path, string $context = "", string $expiry = NULL) :?Lock
    {
        $lock = null;
        $user = $this->dataController->getUserById($user_id);
        if ($user == null) {
            $this->logger->error(__FUNCTION__. " User $user_id was not found");
            return null;
        }

        try {
            $translatedPath = $this->fOps->translatePath($path);
        } catch (FSOpException $e) {
            $this->logger->error(__FUNCTION__. " Failed to translated the path");
            return null;
        }

        if ($user->cannot('read', $translatedPath)) {
            $this->logger->error(__FUNCTION__. " Error: No Permissions for Reading to provided path by user ". $path." by ".$user_id);
            return null;
        }

        $sessionid = microtime(true);
        if ($this->csec->acquireSection($path, $sessionid, 30, 300)) {
            try {
                // Check if lock can be given and take lock
                $lock = $this->getLock($path);
                if (empty($lock)) {
                    $lock = Lock::create($path, $user_id, $context, $expiry);
                    $this->dataController->createLock($lock);

                    $this->raiseLockEvent((int)$lock->id(), $user_id, $translatedPath, true);

                } else if ($lock->path() == $path && (int)$lock->userId() == $user_id && $lock->context() == $context) {
                    // Can use just return this
                    $this->logger->info(__FUNCTION__ . " : Path [$path] is already locked by this user. Reusing" . print_r($lock->getArray(), true));
                } else {
                    // Lock cannot be given
                    $this->logger->info(__FUNCTION__ . " : LOCK NOT GIVEN. Path [$path] is locked by " . print_r($lock->getArray(), true));
                    $lock = NULL;
                }
            } catch (ASException $exception) {
                $this->logger->error(__FUNCTION__ . " : " . $exception->getMessage());
            }

            $this->csec->releaseSection($path, $sessionid);
        }
        else {
            $this->logger->error(__FUNCTION__ . ": Failed to get critsec lock for $path");
        }

        return $lock;
    }

    /**
     *
     * Unlock a path
     *
     * @param int $user_id
     * @param string $path
     * @param string $context
     * @return bool
     * @throws DatabaseException
     * @throws UnknownResourceException
     */
    public function unlock(int $user_id, string $path, string $context = "") :bool
    {

        $user = $this->dataController->getUserById($user_id);
        if ($user == null) {
            $this->logger->error(__FUNCTION__. " User $user_id was not found");
            return false;
        }

        try {
            $translatedPath = $this->fOps->translatePath($path);
        } catch (FSOpException $e) {
            $this->logger->error(__FUNCTION__. " Failed to translate the path");
            return false;
        }

        if ($user->cannot('read', $translatedPath)) {
            $this->logger->error(__FUNCTION__. " Error: No Permissions for Reading to provided path by user ". $path." by ".$user_id);
            return false;
        }

        try {
            $lock = $this->dataController->getLock($path);
            if (empty($lock)) {
                // No lock found for this path. Return success
                $this->logger->info(__FUNCTION__ . " : No lock found for $path to unlock!");
                return true;
            }

            // Lock found. But can only unlocked by the owner
            if ($lock->userId() === $user_id && $lock->context() === $context) {
                // Match.
                $this->logger->info(__FUNCTION__ . " : Unlocking $path");
                $this->dataController->deleteLock($lock->id());

                $this->raiseLockEvent((int)$lock->id(), $user_id, $translatedPath, false);

                return true;
            }

            $this->logger->info(__FUNCTION__ . " NOT ALLOWED - Unlock $path requested by $user_id [$context] for lock owned by " . print_r($lock->getArray(), true));
        }
        catch (ASException $exception) {
            $this->logger->error(__FUNCTION__ . " : " . $exception->getMessage());
        }
        return false;
    }

    /**
     *
     * Send a push event
     *
     * @param int $lock_id
     * @param int $user_id
     * @param TranslatedPath $translatedPath
     * @param bool $acquireEvent
     */
    private function raiseLockEvent(int $lock_id, int $user_id, TranslatedPath $translatedPath, $acquireEvent=true)
    {
        $channelInfoArray = [];
        if ($translatedPath->getChannel() !== null) {
            // Rise one event per channel
            foreach ($channelInfoArray as $record) {
                if ($acquireEvent) {
                    $event = new LockAcquireEvent($lock_id, $user_id, $record['channelpath'], $record['id']);
                    $this->eventManager->publishEvent($event);
                }
                else {
                    $event = new LockReleaseEvent($lock_id, $user_id, $record['channelpath'], $record['id']);
                    $this->eventManager->publishEvent($event);
                }
            }
        }
    }

    /**
     * @param int $id
     * @return Lock|null
     * @throws DatabaseException
     */
    public function getLockById(int $id): ?Lock
    {
        return $this->dataController->getLockById($id);
    }

    /**
     * @param string $path
     * @return Lock|null
     * @throws DatabaseException
     */
    public function getLock(string $path): ?Lock
    {
        return $this->dataController->getLock($path);
    }

    /**
     *
     * Refresh an existing lock . Useful for pushing expiry if set.
     *
     * @param Lock $lock
     * @return bool
     * @throws DatabaseException
     */
    public function update(Lock $lock): bool
    {
        return $this->dataController->updateLock($lock);
    }

}