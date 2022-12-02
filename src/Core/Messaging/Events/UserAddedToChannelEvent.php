<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;

/**
 * This event is for tracking User login event
 */

class UserAddedToChannelEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'user_added.channel';

    protected $channelId;

    /**
     * @var ChannelUser
     */
    protected $channelUser;

    public function __construct(int $channelId, ChannelUser $channelUser)
    {
        $this->channelId = $channelId;
        $this->channelUser = $channelUser;
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
     * @return ChannelUser
     */
    public function channelUser():ChannelUser
    {
        return $this->channelUser;
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
        return $this->channelUser->getArray();
    }
}

