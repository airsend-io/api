<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Message;

/**
 * This event is for tracking read status updates
 */

class ChannelReadStatusUpdateEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'channel.updateReadStatus';

    protected $channelId;
    protected $userId;
    protected $messageId;

    /**
     * @var int
     */
    protected $userUnreadCount;

    /**
     * @var int
     */
    protected $channelUnreadCount;


    /**
     * @param int $channelId
     * @param int $userId
     * @param int $messageId New read watermark for the user
     * @param int $userUnreadCount
     * @param int $channelUnreadCount
     */
    public function __construct(int $channelId, int $userId, int $messageId, int $userUnreadCount, int $channelUnreadCount)
    {
        $this->channelId = $channelId;
        $this->userId = $userId;
        $this->messageId = $messageId;
        $this->userUnreadCount = $userUnreadCount;
        $this->channelUnreadCount = $channelUnreadCount;
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
            'user_id' => $this->userId,
            'channel_id' => $this->channelId,
            'read_watermark_id' => $this->messageId,
            'unread_count' => $this->channelUnreadCount,
            'total_unread_count' => $this->userUnreadCount,
        ];

    }
}

