<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\UserCode;

class PasswordRecoveredEvent extends  ASEvent
{
    const NAME = 'password.recovered';

    protected $userCode;

    public function __construct(UserCode $userCode)
    {
        $this->userCode = $userCode;
    }

    public function getUserCode(): UserCode
    {
        return $this->userCode;
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
    function getPayloadArray (): array
    {
        return $this->userCode->getArray();
    }
}