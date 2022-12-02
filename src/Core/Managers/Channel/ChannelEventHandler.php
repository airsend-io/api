<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Channel;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\FSAddFileEvent;
use CodeLathe\Core\Messaging\Events\FSCreateFolderEvent;
use CodeLathe\Core\Messaging\Events\FSDeleteEvent;
use CodeLathe\Core\Messaging\Events\FSUpdateEvent;
use CodeLathe\Core\Messaging\Events\RtmInterface;
use CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

class ChannelEventHandler implements EventSubscriberInterface
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
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var NormalizedObjectFactory
     */
    protected $normalizedObjectFactory;

    /**
     * @var CriticalSection
     */
    protected $criticalSection;

    /**
     * ChannelEventHandler constructor.
     * @param LoggerInterface $logger
     * @param DataController $dataController
     * @param CacheItemPoolInterface $cache
     * @param NormalizedObjectFactory $normalizedObjectFactory
     */
    public function __construct(LoggerInterface $logger,
                                DataController $dataController,
                                CacheItemPoolInterface $cache,
                                NormalizedObjectFactory $normalizedObjectFactory,
                                CriticalSection $criticalSection)
    {
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->cache = $cache;
        $this->normalizedObjectFactory = $normalizedObjectFactory;
        $this->criticalSection = $criticalSection;
    }

    public static function getSubscribedEvents()
    {
        return [
            // If a user is added to a channel, we need to clear the cached channel
            UserAddedToChannelEvent::backgroundEventName() => 'channelCacheUpdate',
            // Any filesystem stuff for a channel has to update the cache
            FSAddFileEvent::backgroundEventName() => 'channelCacheUpdate',
            FSCreateFolderEvent::backgroundEventName() => 'channelCacheUpdate',
            FSUpdateEvent::backgroundEventName() => 'channelCacheUpdate',
            FSDeleteEvent::backgroundEventName() => 'channelCacheUpdate',
        ];
    }

    /**
     * @param RtmInterface|ASEvent $event
     */
    public function channelCacheUpdate(RtmInterface $event)
    {

        $this->logger->debug(__FUNCTION__ .  ' : Checking for cache update for event ' . $event::eventName());

        $channelId = $event->getAssociatedChannelId();

        // ensure critical section (prevent concurrency on cache renews for the same channel)
        $sessionId = microtime(true);
        $sessionKey = "CHANNEL_OBJECT_CACHE_RENEW_$channelId";

        try {
            $this->criticalSection->execute(function () use ($channelId) {

                $channel = $this->dataController->getChannelById($channelId);
                if ($channel === null) {
                    $this->logger->debug("Channel $channelId not found for update");
                    return;
                }

                $this->updateCachedChannel($channel, null, false);
                $this->updateCachedChannel($channel, null, true);

                foreach ($this->dataController->getUsersForChannel($channelId) as $userRecord) {
                    $this->logger->debug("Channel " . $channel->getName() . " update for " . $userRecord['display_name']);
                    $this->updateCachedChannel($channel, User::withDBData($userRecord), false);
                    $this->updateCachedChannel($channel, User::withDBData($userRecord), true);
                }
            }, $sessionKey, $sessionId, 0);
        } catch (Throwable $e) {
            $this->logger->error("Blowing up cache for channel $channelId due to a previous error");
            $this->blowUpCachedChannel($channelId);
        }
    }

    /**
     * @param Channel $channel
     * @param User|null $user
     * @param bool $abbreviated
     * @throws DatabaseException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     */
    private function updateCachedChannel(Channel $channel, ?User $user, bool $abbreviated)
    {
        $key = 'normalized_channel.' . $channel->getId();
        $key .= '.' . ($user ?  $user->getId() : '');
        $key .= $abbreviated ? '.abbreviated' : '';

        $cacheItem = $this->cache->getItem($key);
        if ($cacheItem->isHit()) {
            $this->logger->info(__FUNCTION__ . " : Updating cached channel key " . $key );

            $normalizedChannel = $this->normalizedObjectFactory->getCacheableChannelData($channel, $user, $abbreviated);
            $cacheItem->set(serialize($normalizedChannel));
            $cacheItem->expiresAfter(3 * 24 * 3600); // Cache for a day

            $this->cache->save($cacheItem);
        }

    }

    private function blowUpCachedChannel(int $channelId)
    {
        $keys = [
            "normalized_channel.$channelId",
            "normalized_channel.$channelId..abbreviated",
        ];

        // clear for all users
        foreach ($this->dataController->getUsersForChannel($channelId) as $userRecord) {
            $keys[] = "normalized_channel.$channelId.{$userRecord['user_id']}";
            $keys[] = "normalized_channel.$channelId.{$userRecord['user_id']}.abbreviated";
        }

        foreach ($keys as $key) {
            $cacheItem = $this->cache->getItem($key);
            if ($cacheItem->isHit()) {
                $this->cache->deleteItem($key);
            }
        }

    }

}