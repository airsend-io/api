<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;

/**
 * This event is for tracking User login event
 */

abstract class AbstractTeamChannelEvent extends ASEvent implements RtmInterface
{

    /**
     * @var Team
     */
    protected $team;

    /**
     * @var Channel
     */
    protected $channel;

    public function __construct(Team $team, Channel $channel)
    {

        $this->team = $team;
        $this->channel = $channel;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return [
            'team_id' => $this->team->getId(),
            'new_channel' => $this->channel,
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
     * @return Channel
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }
}