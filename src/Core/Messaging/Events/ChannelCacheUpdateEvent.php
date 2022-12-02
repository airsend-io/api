<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


/**
 * This event is for updating cached channel
 */

class ChannelCacheUpdateEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'channel.cacheUpdate';

    protected $channelId;

    public function __construct(int $channelId)
    {
        $this->channelId = $channelId;
    }


    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId(): int
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
        return ['channel_id' => $this->channelId] ;
    }
}

