<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Managers\Realtime\RtmToken;

/**
 * This event is for tracking User login event
 */

class PingEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'system.ping';

    protected  $rtmToken;
    protected  $pingToken;

    public function __construct(RtmToken $rtmToken, string $pingToken)
    {
        $this->rtmToken = $rtmToken;
        $this->pingToken = $pingToken;
    }


    public function rtmToken():RtmToken
    {
        return $this->rtmToken;
    }

    public function pingToken():string
    {
        return $this->pingToken;
    }


    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId() : int
    {
        // This is a global event. Not specific to a channel.
        return 0;
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
        return [ 'rtm_token' => $this->rtmToken->getArray(), 'ping_token' => $this->pingToken ];
    }
}

