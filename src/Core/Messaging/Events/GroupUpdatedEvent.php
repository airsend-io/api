<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


/**
 * This event is for tracking User login event
 */

class GroupUpdatedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'channel.group.update';

    protected $groupId;
    protected $userId;

    public function __construct(int $userId, int $groupId)
    {
        $this->groupId = $groupId;
        $this->userId = $userId;
        // This will be delivered to just one user (The one who created the channel)
        $this->setSelfOnly(true);
    }

    public function groupId():int
    {
        return $this->groupId;
    }

    public function userId():int
    {
        return $this->userId;
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId(): int
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
        return  array('group_id'=>$this->groupId);
    }
}

