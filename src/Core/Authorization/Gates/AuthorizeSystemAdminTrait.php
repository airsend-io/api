<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Authorization\Gates;

use CodeLathe\Core\Objects\User;

trait AuthorizeSystemAdminTrait
{

    public function before(User $user, $resource): ?bool
    {
        // if user is a system admin, give total access
        if (User::isServiceAdmin($user->getId())) {
            return true;
        }
        return null;
    }

}