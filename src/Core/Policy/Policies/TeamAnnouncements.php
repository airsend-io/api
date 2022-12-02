<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Policy\Policies;

class TeamAnnouncements extends AbstractPolicy
{

    public static function getKey(): string
    {
        return 'TEAM_ANNOUNCEMENTS';
    }

    public function getDescription(): string
    {
        return 'The announcements md for a team.';
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