<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking User login event
 */

class UserProfileUpdateEvent extends  ASEvent implements RtmInterface
{
    const NAME = 'user.profileUpdate';

    protected $user;
    protected $channelId;

    public function __construct(User $user, int $channelId)
    {
        $this->channelId = $channelId;
        $this->user = $user;
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
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
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
        $payload = array('channelid' => $this->channelId, 'user' => $this->user->getArray());

        return $payload;
    }
}

