<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking User login event
 */

class UserCreatedEvent extends  ASEvent
{
    const NAME = 'user.created';

    protected $user;

    /**
     * @var int|null
     */
    protected $fromPublicChannelId;

    /**
     * @var string|null
     */
    protected $fromPublicChannelHash;

    public function __construct(User $user, ?int $fromPublicChannelId = null, ?string $fromPublicChannelHash = null)
    {
        $this->user = $user;
        $this->fromPublicChannelId = $fromPublicChannelId;
        $this->fromPublicChannelHash = $fromPublicChannelHash;
    }

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

    public function getFromPublicChannelId(): ?int
    {
        return $this->fromPublicChannelId ?? null;
    }

    public function getFromPublicChannelHash(): ?string
    {
        return $this->fromPublicChannelHash ?? null;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return $this->user->getArray();
    }
}

