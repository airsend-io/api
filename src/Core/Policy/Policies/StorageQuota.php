<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy\Policies;

class StorageQuota extends AbstractPolicy
{

    public static function getKey(): string
    {
        return 'STORAGE_QUOTA_IN_GB';
    }

    public function getDescription(): string
    {
        return 'Storage quota for the team in GB';
    }

    public function getType(): string
    {
        return 'float';
    }

    public static function getDefault()
    {
        return 100; // keeping it as 100 for now
    }
}