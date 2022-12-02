<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Authorization\Gates;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\MemoizingException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Memoizer;
use Exception;

class ChannelGate extends AbstractGate
{

    use AuthorizeSystemAdminTrait;

    protected $dataController;

    public function __construct(DataController $dataController)
    {
        $this->dataController = $dataController;
    }

    /**
     * Memoized wrapper to get the ChannelUser object for this user/channel
     * @param User $user
     * @param Channel $channel
     * @return ChannelUser|null
     * @throws MemoizingException
     */
    protected function findChannelUser(User $user, Channel $channel): ?ChannelUser
    {
        return Memoizer::memoized([$this->dataController, 'getUserChannel'])($channel->getId(), $user->getId());
    }

    protected function isPublicUser(User $user): bool
    {
        return $user->getUserRole() === User::USER_ROLE_PUBLIC;
    }

    public function transfer(User $user, Channel $channel): bool
    {

        // have to be channel's owner to transfer ownership
        if ($channel->getOwnedBy() === $user->getId()) {
            return true;
        }

        return false;

    }

    public function getImage(User $user, Channel $channel): bool
    {

        // if the user is the public user, and made so far, it must have access
        if ($this->isPublicUser($user)) {
            return true;
        }

        // if the channel is open for team join, any team member must be able to see the channel logo
        if ($channel->isOpenForTeamJoin()) {
            return $this->dataController->isTeamMember($channel->getTeamId(), $user->getId());
        }

        // if not, only show it for the channel members and team managers
        return $this->findChannelUser($user, $channel) instanceof ChannelUser || $this->managerOnChannelOrTeam($user, $channel);

    }

    protected function managerOnChannelOrTeam(User $user, Channel $channel): bool
    {
        $channelUser = $this->findChannelUser($user, $channel);

        // if the user is manager+ on the channel, allow
        if ($channelUser instanceof ChannelUser && $channelUser->getUserRole() >= ChannelUser::CHANNEL_USER_ROLE_MANAGER) {
            return true;
        }

        // if the channel is part of a team and the user is manager+ on the team, allow
        if ($channel->getTeamId()) {
            $teamUser = $this->dataController->getTeamUser($user->getId(), $channel->getTeamId());
            if ($teamUser instanceof TeamUser && $teamUser->getUserRole() >= TeamUser::TEAM_USER_ROLE_MANAGER) {
                return true;
            }
        }

        // not allowed so far, deny...
        return false;
    }

    public function invite(User $user, Channel $channel): bool
    {
        return $this->managerOnChannelOrTeam($user, $channel);
    }

    public function kick(User $user, Channel $channel): bool
    {
        return $this->managerOnChannelOrTeam($user, $channel);
    }

}