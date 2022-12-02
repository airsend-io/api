<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class TeamOpUnauthorizedException extends ASException
{

    public function __construct(int $userId, int $teamId, string $action)
    {
        parent::__construct("User `$userId` is not authorized to $action on team `$teamId`");
    }

}