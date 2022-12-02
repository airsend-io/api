<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking User login event
 */

class UserRemovedFromChannelEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'user_removed.channel';

    protected $channelId;
    protected $userRemovedId;
    protected $removedById;

    public function __construct(int $channelId, int $userRemovedId, int $removedByUserId)
    {
        $this->channelId = $channelId;
        $this->userRemovedId = $userRemovedId;
        $this->removedById = $removedByUserId;
    }

    /**
     * Get the ID of channel the user was added to
     * @return int
     */
    public function channelId():int
    {
        return $this->channelId;
    }

    /**
     * Get the payload channel user object
     * @return User
     */
    public function userRemovedId(): int
    {
        return $this->userRemovedId;
    }

    public function removedById(): int
    {
        return $this->removedById;
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId(): int
    {
        return (int)$this->channelId;
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
        $payload['channel_id'] = $this->channelId;
        $payload['user_removed_id'] = $this->userRemovedId;
        $payload['removed_by_user_id'] = $this->removedById;
        return $payload;
    }
}

