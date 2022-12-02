<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

/**
 * Class ResourcePermission
 * @package CodeLathe\Core\Objects
 * @deprecated replaced by
 */
class ResourcePermission
{
    const LIST = "list";
    const READ = "read";
    const WRITE = "write";
    const DELETE = "delete";
    const OWNER = "owner";

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

    public function allowList(): bool
    {
        return $this->check(self::LIST);
    }

    public function allowRead(): bool
    {
        return $this->check(self::READ);
    }

    public function allowWrite(): bool
    {
        return $this->check(self::WRITE);
    }

    public function allowDelete(): bool
    {
        return $this->check(self::DELETE);
    }

    public function allowOwner(): bool
    {
        return $this->check(self::OWNER);
    }
}