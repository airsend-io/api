<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Message;

/**
 * This event is for tracking User login event
 */

class LockAcquireEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'lock.acquire';

    protected  $lockId;
    protected $acquirerId;
    protected $channelId;
    protected $pathInChannel;

    public function __construct(int $lockId, int $acquirerUserId, string $path, int $channelId = -1)
    {
        $this->lockId = $lockId;
        $this->acquirerId = $acquirerUserId;
        $this->channelId = $channelId;
        $this->pathInChannel = $path;
    }


    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId() : int
    {
        return (int) $this->channelId;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName(): string
    {
        return self::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray(): array
    {
        $payload = [];
        $payload['lock_id'] = $this->lockId;
        $payload['channel_id'] = $this->channelId;
        $payload['user_id'] = $this->acquirerId;
        $payload['path'] = $this->pathInChannel;

        return $payload;

    }
}

