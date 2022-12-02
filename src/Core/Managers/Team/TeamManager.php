<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Team;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\InvalidTeamSettingValueException;
use CodeLathe\Core\Exception\NoTeamSeatsAvailableException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\TeamOpAlreadyMemberException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\TeamOpNotAMemberException;
use CodeLathe\Core\Exception\TeamOpUnauthorizedException;
use CodeLathe\Core\Exception\UnconfiguredPolicyException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException as UnknownPolicyEntityExceptionAlias;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Exception\UnsupportedPolicyTypeException;
use CodeLathe\Core\Exception\UnsupportedTeamSettingException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\TeamUpdateEvent;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class TeamManager extends ManagerBase
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
     * @var TeamOperations
     */
    protected $teamOps;
    /**
     * @var NormalizedObjectFactory
     */
    private $normalizedObjectFactory;
    /**
     * @var FileOperations
     */
    private $fileOperations;
    /**
     * @var RtmOperations
     */
    private $rtmOperations;
    /**
     * @var ChannelOperations
     */
    private $channelOps;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * TeamManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param TeamOperations $teamOps
     * @param NormalizedObjectFactory $normalizedObjectFactory
     * @param FileOperations $fileOperations
     * @param RtmOperations $rtmOperations
     * @param ChannelOperations $channelOps
     * @param EventManager $eventManager
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                TeamOperations $teamOps,
                                NormalizedObjectFactory $normalizedObjectFactory,
                                FileOperations $fileOperations,
                                RtmOperations $rtmOperations,
                                ChannelOperations $channelOps,
                                EventManager $eventManager)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->teamOps = $teamOps;
        $this->normalizedObjectFactory = $normalizedObjectFactory;
        $this->fileOperations = $fileOperations;
        $this->rtmOperations = $rtmOperations;
        $this->channelOps = $channelOps;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws TeamOpException
     * @throws UnconfiguredPolicyException
     * @throws UnknownPolicyEntityExceptionAlias
     * @throws UnsupportedPolicyException
     * @throws UnsupportedPolicyTypeException
     */
    public function create(Request $request, Response $response): Response
    {

        // get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $name = $params['name'] ?? '';
        if (empty($name)) {
            return JsonOutput::error('Team name is required', 422)->write($response);
        }

        // get the color tag parameter
        $colorTag = $params['color_tag'] ?? null;

        // creates the team
        $team = $this->teamOps->createTeam($name, $user, $colorTag);

        // return the success response
        return JsonOutput::success()->withContent('team', $this->normalizedObjectFactory->normalizedObject($team, $user))->write($response);

    }

    public function invite(Request $request, Response $response): Response
    {

        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get team id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // find the team object
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('The team was not found', 404)->write($response);
        }

        // get the invitees
        $invitees = explode(',', $params['users'] ?? '');
        $invitees = array_map('trim', $invitees);
        $invitees = array_filter($invitees, function ($item) {
            return !empty($item);
        });
        if (empty($invitees)) {
            return JsonOutput::error('Invitees list is required', 422)->write($response);
        }

        // get the channels where the user should be added
        $channelIds = explode(',', $params['channels'] ?? '');
        $channelIds = array_map(function ($item) {
            return (int) trim($item);
        }, $channelIds);
        $channelIds = array_filter($channelIds, function ($item) {
            return !empty($item);
        });

        $failedInvites = [];
        foreach ($invitees as $invitee) {
            if (is_numeric($invitee)) {
                $user = $this->dataController->getUserById((int)$invitee);
            } else {
                $user = $this->dataController->getUserByEmail($invitee);
            }

            if (!($user instanceof User)) {
                $failedInvites[] = [
                    'user' => $invitee,
                    'reason' => 'User not found'
                ];
                continue;
            }

            try {
                $this->teamOps->addUserToTeam($team, $loggedUser, $user);
            } catch (TeamOpAlreadyMemberException | TeamOpUnauthorizedException $e) {
                $failedInvites[] = [
                    'user' => $invitee,
                    'reason' => $e->getMessage()
                ];
                continue;
            }

            // add the user to the channels...
            $failedChannels = [];
            foreach ($channelIds as $channelId) {

                // channel must exist
                $channel = $this->dataController->getChannelById((int)$channelId);
                if ($channel === null) {
                    $failedChannels[] = [
                        'channel_id' => $channelId,
                        'reason' => 'Channel not found',
                    ];
                    continue;
                }

                // channel must be part of the team
                if ($channel->getTeamId() !== $teamId) {
                    $failedChannels[] = [
                        'channel_id' => $channelId,
                        'reason' => 'Channel is not part of the team',
                    ];
                    continue;
                }

                // add the user to the team
                try {
                    $addedUser = $this->channelOps->addUserToChannel($channel->getId(), $user->getId(), $loggedUser->getId());
                } catch (ChannelOpException | FSOpException | TeamOpException | UserOpException | InvalidEmailAddressException $e) {
                    $failedChannels[] = [
                        'channel_id' => $channelId,
                        'reason' => $e->getMessage(),
                    ];
                    continue;
                }

                // Raise bot notification
                $this->channelOps->raiseUserAddedBotMessage($loggedUser, [$addedUser], (int)$channelId);

            }
            if (!empty($failedChannels)) {
                $failedInvites[] = [
                    'user' => $invitee,
                    'reason' => 'Failed to add user to channels',
                    'failed_channels' => $failedChannels,
                ];
            }
        }

        // return the success response if all users was added
        if (empty($failedInvites)) {
            return JsonOutput::success(201)->write($response);
        }

        return JsonOutput::error('Failed to add users to team.')->withContent('failed_invites', $failedInvites)->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     */
    public function kick(Request $request, Response $response): Response
    {
        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get team id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // find the team object
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('The team was not found', 404)->write($response);
        }

        // get user_id
        $kickedId = isset($params['user_id']) ? (int)$params['user_id'] : null;
        if ($kickedId === null) {
            return JsonOutput::error('User id is required', 422)->write($response);
        }

        // find the team object
        $kickedUser = $this->dataController->getUserById($kickedId);
        if ($kickedUser === null) {
            return JsonOutput::error('The user was not found', 404)->write($response);
        }

        try {
            $this->teamOps->kick($team, $loggedUser, $kickedUser);
        } catch (TeamOpNotAMemberException $e) {
            return JsonOutput::error($e->getMessage(), 404)->write($response);
        } catch (TeamOpUnauthorizedException $e) {
            return JsonOutput::error($e->getMessage(), 403)->write($response);
        }

        return JsonOutput::success(204)->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     */
    public function list(Request $request, Response $response): Response
    {
        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        // find the teams that user is member of
        $teams = $this->teamOps->list($loggedUser);

        $output = array_map(function (Team $team) use ($loggedUser) {
            return $this->normalizedObjectFactory->normalizedObject($team, $loggedUser, true);
        }, $teams);

        return JsonOutput::success()->withContent('teams', $output)->write($response);
    }

    public function info(Request $request, Response $response): Response
    {
        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // get the team_id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // find team...
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('Team was not found', 404)->write($response);
        }

        $output = $this->normalizedObjectFactory->normalizedObject($team, $loggedUser, false);

        return JsonOutput::success()->withContent('team', $output)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     */
    public function update(Request $request, Response $response): Response
    {
        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get the team_id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // find team...
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('Team was not found', 404)->write($response);
        }

        // check user permissions on the team
        if ($loggedUser->cannot('manage', $team)) {
            return JsonOutput::error('You don\'t have permissions to update this team', 403)->write($response);
        }

        // get the attributes to change
        $name = $params['name'] ?? null;
        if ($name !== null && empty($name)) {
            return JsonOutput::error('Name can\'t be empty.', 422)->write($response);
        }

        // set the info on the team object
        $changed = false;

        // set name
        if ($name !== null) {
            $changed = true;
            $team->setName($name);
        }

        // ... other attributes

        if (!$changed) {
            return JsonOutput::error('Nothing to update', 422)->write($response);
        }

        // update the team
        $this->dataController->updateTeam($team);

        // raise the team.update event
        $event = new TeamUpdateEvent($team);
        $this->eventManager->publishEvent($event);

        $output = $this->normalizedObjectFactory->normalizedObject($team, $loggedUser, false);
        return JsonOutput::success()->withContent('team', $output)->write($response);

    }

    public function members(Request $request, Response $response): Response
    {

        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // get the team_id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // team must exist
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('Team was not found', 404)->write($response);
        }

        // user must have list_members permission
        if ($loggedUser->cannot('listMembers', $team)) {
            return JsonOutput::error('User must be member of the team', 403)->write($response);
        }

        $members = array_map(function ($item) {
            return $this->normalizedObjectFactory->normalizedObject($item);
        }, $this->dataController->getTeamUsers($teamId));

        return JsonOutput::success()->withContent('members', $members)->write($response);
    }

    public function setting(Request $request, Response $response): Response
    {

        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get the team_id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // team must exist
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('Team was not found', 404)->write($response);
        }

        // remove team_id from params, and check other settings
        unset($params['team_id']);

        // validate all settings
        foreach ($params as $setting => $value) {
            try {
                $this->teamOps->validateSetting($setting, $value);
            } catch (InvalidTeamSettingValueException $e) {
                return JsonOutput::error($e->getMessage(), 422)->write($response);
            } catch (UnsupportedTeamSettingException $e) {
                return JsonOutput::error($e->getMessage(), 404)->write($response);
            }
        }

        // if all settings are valid, save them
        foreach ($params as $setting => $value) {
            try {
                $this->teamOps->set($team, $setting, $value);
            } catch (UnknownPolicyEntityExceptionAlias $e) {
                // ser ver error, should be caught on validation
                return JsonOutput::error($e->getMessage(), 500)->write($response);
            }
        }

        // raise the team.update event
        $event = new TeamUpdateEvent($team);
        $this->eventManager->publishEvent($event);

        return JsonOutput::success(201)->write($response);

    }

    public function setRole(Request $request, Response $response): Response
    {

        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get team id (required)
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        if ($teamId === null) {
            return JsonOutput::error('Team id is required', 422)->write($response);
        }

        // find the team object
        $team = $this->dataController->getTeamByTeamId($teamId);
        if ($team === null) {
            return JsonOutput::error('The team was not found', 404)->write($response);
        }

        // get the user id
        $userId = isset($params['user_id']) ? (int)$params['user_id'] : null;
        if ($userId === null) {
            return JsonOutput::error('User id is required', 422)->write($response);
        }

        // check if the user is a member of the team
        if (!$this->dataController->isTeamMember($teamId, $userId)) {
            return JsonOutput::error('The user must be a member of the team', 404)->write($response);
        }

        // get the role
        $role = isset($params['role']) ? (int)$params['role'] : null;
        if ($role === null) {
            return JsonOutput::error('Role is required', 422)->write($response);
        }
        $availableRoles = [
            TeamUser::TEAM_USER_ROLE_MANAGER,
            TeamUser::TEAM_USER_ROLE_MEMBER,
        ];
        if (!in_array($role, $availableRoles)) {
            return JsonOutput::error('Invalid Role', 422)->write($response);
        }

        try {
            $this->teamOps->setTeamUserRole($loggedUser, $team, $userId, $role);
        } catch (TeamOpUnauthorizedException $e) {
            return JsonOutput::error("You're not authorized to change roles on this channel", 403)->write($response);
        }

        return JsonOutput::success(201)->write($response);

    }

    public function setChannelOwner(Request $request, Response $response): Response
    {
        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get the channel_id (required)
        $channelId = isset($params['channel_id']) ? (int)$params['channel_id'] : null;
        if ($channelId === null) {
            return JsonOutput::error('Channel id is required', 422)->write($response);
        }

        // channel must exist
        $channel = $this->dataController->getChannelById($channelId);
        if ($channel === null) {
            return JsonOutput::error('Channel not found', 404)->write($response);
        }

        // channel must be part of a standard team
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if (!($team instanceof Team) || $team->getTeamType() !== Team::TEAM_TYPE_STANDARD) {
            return JsonOutput::error("Invalid channel. Not part of a team.", 404)->write($response);
        }

        // logged user must have permission to change channel ownership
        if ($loggedUser->cannot('setChannelOwnership', $team)) {
            return JsonOutput::error("You do not have authority to change this channel ownership.", 403)->write($response);
        }

        // new owner id is required
        $newOwnerId = isset($params['new_owner_id']) ? (int)$params['new_owner_id'] : null;
        if ($newOwnerId === null) {
            return JsonOutput::error("New owner id is required.", 422)->write($response);
        }

        // new owner must be a valid user, and a member of the team
        $newOwner = $this->dataController->getUserById($newOwnerId);
        if (!($newOwner instanceof User) || !$this->dataController->isTeamMember($team->getId(), $newOwnerId)) {
            return JsonOutput::error("New owner must be a member of the team.", 422)->write($response);
        }

        $this->channelOps->setOwnership($channel, $newOwner);

        return JsonOutput::success(201)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function setChannelOpenStatus(Request $request, Response $response): Response
    {
        // get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // get the channel_id (required)
        $channelId = isset($params['channel_id']) ? (int)$params['channel_id'] : null;
        if ($channelId === null) {
            return JsonOutput::error('Channel id is required', 422)->write($response);
        }

        // channel must exist
        $channel = $this->dataController->getChannelById($channelId);
        if ($channel === null) {
            return JsonOutput::error('Channel not found', 404)->write($response);
        }

        // channel must be part of a standard team
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if (!($team instanceof Team) || $team->getTeamType() !== Team::TEAM_TYPE_STANDARD) {
            return JsonOutput::error("Invalid channel. Not part of a team.", 404)->write($response);
        }

        // logged user must have permission to change channel on team
        if ($loggedUser->cannot('openChannelForTeamJoin', $team)) {
            return JsonOutput::error("You do not have authority to change this channel ownership.", 403)->write($response);
        }

        // get the open status
        $open = isset($params['open']) ? (bool) $params['open'] : null;
        if ($open === null) {
            return JsonOutput::error("The open (boolean) field is required.", 422)->write($response);
        }

        $this->teamOps->setOpenTeamStatusForChannel($loggedUser, $channel, $open);

        return JsonOutput::success(201)->write($response);

    }

    /**
     * @inheritDoc
     */
    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}
