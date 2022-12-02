<?php  declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\User;

class UserUpdatedEvent extends  ASEvent
{
    public const NAME = 'user.update';

    protected $initialUser;

    protected $updatedUser;


    public function __construct(User $initialUser, User $updatedUser)
    {
        $this->initialUser = $initialUser;
        $this->updatedUser = $updatedUser;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName (): string
    {
        return self::NAME;
    }

    public function getUserId(): int
    {
        return $this->updatedUser->getId();
    }

    public function getUserAfterUpdate() : User
    {
        return $this->updatedUser;
    }

    public function getUserBeforeUpdate() : User
    {
        return $this->initialUser;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return $this->updatedUser->getArray();
    }

}