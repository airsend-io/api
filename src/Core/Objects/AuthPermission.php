<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


class AuthPermission
{
    const CREATE = "create";
    const READ = "read";
    const UPDATE = "update";
    const DELETE = "delete";

    protected $permissions;

    public function __construct($perms)
    {
        $this->permissions = $perms;
    }

    private function check($val): bool
    {
        if (isset($this->permissions[$val])) {
            return $this->permissions[$val];
        }
        return false;
    }

    public function allowRead(): bool
    {
        return $this->check(self::READ);
    }

    public function allowCreate(): bool
    {
        return $this->check(self::CREATE);
    }

    public function allowUpdate(): bool
    {
        return $this->check(self::UPDATE);
    }

    public function allowDelete(): bool
    {
        return $this->check(self::DELETE);
    }
}