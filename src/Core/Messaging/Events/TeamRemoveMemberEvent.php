<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking team member removal
 */

class TeamRemoveMemberEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'team.remove_member';

    /**
     * @var Team
     */
    protected $team;

    /**
     * @var User
     */
    protected $member;

    /**
     * @var TeamUser
     */
    protected $teamUser;

    public function __construct(Team $team, User $member, TeamUser $teamUser)
    {
        $this->team = $team;
        $this->member = $member;
        $this->teamUser = $teamUser;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName (): string
    {
        return self::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return [
            'team_id' => $this->team->getId(),
            'removed_member' => $this->member,
        ];
    }

    public function getAssociatedChannelId(): int
    {
        return 0;
    }

    /**
     * @return Team
     */
    public function getTeam(): Team
    {
        return $this->team;
    }

    /**
     * @return User
     */
    public function getMember(): User
    {
        return $this->member;
    }

    /**
     * @return TeamUser
     */
    public function getTeamUser(): TeamUser
    {
        return $this->teamUser;
    }
}