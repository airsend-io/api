<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy\Policies;

class TeamTagColor extends AbstractPolicy
{

    public static function getKey(): string
    {
        return 'TEAM_TAG_COLOR';
    }

    public function getDescription(): string
    {
        return 'Tag color for a team';
    }

    public function getType(): string
    {
        return 'string';
    }

    public static function getDefault()
    {
        return '';
    }
}