<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;

/**
 * This event is for tracking User login event
 */

class UserUpdatedInChannelEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'user_updated.channel';

    protected $oldChannelUser;
    protected $newChannelUser;

    public function __construct(ChannelUser $oldChannelUser, ChannelUser $newChannelUser)
    {
        $this->oldChannelUser = $oldChannelUser;
        $this->newChannelUser = $newChannelUser;
    }

    /**
     * Get the ID of channel the user was added to
     * @return int
     */
    public function getOldChannelUser():ChannelUser
    {
        return $this->oldChannelUser;
    }

    /**
     * Get the payload channel user object
     * @return ChannelUser
     */
    public function newChannelUser():ChannelUser
    {
        return $this->newChannelUser;
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
        $payload = array();
        $payload['old_channel_user'] = $this->oldChannelUser->getArray();
        $payload['new_channel_user'] = $this->newChannelUser->getArray();
        return $payload;
    }
}
