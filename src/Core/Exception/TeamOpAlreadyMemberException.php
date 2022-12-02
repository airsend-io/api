<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class TeamOpAlreadyMemberException extends ASException
{

    public function __construct(int $userId, int $teamId)
    {
        parent::__construct("User `$userId` is already part of the team `$teamId`");
    }

}