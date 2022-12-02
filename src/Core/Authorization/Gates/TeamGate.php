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
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Memoizer;
use Exception;

class TeamGate extends AbstractGate
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
     * @param Team $team
     * @return ChannelUser|null
     * @throws MemoizingException
     */
    protected function findTeamUser(User $user, Team $team): ?TeamUser
    {
        return Memoizer::memoized([$this->dataController, 'getTeamUser'])($user->getId(), $team->getId());
    }

    protected function atLeast(int $role, User $user, Team $team): bool
    {

        $teamUser = $this->findTeamUser($user, $team);

        // user must be member of the team
        if (!($teamUser instanceof TeamUser)) {
            return false;
        }

        // user must be at least manager of the team to be able to add someone to it
        if ($teamUser->getUserRole() < $role) {
            return false;
        }

        return true;

    }

    protected function atLeastManager(User $user, Team $team): bool
    {
        return $this->atLeast(TeamUser::TEAM_USER_ROLE_MANAGER, $user, $team);
    }

    protected function atLeastMember(User $user, Team $team): bool
    {
        return $this->atLeast(TeamUser::TEAM_USER_ROLE_MEMBER, $user, $team);
    }

    public function invite(User $user, Team $team): bool
    {
        return $this->atLeastManager($user, $team);
    }

    public function kick(User $user, Team $team): bool
    {
        return $this->atLeastManager($user, $team);
    }

    public function manage(User $user, Team $team): bool
    {
        return $this->atLeastManager($user, $team);
    }

    public function createChannel(User $user, Team $team): bool
    {
        return $this->atLeastMember($user, $team);
    }

    public function listMembers(User $user, Team $team): bool
    {
        return $this->atLeastMember($user, $team);
    }

    public function setChannelOwnership(User $user, Team $team): bool
    {
        return $this->atLeastManager($user, $team);
    }

    public function openChannelForTeamJoin(User $user, Team $team): bool
    {
        return $this->atLeastManager($user, $team);
    }

}