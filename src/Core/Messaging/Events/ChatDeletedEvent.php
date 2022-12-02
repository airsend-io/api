<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Message;

/**
 * This event is for tracking User login event
 */

class ChatDeletedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'chat.deleteMessage';


    protected  $messageId;
    protected $channelId;

    public function __construct(int $messageId, int $channelId)
    {
        $this->messageId = $messageId;
        $this->channelId = $channelId;
    }

    /**
     * Get the stored message object
     * @return Message
     */
    public function getMessageId(): int
    {
        return $this->messageId;
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
    function getPayloadArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'channel_id' => $this->channelId
        ];
    }
}

