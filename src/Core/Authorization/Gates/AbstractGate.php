<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Authorization\Gates;

use CodeLathe\Core\Objects\User;

abstract class AbstractGate
{

    public function before(User $user, $resource): ?bool
    {
        return null;
    }

}