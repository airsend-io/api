<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking User login event
 */

abstract class AbstractTeamMemberEvent extends ASEvent implements RtmInterface
{
    /**
     * @var Team
     */
    protected $team;

    /**
     * @var User
     */
    protected $member;

    public function __construct(Team $team, User $member)
    {
        $this->team = $team;
        $this->member = $member;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray(): array
    {
        return [
            'team_id' => $this->team->getId(),
            'new_member' => $this->member,
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
}