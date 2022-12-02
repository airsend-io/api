<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class TeamOpNotAMemberException extends ASException
{

    public function __construct(int $userId, int $teamId)
    {
        parent::__construct("User `$userId` is not a member of team `$teamId`");
    }

}