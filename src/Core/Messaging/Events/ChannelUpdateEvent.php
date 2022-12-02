<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking Channel Update Event
 */

class ChannelUpdateEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'channel.update';

    protected $channel;

    protected $user;

    public function __construct(Channel $channel, ?User $user = null, bool $selfOnly = false)
    {
        $this->channel = $channel;
        $this->user = $user;
        $this->selfNotificationOnly = $selfOnly;
    }

    public function channel():Channel
    {
        return $this->channel;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId(): int
    {
        return (int) $this->channel->getId();
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
        return $this->channel->getArray();
    }


}

