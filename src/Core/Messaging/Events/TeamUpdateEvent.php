<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;

/**
 * This event is for tracking team updates
 */
class TeamUpdateEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'team.update';

    /**
     * @var Team
     */
    protected $team;

    public function __construct(Team $team)
    {
        $this->team = $team;
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
            'team_id' => $this->team->getId()
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

}