<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Message;

/**
 * This event is for tracking User login event
 */

class MeetingInviteEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'call.invite';

    protected $callHash;
    protected $fromId;
    protected $toId;
    protected $channelId;

    public function __construct(string $callHash, int $fromId, int $toId, int $channelId)
    {
        $this->callHash = $callHash;
        $this->fromId = $fromId;
        $this->toId = $toId;
        $this->channelId = $channelId;
    }

    public function getToUserId(): int
    {
        return $this->toId;
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
    public function getPayloadArray(): array
    {
        return [
            'call_hash' => $this->callHash,
            'from_user_id' => $this->fromId,
            'to_user_id' => $this->toId,
            'channel_id' => $this->channelId
        ];

    }

    public function getCallHash(): string
    {
        return $this->callHash;
    }
}

