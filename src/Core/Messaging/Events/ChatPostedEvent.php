<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Message;

/**
 * This event is for tracking User login event
 */

class ChatPostedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'chat.postMessage';

    protected  $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the stored message object
     * @return Message
     */
    public function getMessage(): Message
    {
        return $this->message;
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId() : int
    {
        return (int) $this->message->getChannelId();
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
        return $this->message->getArray();

    }
}

