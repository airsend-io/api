<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObject;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;

/**
 * This event is for tracking Channel Update Event
 */

class ChannelJoinUpdateEvent extends  ASEvent implements RtmInterface
{

    protected $channel;

    protected $user;

    /**
     * @var NormalizedObject[]
     */
    protected $pendingUsers;

    /**
     * @var bool
     */
    protected $notify;

    /**
     * AbstractChannelJoinEvent constructor.
     * @param Channel $channel
     * @param User $user
     * @param NormalizedObject[] $pending
     * @param bool $notify
     */
    public function __construct(Channel $channel, User $user, array $pending, bool $notify = false)
    {
        $this->channel = $channel;
        $this->user = $user;
        $this->pendingUsers = $pending;
        $this->notify = $notify;
    }

    static function eventName(): string
    {
        return 'channel.join.update';
    }

    public function channel():Channel
    {
        return $this->channel;
    }

    public function shouldNotify(): bool
    {
        return $this->notify;
    }

    public function user(): User
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
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray(): array
    {

        return [
            'channel_id' => $this->channel()->getId(),
            'user_id' => $this->user()->getId(),
            'pending_members' => $this->pendingUsers,
        ];
    }

}

