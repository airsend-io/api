<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Utility\ContainerFacade;

/**
 * This event is for tracking User login event
 */

class ChannelCreatedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'channel.create';

    protected $channel;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    public function channel():Channel
    {
        return $this->channel;
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

