<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Message;

/**
 * This event is for tracking User login event
 */

class MeetingInviteAcceptEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'call.invite.accept';

    protected  $callHash;
    protected $toId;
    protected $accept;

    public function __construct(string $callHash, int $toId, bool $accept)
    {
        $this->callHash = $callHash;
        $this->toId = $toId;
        $this->accept = $accept;
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
        return [
            'call_hash' => $this->callHash,
            'accept' => $this->accept
        ];

    }
}

