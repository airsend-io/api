<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Channel;

use Carbon\CarbonImmutable;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelAuthorizationException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\MemoizingException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\PhoneOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChannelJoinUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChannelUpdateEvent;
use CodeLathe\Core\Messaging\Events\TeamAddChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveChannelEvent;
use CodeLathe\Core\Messaging\Notification\NodeCommandHandler;
use CodeLathe\Core\Objects\ChannelPath;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\ChannelUserPending;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Serializers\CallbackStream;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Storage\Exceptions\NotAFolderException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class ChannelManager extends ManagerBase
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
     * @var FileOperations
     */
    protected $fOps;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    /**
     * @var UserOperations
     */
    protected $userOps;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    protected $phoneOps;

    protected $globalAuthContext;
    /**
     * @var NodeCommandHandler
     */
    private $nodeCommandHandler;


    /**
     * ChannelManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param ChannelOperations $channelOps
     * @param UserOperations $userOps
     * @param PhoneOperations $phoneOps
     * @param NormalizedObjectFactory $objectFactory
     * @param GlobalAuthContext $globalAuthContext
     * @param NodeCommandHandler $nodeCommandHandler
     */
    public function __construct(
        DataController $dataController,
        LoggerInterface $logger,
        FileOperations $fOps,
        EventManager $eventManager,
        ChannelOperations $channelOps,
        UserOperations $userOps,
        PhoneOperations $phoneOps,
        NormalizedObjectFactory $objectFactory,
        GlobalAuthContext $globalAuthContext,
        NodeCommandHandler  $nodeCommandHandler
    ) {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->channelOps = $channelOps;
        $this->userOps = $userOps;
        $this->objectFactory = $objectFactory;
        $this->phoneOps = $phoneOps;
        $this->globalAuthContext = $globalAuthContext;
        $this->nodeCommandHandler = $nodeCommandHandler;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController() : DataController
    {
        return $this->dataController;
    }

    /******************************************************************************************************************/
    //  END POINTS
    /******************************************************************************************************************/

    /**
     * Get information about a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function info(Request $request, Response $response) : Response
    {
        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channelId = $params['channel_id'];

        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error(I18n::get('messages.channel_invalid'), 404)->write($response);
        }


        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_READ)) {
            return JsonOutput::error("You do not have access this channel", 401)->write($response);
        }

        // If this user is the owner of this channel, we will give more, else abbreviated
        $abbreviated = (int)$user->getId() != (int)$channel->getOwnedBy();


        $channelEx = $this->objectFactory->normalizedObject($channel, $user, $abbreviated);

        // Return the channel object
        return JsonOutput::success()->withContent('channel', $channelEx)->write($response);
    }

    /**
     * Creates a new channel
     *
     * Auth Requirement: Requires a valid user login
     *
     * @param Request $request .
     * @param Response $response
     * @return Response|void
     * @throws InvalidSuccessfulHttpCodeException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws InvalidEmailAddressException
     */
    public function create(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        // TODO - Replace this with the new authorization subsystem
        $authContext = $this->globalAuthContext->getEffectivePermission(
            $user->getId(),
            GlobalAuthContext::CONTEXT_TYPE_GLOBAL,
            null
        );
        if ($authContext < GlobalAuthContext::AUTH_WRITE) {
            $this->logger->error("User " . $user->getId() . " does not have authority to create channel");
            return JsonOutput::error("You do not have authority to create channel", 401)->write($response);
        }


        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(
            ['channel_name', 'emails', 'users', 'auto_close_days', 'copy_from_channel_id'],
            $params,
            $response
        )) {
            return $response;
        }

        $cname = htmlspecialchars_decode($params['channel_name']);

        $blurb = '';
        if (!empty($params['blurb'])) {
            $blurb = $params['blurb'];

            if (StringUtility::containsProfanity($blurb, $violatingWords)) {
                $this->logger->error(__FUNCTION__ . " Blurb has profanity: " . $blurb);
                $violatingWords = implode(', ', $violatingWords);
                return JsonOutput::error(
                    "Channel description violates our non-profanity policy. Violating words: $violatingWords",
                    400
                )->write($response);
            }
        }

        $invitees = array();
        if (!empty($params['emails'])) {
            $invitees = explode(',', $params['emails']);
        }

        if (!empty($params['users'])) {
            $invitees = explode(',', $params['users']);
        }

        $autoCloseDays = -1;
        if (!empty($params['auto_close_days'])) {
            $autoCloseDays = (int)$params['auto_close_days'];
        }

        $copyChannelId = -1;
        if (!empty($params['copy_from_channel_id'])) {
            $copyChannelId = (int)$params['copy_from_channel_id'];
        }

        if ($params['is_public'] ?? false) { // is_public option is deprecated!
            $allowJoin = true;
            $requireJoinApproval = false;
            $allowExternalRead = true;
        } else {
            $allowJoin = (bool)($params['allow_join'] ?? false);
            $requireJoinApproval = (bool)($params['require_join_approval'] ?? true);
            $allowExternalRead = (bool)($params['allow_external_read'] ?? false);
        }

        foreach ($invitees as $invitee) {
            if (Utility::isValidPhoneFormat($invitee) && empty($this->phoneOps->getValidatedPhone($invitee))) {
                return JsonOutput::error("Invalid Phone for invitee", 400)->write($response);
            }
        }

        if (StringUtility::containsProfanity($cname, $violatingWords)) {
            $this->logger->error(__FUNCTION__ . " channel name has profanity: " . $cname);
            $violatingWords = implode(', ', $violatingWords);
            return JsonOutput::error(
                "This Channel Name violates our non-profanity policy. Violating words: $violatingWords",
                400
            )->write($response);
        }

        // get the team_id, if provided
        $team = null;
        $openTeamJoin = false;
        $teamId = isset($params['team_id']) ? (int) $params['team_id'] : null;
        if ($teamId !== null) {

            // team must exists
            $team = $this->dataController->getTeamByTeamId($teamId);
            if ($team === null) {
                return JsonOutput::error("Team $teamId was not found", 404)->write($response);
            }

            // logged user must be authorized to create channel on the team
            if ($user->cannot('createChannel', $team)) {
                return JsonOutput::error("You're not authorized to create a channel on team $teamId", 403)->write($response);
            }

            // open team join is only considered if a team_id is provided
            $openTeamJoin = (bool) ($params['open_team_join'] ?? false);

        }

        try {
            $channel = $this->channelOps->createChannel(
                $user,
                $cname,
                $blurb,
                (int)$user->getId(),
                $autoCloseDays,
                $copyChannelId,
                $allowJoin,
                $requireJoinApproval,
                $allowExternalRead,
                null,
                null,
                false,
                $team,
                $openTeamJoin
            );

            /** @var User[] $addedUsers */
            $addedUsers = [];
            foreach ($invitees as $invitee) {

                // if invitee is an id, check if the invitee already have relation with the inviter
                if (preg_match('/^[0-9]+$/', $invitee) && !$this->userOps->hasRelation((int)$invitee, $user->getId())) {
                    // abort, since UI should block it
                    // probably mean that the user is doing something bad/malicious
                    return JsonOutput::error("Invalid invitees list", 400)->write($response);
                }

                try {
                    $addedUsers[] = $this->channelOps->addUserToChannel(
                        (int)$channel->getId(),
                        $invitee,
                        (int)$user->getId()
                    );
                } catch (ChannelOpException $e) {
                    // just skip and continue if a user insert fails
                    continue;
                }
            }
        } catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 400)->write($response);
        }

        // Raise bot notification
        $this->channelOps->raiseUserAddedBotMessage($user, $addedUsers, (int)$channel->getId());


        // Augment data
        $channelEx = $this->objectFactory->normalizedObject($channel, $user, false);

        // Return the channel object
        return JsonOutput::success()->withContent('channel', $channelEx)->write($response);
    }

    /**
     * Get all channels for the user
     *
     * Auth Requirement: Requires a valid user login
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function list(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['exclude_closed'], $params, $response)) {
            return $response;
        }

        $excludeClosed = true;
        if (!empty($params['exclude_closed'])) {
            $excludeClosed = $params['exclude_closed'] === "false" ? false : true;
        }

        $channels = $this->objectFactory->getNormalizedChannelsForUser($user, $excludeClosed);

        return JsonOutput::success()->withContent('channels', $channels)->write($response);
    }

    /**
     *
     * Invite user(s) to channel.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidArgumentException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws InvalidEmailAddressException
     */
    public function invite(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        if (isset($params['user_role'])) {
            $params['optional_user_role'] = $params['user_role'];
            unset($params['user_role']);
        }

        // Validate request.
        if (!RequestValidator::validateRequest(
            ['channel_id', 'emails', 'users', 'optional_user_role'],
            $params,
            $response
        )) {
            return $response;
        }

        $channelId = $params['channel_id'];
        $invitees = [];
        if (!empty($params['emails'])) {
            $invitees = explode(',', $params['emails']);
        }
        if (!empty($params['users'])) {
            $invitees = explode(',', $params['users']);
        }

        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        foreach ($invitees as $invitee) {
            if (Utility::isValidPhoneFormat($invitee) && empty($this->phoneOps->getValidatedPhone($invitee))) {
                return JsonOutput::error("Invalid Phone for invitee", 400)->write($response);
            }
        }

        if ($user->cannot('invite', $channel)) {
            $this->logger->error(
                "User " . $user->getId() . " does not have authority to invite for channel " . $channelId
            );
            return JsonOutput::error("You do not have authority to add users to this channel", 401)->write($response);
        }

        $role = $channel->getDefaultInviteeRole();
        if (!empty($params['optional_user_role'])) {
            $role = $params['optional_user_role'];
        }

        $addedUsers = [];

        try {
            foreach ($invitees as $invitee) {

                // if invitee is an id, check if the invitee already have relation with the inviter
                if (preg_match('/^[0-9]+$/', $invitee) && !$this->userOps->hasRelation((int)$invitee, $user->getId())) {
                    // abort, since UI should block it
                    // probably mean that the user is doing something bad/malicious
                    return JsonOutput::error("Invalid invitees list", 400)->write($response);
                }

                try {
                    $addedUsers[] = $this->channelOps->addUserToChannel(
                        (int)$channel->getId(),
                        $invitee,
                        (int)$user->getId(),
                        (int)$role
                    );
                } catch (ChannelOpException $e) {
                    // just skip and continue
                    continue;
                }
            }
        } catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        // Raise bot notification
        $this->channelOps->raiseUserAddedBotMessage($user, $addedUsers, (int)$channelId);

        // normalize users
        $addedUsers = array_map(
            function (User $user) {
                return $this->objectFactory->normalizedObject($user);
            },
            $addedUsers
        );


        return JsonOutput::success()->withContent('users', $addedUsers)->write($response);
    }

    /**
     * Joins the logged user to the channel (only for public channels)
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelOpException
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws FSOpException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     */
    public function join(Request $request, Response $response) : Response
    {
        // need a user to join the channel
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['channel_id', 'public_hash'], $params, $response)) {
            return $response;
        }

        $channelId = (int)$params['channel_id'];
        $publicHash = $params['public_hash'];

        if (preg_match('/^pub-(.*)$/', $publicHash, $matches)) {
            $publicHash = $matches[1];
        }

        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        // ensure the channel is public (accepts join requests), and the hash is correct
        if (empty($channelPublicHash = $this->dataController->getPublicHashForResource('ChannelInvite', $channel->getId()))) {
            return JsonOutput::error("This channel doesn't allow self join. Ask a manager to add you.", 403)->write($response);
        }

        if ($publicHash !== $channelPublicHash) {
            return JsonOutput::error("You can't join this channel", 403)->write($response);
        }

        if ($channel->getRequireJoinApproval()) {

            if ($this->dataController->channelUserPendingExists($channelId, $user->getId())) {
                return JsonOutput::error("You already requested to join this channel", 422)->write($response);
            }

            if ($this->dataController->isUserBlacklistedOnChannel($user->getId(), $channelId)) {
                return JsonOutput::error("Your user is banned from this channel.", 422)->write($response);
            }

            $channelUserPending = ChannelUserPending::create($channelId, $user->getId());
            $this->dataController->addChannelUserPending($channelUserPending);

            // get the new list of pending users
            $pending = $this->dataController->getPendingUsersForChannel($channel->getId());
            $pending = array_map(
                function ($user) {
                    return $this->objectFactory->normalizedObject($user);
                },
                $pending
            );

            $event = new ChannelJoinUpdateEvent($channel, $user, $pending, true);
            $this->eventManager->publishEvent($event);

        } else {

            try {
                $this->channelOps->addUserToChannelByJoin($channel, $user->getId());
            } catch (ChannelOpException $e) {
                return JsonOutput::error($e->getMessage(), 400)->write($response);
            }
        }

        return JsonOutput::success(201)->write($response);
    }

    public function checkJoin(Request $request, Response $response) : Response
    {
        // need a user to check channel join
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channelId = (int)$params['channel_id'];

        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        if ($this->dataController->isChannelMember($channelId, $user->getId())) {
            return JsonOutput::error("You're already a member of this channel", 400)->write($response);
        }

        if (!$channel->getAllowExternalRead() || !$channel->getRequireJoinApproval()) {
            return JsonOutput::error("This channel don't support join requests.", 400)->write($response);
        }

        $joined = $this->dataController->channelUserPendingExists($channelId, $user->getId());
        return JsonOutput::success()->withContent('joined', $joined)->write($response);

    }

        /**
     *
     * Get members of a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function members(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }
        $channelId = $params['channel_id'];

        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        // TODO - replace with the new authorization system
        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_READ)) {
            return JsonOutput::error("You do not have access this channel", 401)->write($response);
        }

        $users = $this->objectFactory->getNormalizedUsersForChannel($channel, $user);

        return JsonOutput::success()->withContent('users', $users)->write($response);
    }


    /**
     * Get messages for a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function history(Request $request, Response $response) : Response
    {
        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id', 'limit', 'limit_newer'], $params, $response)) {
            return $response;
        }

        [$channelId, $e_cursor, $limit, $limit_newer] = $this->channelOps->extractHistoryParams($params);

        // if there is a notification attribute set on the request, it means that the request came from a notification,
        // so we need to validate if the notification was generated by this channel
        /** @var Notification $notification */
        $notification = $request->getAttribute('notification');
        if ($notification !== null && $notification->getChannelId() !== $channelId) {
            return JsonOutput::error("Forbidden", 403)->write($response);
        }

        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        // TODO - Replace with the new authorization system
        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_READ)) {
            return JsonOutput::error("You do not have access this channel", 401)->write($response);
        }

        $channelUser = $this->dataController->getUserChannel($channelId, $user->getId());
        $before = $channelUser instanceof ChannelUser ? $channelUser->getBlockedOn() : null;

        return $this->channelOps->handleHistoryResponse($response, $channelId, $e_cursor, $limit, $limit_newer, $before);
    }

    /**
     * Rename a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function rename(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id', 'channel_name'], $params, $response)) {
            return $response;
        }

        $channel_id = (int)$params['channel_id'];
        $channel_name = htmlspecialchars_decode($params['channel_name']);

        $channel = $this->dataController->getChannelById($channel_id);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        try {
            $this->channelOps->rename($user, $channel, $channel_name);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     *
     * Remove a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function remove(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channel_id = (int)$params['channel_id'];
        $channel = $this->dataController->getChannelById($channel_id);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        try {
            $this->channelOps->removeChannel($user, $channel);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws DatabaseException
     */
    public function kick(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['user_id', 'channel_id'], $params, $response)) {
            return $response;
        }

        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if (empty($channel)) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        $userToRemove = $this->dataController->getUserById((int)$params['user_id']);
        if (empty($userToRemove)) {
            return JsonOutput::error("Unknown User", 404)->write($response);
        }

        // check authorization
        if ($user->cannot('kick', $channel)) {
            return JsonOutput::error('You do not have authority to remove members from this channel', 401)->write(
                $response
            );
        }

        $blacklist = (bool)($params['blacklist'] ?? false);

        try {
            $this->channelOps->removeUserFromChannel($userToRemove, $user, $channel, $blacklist);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws DatabaseException
     */
    public function leave(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if (empty($channel)) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }
        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_READ)) {
            return JsonOutput::error('You do not have authority to leave this channel', 401)->write($response);
        }

        try {
            $this->channelOps->removeUserFromChannel($user, $user, $channel);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws DatabaseException
     */
    public function close(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if (empty($channel)) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_OWNER)) {
            $this->logger->error('User ' . $user->getId() . ' does not have authority to close ' . $channel->getId());
            return JsonOutput::error('You do not have authority to close this channel', 401)->write($response);
        }

        try {
            $this->channelOps->closeChannel($channel);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function activate(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_OWNER)) {
            $this->logger->error('User ' . $user->getId() . ' does not have authority to open ' . $channel->getId());
            return JsonOutput::error('You do not have authority to open this channel', 401)->write($response);
        }

        try {
            $this->channelOps->activateChannel($channel);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     *
     * Set a profile Photo
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function imageSet(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }
        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(
            ['channel_asset_type', 'channel_id', 'clear_asset'],
            $params,
            $response
        )) {
            return $response;
        }

        $assetType = $params['channel_asset_type'];

        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_MANAGE)) {
            $this->logger->error('User ' . $user->getId() . ' does not have authority to modify ' . $channel->getId());
            return JsonOutput::error('You do not have authority to modify this channel', 401)->write($response);
        }

        $bgClear = false;
        $iconClear = false;

        if (!empty($params['clear_asset']) &&
            ($params['clear_asset'] == 'true' || $params['clear_asset'] == 1)) {
            if ($assetType == 'background') {
                $bgClear = true;
            }
            if ($assetType == 'logo') {
                $iconClear = true;
            }
        }


        if ($bgClear || $iconClear) {
            // If any of these fields are provided, we ignore all files being sent
            try {
                $this->channelOps->clearAsset($channel, $bgClear, $iconClear);
            } catch (ASException $ex) {
                $this->logger->error(__FUNCTION__ . " Exception: " . $ex->getMessage());
                // Return internal server error
                return JsonOutput::error($ex->getMessage(), 500)->write($response);
            }

            return JsonOutput::success()->write($response);
        } else {
            // handle single input with single file upload
            $uploadedFiles = $request->getUploadedFiles();
            if (count($uploadedFiles) <= 0) {
                $this->logger->error(__FUNCTION__ . " WebServer File Upload Failure, no files found : ");
                return JsonOutput::error("Upload Failure", 400)->write($response);
            }

            if (!isset($uploadedFiles['file'])) {
                $this->logger->error(
                    __FUNCTION__ . " WebServer File Upload Failure, bad file input param name, use 'file' : "
                );
                return JsonOutput::error("Upload Failure", 400)->write($response);
            }

            $uploadedFile = $uploadedFiles['file'];
            if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
                $basename = bin2hex(random_bytes(8));
                $filename = sprintf('tmp_upload_%s.%0.8s', $basename, $extension);
                $phyFile = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
                $this->logger->info("Channel image for " . $channel->getName() . " is at " . $phyFile);
                $uploadedFile->moveTo($phyFile);

                try {
                    $this->channelOps->storeAsset($user, $channel, $assetType, $phyFile, $extension);
                } catch (ChannelAuthorizationException $ex) {
                    return JsonOutput::error($ex->getMessage(), 401)->write($response);
                } catch (ASException $ex) {
                    $this->logger->error(__FUNCTION__ . " Exception: " . $ex->getMessage());
                    // Return internal server error
                    return JsonOutput::error($ex->getMessage(), 500)->write($response);
                }

                return JsonOutput::success()->write($response);
            }

            $this->logger->error(__FUNCTION__ . " No file uploaded");
            return JsonOutput::error("Error", 400)->write($response);
        }
        $this->logger->error(__FUNCTION__ . " No file uploaded");
        return JsonOutput::error("Error", 400)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function imageGet(Request $request, Response $response) : Response
    {
        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['channel_asset_type', 'channel_id'], $params, $response)) {
            return $response;
        }

        $channelId = (int)$params['channel_id'];
        $assetType = $params['channel_asset_type'];
        if (($assetType != 'logo') && ($assetType != 'background')) {
            $this->logger->error(__FUNCTION__ . " Invalid asset type : $assetType . Can only be logo or background");
            return JsonOutput::error(" Invalid asset type : $assetType . Can only be logo or background", 400)->write(
                $response
            );
        }

        if (empty($channel = $this->dataController->getChannelById($channelId))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if ($user->cannot('getImage', $channel)) {
            $this->logger->debug('User ' . $user->getId() . ' does not have authority to read ' . $channel->getId());
            return JsonOutput::error('You do not have access to this channel', 401)->write($response);
        }

        try {
            $asset = $this->channelOps->getAsset($channel, $assetType);
        } catch (ChannelOpException $ex) {
            // asset was not found
            return JsonOutput::error($ex->getMessage(), 404)->write($response);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            //$this->logger->error(__FUNCTION__ . " Exception: " . $ex->getMessage());
            // Return internal server error
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        // Need to send the $asset->getData() and $asset->getMime to caller
        $response->getBody()->write($asset->getData());
        return $response->withHeader('Content-Type', $asset->getMime());
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function setUserRole(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $params = $request->getParsedBody();
        if (isset($params['user_role'])) {
            $params['channel_user_role'] = $params['user_role'];
            unset($params['user_role']);
        }


        // Validate request
        if (!RequestValidator::validateRequest(['channel_id', 'user_id', 'channel_user_role'], $params, $response)) {
            return $response;
        }

        $userIdToSet = (int)$params['user_id'];

        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error('You do not have authority to modify user role in this channel', 401)->write(
                $response
            );
        }

        if ($userIdToSet === $channel->getOwnedBy()) {
            return JsonOutput::error("Can't change channel owner role.", 403)->write(
                $response
            );
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_MANAGE)) {
            return JsonOutput::error('You do not have authority to modify user role in this channel', 403)->write(
                $response
            );
        }

        if ((int)$user->getId() == $userIdToSet) {
            return JsonOutput::error('Channel owner cannot demote ones own role', 400)->write($response);
        }

        try {
            $this->logger->debug(__FUNCTION__ .  ' : User ' . $userIdToSet . ' set to role ' . $params['channel_user_role']
            . ' by ' . $user->getDisplayName());
            $this->channelOps->setUserRole($channel, $userIdToSet, (int)$params['channel_user_role']);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function update(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();


        $channelName = isset($params['channel_name']) ? htmlspecialchars_decode($params['channel_name']) : null;
        $blurb = '';
        if (!empty($params['blurb'])) {
            $blurb = $params['blurb'];
        }
        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if ($params['is_public'] ?? false) { // is_public option is deprecated!
            $allowJoin = true;
            $requireJoinApproval = false;
            $allowExternalRead = true;
        } else {
            $allowJoin = isset($params['allow_join']) ? ((bool)($params['allow_join'])) : null;
            $requireJoinApproval = isset($params['require_join_approval']) ? ((bool)($params['require_join_approval'])) : null;
            $allowExternalRead = isset($params['allow_external_read']) ? ((bool)($params['allow_external_read'])) : null;
        }


        try {
            $newChannel = $this->channelOps->update($user, $channel, $channelName, $blurb, $allowJoin, $requireJoinApproval, $allowExternalRead);
        } catch (ChannelOpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getCode())->write($response);
        }
        $newChannel = $this->objectFactory->normalizedObject($newChannel, $user);

        return JsonOutput::success()->withContent('channel', $newChannel)->write($response);
    }

    /**
     * Sets the channel as favorite
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function favorite(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_WRITE)) {
            $this->logger->error(
                'User ' . $user->getId() . ' does not have authority to favorite ' . $channel->getId()
            );
            return JsonOutput::error('You do not authority to update this channel', 401)->write($response);
        }

        try {
            $this->channelOps->setFavorite($channel, $user, true);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success(204)->write($response);
    }

    /**
     * Sets the channel as favorite
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function unfavorite(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_WRITE)) {
            $this->logger->error(
                'User ' . $user->getId() . ' does not have authority to favorite ' . $channel->getId()
            );
            return JsonOutput::error('You do not authority to update this channel', 401)->write($response);
        }

        try {
            $this->channelOps->setFavorite($channel, $user, false);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success(204)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function manageNotifications(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['channel_id', 'notification_option'], $params, $response)) {
            return $response;
        }

        $configValue = User::NOTIFICATIONS_CONFIG_MAP[$params['notification_option']];

        $this->dataController->setChannelNotificationsConfig((int)$params['channel_id'], $user->getId(), $configValue);

        return JsonOutput::success()->write($response);
    }

    /**
     *
     * Endpoint to export channel data
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     */
    public function export(Request $request, Response $response) : Response
    {
        // try to get the authenticated user
        $user = $this->requireValidUser($request, $response);
        if (empty($user)) {
            return $response;
        }

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        if (empty($channel = $this->dataController->getChannelById((int)$params['channel_id']))) {
            return JsonOutput::error("Unknown Channel", 404)->write($response);
        }

        // TODO - replace with the new Authorization system
        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_OWNER)) {
            $this->logger->error('User ' . $user->getId() . ' does not have authority to export ' . $channel->getId());
            return JsonOutput::error('You do not have authority to export this channel', 401)->write($response);
        }

        $exportMode = "";
        // Export and return
        // ... Send Callback that will be run by the response object
        $output = new CallbackStream(
            function () use ($user, $channel, $exportMode) {
                $this->channelOps->sendZipCallback($user, $channel, $exportMode);
                return '';
            }
        );

        return $response->withBody($output);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws ChannelOpException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidEmailAddressException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws FSOpException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UnknownResourceException
     * @throws UserOpException
     */
    public function oneOnOne(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        if (empty($params['with_user_id'])) {
            return JsonOutput::error('Other part id is required', 422)->write($response);
        }

        $otherUser = $this->dataController->getUserById((int)$params['with_user_id']);
        if ($otherUser === null) {
            return JsonOutput::error('Invalid user provided', 422)->write($response);
        }

        if ($otherUser->getId() === $user->getId()) {
            return JsonOutput::error("Can't create a one on one channel with yourself.", 422)->write($response);
        }

        // check if the invitee already have relation with the inviter
        if (!$this->userOps->hasRelation($user->getId(), $otherUser->getId())) {
            // abort, since UI should block it
            // probably mean that the user is doing something bad/malicious
            return JsonOutput::error("Invalid invitees list", 400)->write($response);
        }

        // check if there is a one on one channel for those users
        $channel = $this->channelOps->findOneOnOne($user->getId(), $otherUser->getId());

        // no channel found, create one
        if ($channel === null) {
            // creates a regular channel
            $channelName = "{$user->getDisplayName()}, {$otherUser->getDisplayName()}";

            $count = '';
            while ($this->channelOps->doesChannelNameExistInTeam($user, $channelName))
            {
                $channelName = "{$user->getDisplayName()}, {$otherUser->getDisplayName()}" . ' - DM ' .$count;
                if ($count == '') {
                    $count = 1;
                }
                else {
                    $count++;
                }
            }

            try {
                $channel = $this->channelOps->createChannel(
                    $user,
                    $channelName,
                    '',
                    null,
                    -1,
                    -1,
                    false,
                    null,
                    null,
                    null,
                    null,
                    true
                );
            }
            catch (ASException $ex) {
                $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
                return JsonOutput::error($ex->getMessage(), 400)->write($response);
            }

            // adds the other part to the channel
            $this->channelOps->addUserToChannel(
                $channel->getId(),
                $otherUser->getEmail() ?? $otherUser->getPhone(),
                $user->getId(),
                ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI,
                true
            );
        }

        $channelEx = $this->objectFactory->normalizedObject($channel, $user, false);

        // Return the channel object
        return JsonOutput::success()->withContent('channel', $channelEx)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function transferOwnership(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channelId = (int)$params['channel_id'];
        $channel = $this->dataController->getChannelById($channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        $newOwnerId = $params['new_owner_id'] ?? null;
        if ($newOwnerId === null) {
            return JsonOutput::error('The new owner id is required', 422)->write($response);
        }
        $newOwnerId = (int) $newOwnerId;

        $teamId = isset($params['team_id']) ? ((int)$params['team_id']) : null;
        $oldTeamId = $channel->getTeamId(); // save the old team id

        try {
            $this->channelOps->transferOwnership($user, $channel, $newOwnerId, $teamId);
        } catch (ChannelAuthorizationException $ex) {
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        // trigger team events
        $oldTeam = $this->dataController->getTeamByTeamId($oldTeamId);
        if (!$oldTeam->isSelfTeam()) {
            $event = new TeamRemoveChannelEvent($oldTeam, $channel);
            $this->eventManager->publishEvent($event);
        }
        $newTeam = $this->dataController->getTeamByTeamId($teamId);
        if (!$newTeam->isSelfTeam()) {
            $event = new TeamAddChannelEvent($newTeam, $channel);
            $this->eventManager->publishEvent($event);
        }

        return JsonOutput::success()->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function oauthClient(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channelId = (int)$params['channel_id'];

        $clientId = $params['client_id'] ?? null;
        if ($clientId === null) {
            return JsonOutput::error("Oauth client id is required", 422)->write($response);
        }
        $client = $this->dataController->getOauthClientById($clientId);

        $disable = (bool)($params['disable'] ?? false);


        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_MANAGE)) {
            $this->logger->error(
                "User " . $user->getId() . " does not have authority to manage Oauth Clients for channel " . $channelId
            );
            return JsonOutput::error("You do not have authority to manage Oauth Clients on this channel", 401)->write($response);
        }

        if (!$disable) {
            if (!$this->dataController->isOauthClientActivatedOnChannel($channelId, $clientId)) {
                $this->dataController->addOauthClientToChannel($channelId, $clientId);
                $this->channelOps->raiseOauthClientAddedBotMessage($user, $client, $channelId);
            }
        } else {
            if ($this->dataController->isOauthClientActivatedOnChannel($channelId, $clientId)) {
                $this->dataController->removeOauthClientFromChannel($channelId, $clientId);
                $this->channelOps->raiseOauthClientRemovedBotMessage($user, $client, $channelId);
            }
        }

        return JsonOutput::success()->write($response);
    }


    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function approveJoin(Request $request, Response $response): Response
    {
        return $this->approveOrRemoveJoinRequest($request, $response, true);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function removeJoin(Request $request, Response $response): Response
    {
        return $this->approveOrRemoveJoinRequest($request, $response, false);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param bool $approve
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    protected function approveOrRemoveJoinRequest(Request $request, Response $response, bool $approve): Response
    {
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        // Validate request.
        if (!RequestValidator::validateRequest(['channel_id', 'user_id'], $params, $response)) {
            return $response;
        }

        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if (empty($channel)) {
            return JsonOutput::error('Channel not found.', 404)->write($response);
        }

        $user = $this->dataController->getUserById((int)$params['user_id']);
        if (empty($user)) {
            return JsonOutput::error('User not found.', 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($loggedUser, $channel, GlobalAuthContext::AUTH_MANAGE)) {
            return JsonOutput::error("You do not have authority to remove members from this channel.", 401)->write($response);
        }

        if ($approve) {
            try {
                $this->channelOps->approveJoinRequest($channel, $user, $loggedUser);
            } catch (\Throwable $e) {
                return JsonOutput::error($e->getMessage(), 400)->write($response);
            }
        } else {
            try {
                $this->channelOps->removeJoinRequest($channel, $user);
            } catch (\Throwable $e) {
                return JsonOutput::error($e->getMessage(), 400)->write($response);
            }
        }
        return JsonOutput::success(201)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws MemoizingException
     * @throws StorageServiceException
     * @throws ValidationErrorException
     */
    public function wikiTree(Request $request, Response $response): Response
    {
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        // Validate request.
        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        // get channel object
        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if ($channel === null) {
            return JsonOutput::error('Channel not found.', 404)->write($response);
        }

        $wikiPath = $this->fOps->getChannelRoots($channel, ChannelPath::CHANNEL_PATH_TYPE_WIKI);


        try {
            $tree = $this->fOps->wikiTree($wikiPath->getRelativePath(), $loggedUser);
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not authorized to access this wiki-tree", 403)->write($response);
        } catch (NotAFolderException | NotFoundException $e) {
            return JsonOutput::error("The wiki-tree resource doesn't exist.", 404)->write($response);
        }

        return JsonOutput::success()->withContent('tree', $tree)->write($response);
    }


    /**
     * @param Request $request
     * @param Response $response
     * @param bool $approve
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function readNotification(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        if (!isset($params['message_id'])) {
            if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
                return $response;
            }
        }
        else {
            // Validate request.
            if (!RequestValidator::validateRequest(['message_id', 'channel_id'], $params, $response)) {
                return $response;
            }
        }

        // get channel object
        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if ($channel === null) {
            return JsonOutput::error('Channel not found.', 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_WRITE)) {
            $this->logger->error(
                'User ' . $user->getId() . ' does not have authority to mark read ' . $channel->getId()
            );
            return JsonOutput::error('You do not authority to update this channel', 401)->write($response);
        }

        try {
            if (isset($params['message_id'])) {
                $this->nodeCommandHandler->processReadNotification($user, $channel->getId(), $params['message_id']);
            }
            else {
                $this->nodeCommandHandler->processReadAllNotification($user, $channel->getId());
            }
        } catch (Throwable $e) {
            return JsonOutput::error($e->getMessage(), 400)->write($response);
        }
        return JsonOutput::success()->write($response);
    }

    public function links(Request $request, Response $response): Response
    {

        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        $channelId = $params['channel_id'];

        if ($cursor = $params['cursor'] ?? null) {
            $cursor = (int)$cursor;
        }

        if ($limitAfter = $params['limit_after'] ?? null) {
            $limitAfter = (int)$limitAfter;
        }

        if ($limitBefore = $params['limit_before'] ?? null) {
            $limitBefore = (int)$limitBefore;
        }

        // cursor is required when limit before is provided
        if ($limitBefore !== null && $cursor === null) {
            return JsonOutput::error('Cursor is required when you provice a limit_before', 422)->write($response);
        }

        // Validate the channelId
        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 404)->write($response);
        }

        if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_READ)) {
            return JsonOutput::error("You do not have access this channel", 401)->write($response);
        }

        $result = array_map(function(array $item) {
            $message = $this->dataController->getMessageById($item['message_id']);
            $item['message'] = $this->objectFactory->normalizedObject($message);
            unset($item['message_id']);
            return $item;
        }, $this->channelOps->handlePaginatedLinks($channel, $cursor, $limitAfter, $limitBefore));

        return JsonOutput::success()->withContent('links', $result)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidEmailAddressException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     * @throws ValidationErrorException
     */
    public function teamJoin(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) {
            return $response;
        }

        // find the channel to join
        $channelId = (int)$params['channel_id'];
        $channel = $this->dataController->getChannelById($channelId);
        if ($channel === null) {
            return JsonOutput::error('Channel not found', 404)->write($response);
        }

        // channel must be open for team join
        if (!$channel->isOpenForTeamJoin()) {
            return JsonOutput::error('This channel doesn\'t accept team joins', 422)->write($response);
        }

        // user must be member of the channel team
        if (!$this->dataController->isTeamMember($channel->getTeamId(), $loggedUser->getId())) {
            return JsonOutput::error("You're not authorized to join this channel.", 403)->write($response);
        }

        // user can't be already a member of the team
        if ($this->dataController->isChannelMember($channel->getId(), $loggedUser->getId())) {
            return JsonOutput::error("You're already a member of the channel", 422)->write($response);
        }

        $this->channelOps->addUserToChannel($channel->getId(), $loggedUser->getId(), $channel->getOwnedBy());

        return JsonOutput::success(201)->write($response);
    }

    public function listBlocked(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $blockedList = $this->dataController->getBlockedChannelsForUser($loggedUser->getId());

        $blockedChannels = array_map(function (ChannelUser $channelUser) {
            $blockedOn = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $channelUser->getBlockedOn());
            return [
                'channel_id' => $channelUser->getChannelId(),
                'blocked_at' => $channelUser->getBlockedOn(),
                'blocked_at_ts' => $blockedOn->getTimestamp(),
            ];
        }, $blockedList);


        return JsonOutput::success()->withContent('blocked_channels', $blockedChannels)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function block(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $channelId = ((int)$params['channel_id']) ?? null;
        if ($channelId === null) {
            return JsonOutput::error("Channel id is required", 422)->write($response);
        }

        $channelUser = $this->dataController->getUserChannel($channelId, $loggedUser->getId());
        if ($channelUser === null) {
            return JsonOutput::error("You must be a member of the channel to block it.", 422)->write($response);
        }
        if ($channelUser->getBlockedOn() !== null) {
            return JsonOutput::error("This channel is already blocked", 422)->write($response);
        }

        $reportSpamMessage = trim($params['report_spam_message'] ?? '');
        if (strlen($reportSpamMessage) > 255) {
            return JsonOutput::error("Spam report message max size is 255", 422)->write($response);
        }

        $channelUser->setBlockedOn(date('Y-m-d H:i:s'));
        $this->dataController->updateChannelUser($channelUser);

        $channel = $this->dataController->getChannelById($channelId);
        $event = new ChannelUpdateEvent($channel, $loggedUser);
        $event->setSelfOnly(true);
        $this->eventManager->publishEvent($event);

        if (!empty($reportSpamMessage)) {
            $this->channelOps->saveSpamReport($channel, $loggedUser, $reportSpamMessage);
        }

        return JsonOutput::success(201)->write($response);
    }

    public function unblock(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $channelId = ((int)$params['channel_id']) ?? null;
        if ($channelId === null) {
            return JsonOutput::error("Channel id is required", 422)->write($response);
        }

        $channelUser = $this->dataController->getUserChannel($channelId, $loggedUser->getId());
        if ($channelUser === null) {
            return JsonOutput::error("You must be a member of the channel to unblock it.", 422)->write($response);
        }
        if ($channelUser->getBlockedOn() === null) {
            return JsonOutput::error("This channel is not blocked.", 422)->write($response);
        }

        $channelUser->setBlockedOn(null);
        $this->dataController->updateChannelUser($channelUser);

        $channel = $this->dataController->getChannelById($channelId);
        $event = new ChannelUpdateEvent($channel, $loggedUser);
        $event->setSelfOnly(true);
        $this->eventManager->publishEvent($event);

        // TODO - Remove/mark the spam report

        return JsonOutput::success(201)->write($response);
    }

}
