<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Team;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelAuthorizationException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidTeamSettingValueException;
use CodeLathe\Core\Exception\TeamOpAlreadyMemberException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\TeamOpNotAMemberException;
use CodeLathe\Core\Exception\TeamOpUnauthorizedException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnsupportedTeamSettingException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\TeamAddMemberEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveMemberEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateMemberEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Policy\Policies\TeamAnnouncements;
use CodeLathe\Core\Policy\Policies\TeamTagColor;
use CodeLathe\Core\Policy\PolicyManager;
use CodeLathe\Service\Storage\Exceptions\DestinyPathAlreadyExistsException;
use CodeLathe\Service\Storage\Exceptions\NotAFolderException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;

class TeamOperations
{

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PolicyManager
     */
    protected $policyManager;

    /**
     * @var FileOperations
     */
    protected $fileOperations;

    /**
     * @var ChannelOperations
     */
    protected $channelOperations;
    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * TeamManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param PolicyManager $policyManager
     * @param FileOperations $fileOperations
     * @param ChannelOperations $channelOperations
     * @param EventManager $eventManager
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                PolicyManager $policyManager,
                                FileOperations $fileOperations,
                                ChannelOperations $channelOperations,
                                EventManager $eventManager)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->policyManager = $policyManager;
        $this->fileOperations = $fileOperations;
        $this->channelOperations = $channelOperations;
        $this->eventManager = $eventManager;
    }

    /**
     * @param string $name
     * @param User $owner
     * @param string|null $colorTag
     * @return Team
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     */
    public function createTeam(string $name, User $owner, ?string $colorTag = null): Team
    {

        // TODO - Validate if the owner can create a team (it must have a valid credit card)

        $team = Team::create($name, Team::TEAM_TYPE_STANDARD, $owner->getId());

        $this->dataController->beginTransaction();

        try {

            // create the team
            $this->dataController->createTeam($team);

            // create team FS roots
            $this->fileOperations->onNewTeam($team, $owner);

            // set the team color tag
            if ($colorTag !== null) {
                $this->policyManager->setPolicyValue($team, TeamTagColor::class, $colorTag);
            }

        } catch (DestinyPathAlreadyExistsException | NotFoundException | NotAFolderException | DatabaseException $e) {
            $this->dataController->rollback();
            throw new TeamOpException($e->getMessage());
        }

        $this->dataController->commit();

        return $team;
    }

    /**
     * @param Team $team
     * @param User $inviter
     * @param User $invitee
     * @throws DatabaseException
     * @throws TeamOpAlreadyMemberException
     * @throws TeamOpUnauthorizedException
     */
    public function addUserToTeam(Team $team, User $inviter, User $invitee): void
    {
        // check if the inviter has the permission to add users to the team
        if ($inviter->cannot('invite', $team)) {
            throw new TeamOpUnauthorizedException($inviter->getId(), $team->getId(), 'invite');
        }

        // TODO - check if there is any license blocker to add users to a team

        // check if the user is not a current member of the team
        $teamUser = $this->dataController->getTeamUser($invitee->getId(), $team->getId());
        if ($teamUser !== null) {
            throw new TeamOpAlreadyMemberException($invitee->getId(), $team->getId());
        }

        $teamUser = TeamUser::create($team->getId(), $invitee->getId(), TeamUser::TEAM_USER_ROLE_MEMBER, $inviter->getId());
        $this->dataController->addTeamUser($teamUser);

        $event = new TeamAddMemberEvent($team, $invitee);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Team $team
     * @param User $kicker
     * @param User $kicked
     * @throws DatabaseException
     * @throws TeamOpNotAMemberException
     * @throws TeamOpUnauthorizedException
     */
    public function kick(Team $team, User $kicker, User $kicked): void
    {
        // check if the kicker have the permission to kick users from the team
        if ($kicker->cannot('kick', $team)) {
            throw new TeamOpUnauthorizedException($kicker->getId(), $team->getId(), 'kick');
        }

        // kicked must be a member of the team
        $teamUser = $this->dataController->getTeamUser($kicked->getId(), $team->getId());
        if ($teamUser === null) {
            throw new TeamOpNotAMemberException($kicked->getId(), $team->getId());
        }

        // kicked can't be owner of the team
        if ($teamUser->getUserRole() >= TeamUser::TEAM_USER_ROLE_OWNER) {
            throw new TeamOpUnauthorizedException($kicker->getId(), $team->getId(), 'kick');
        }

        $this->dataController->transaction(function () use ($team, $kicked, $kicker) {

            // find all channels from team, where the kicked user is a member
            $channels = $this->dataController->findTeamChannelsForUser($team->getId(), $kicked->getId());

            // kick the user from all channels that he's member on the team
            foreach ($channels as $channel) {
                try {
                    $this->channelOperations->removeUserFromChannel($kicked, $kicker, $channel, false, true);
                } catch (ChannelAuthorizationException | ChannelOpException | ChatOpException | DatabaseException $e) {
                    throw new TeamOpException("Failed to kick the user from channel `{$channel->getId()}` -> " . get_class($e) . ": {$e->getMessage()}");
                }
            }
            $this->dataController->dropTeamUser($team->getId(), $kicked->getId());

        });

        $event = new TeamRemoveMemberEvent($team, $kicked, $teamUser);
        $this->eventManager->publishEvent($event);

    }

    /**
     * @param User $user
     * @param bool $includeSelfTeam
     * @return Team[]
     * @throws DatabaseException
     */
    public function list(User $user, bool $includeSelfTeam = false): array
    {
        $teams = $this->dataController->getTeamsForUser($user->getId());
        if (!$includeSelfTeam) {
            $teams = array_filter($teams, function (Team $team) {
                return !$team->isSelfTeam();
            });
            $teams = array_values($teams); // just reset the array keys
        }
        return $teams;
    }

    protected function availableSettings(): array
    {
        return [
            'color_tag' => [
                'policyClass' => TeamTagColor::class,
                'validator' => function($value) {
                    return ((bool) preg_match('/^[0-9A-Fa-f]{8}$/', $value)) || empty($value);
                },
            ],
            'announcements' => [
                'policyClass' => TeamAnnouncements::class,
                'validator' => function($value) {
                    return strlen((string)$value) < 256;
                },
            ]
        ];
    }

    protected function policyClass(string $setting): ?string
    {
        $settingConfig = $this->availableSettings()[$setting] ?? null;

        return $settingConfig['policyClass'] ?? null;
    }

    protected function settingValidator(string $setting): ?callable
    {

        $settingConfig = $this->availableSettings()[$setting] ?? null;

        return $settingConfig['validator'] ?? null;
    }

    /**
     * @param string $setting
     * @param string $value
     * @throws InvalidTeamSettingValueException
     * @throws UnsupportedTeamSettingException
     */
    public function validateSetting(string $setting, string $value): void
    {
        $validator = $this->settingValidator($setting);

        if ($validator === null) {
            throw new UnsupportedTeamSettingException($setting);
        }

        if (!$validator($value)) {
            throw new InvalidTeamSettingValueException($setting, $value);
        }
    }

    /**
     * @param Team $team
     * @param string $setting
     * @param string $value
     * @throws UnknownPolicyEntityException
     */
    public function set(Team $team, string $setting, string $value)
    {
        $this->policyManager->setPolicyValue($team, $this->policyClass($setting), $value);
    }

    /**
     * @param User $loggedUser
     * @param Team $team
     * @param int $userId
     * @param int $role
     * @throws DatabaseException
     * @throws TeamOpUnauthorizedException
     */
    public function setTeamUserRole(User $loggedUser, Team $team, int $userId, int $role): void
    {

        if ($loggedUser->cannot('manage', $team)) {
            throw new TeamOpUnauthorizedException($loggedUser->getId(), $team->getId(), 'change role');
        }

        $this->dataController->setTeamUserRole($team->getId(), $userId, $role);

        // trigger the team member update event
        $member = $this->dataController->getUserById($userId);
        $event = new TeamUpdateMemberEvent($team, $member);
        $this->eventManager->publishEvent($event);

    }

    public function setOpenTeamStatusForChannel(User $loggedUser, Channel $channel, bool $open)
    {
        $channel->setOpenForTeamJoin($open);
        $this->dataController->updateChannel($channel);

        // trigger the event
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());

        $event = new TeamUpdateChannelEvent($team, $channel);
        $this->eventManager->publishEvent($event);

    }

}
