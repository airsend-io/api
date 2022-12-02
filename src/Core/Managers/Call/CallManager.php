<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Call;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class CallManager extends ManagerBase
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
     * @var CallOperations
     */
    protected $channelOps;

    /**
     * @var GlobalAuthContext
     */
    protected $globalAuthContext;

    /**
     * @var CallOperations
     */
    private $callOps;
    /**
     * @var NormalizedObjectFactory
     */
    private $objectFactory;

    /**
     * ChannelManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param GlobalAuthContext $globalAuthContext
     * @param CallOperations $callOps
     * @param NormalizedObjectFactory $objectFactory
     * @param ChannelOperations $channelOps
     */
    public function __construct(
        DataController $dataController,
        LoggerInterface $logger,
        GlobalAuthContext $globalAuthContext,
        CallOperations $callOps,
        NormalizedObjectFactory $objectFactory,
        ChannelOperations $channelOps
    )
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->globalAuthContext = $globalAuthContext;
        $this->objectFactory = $objectFactory;
        $this->callOps = $callOps;
        $this->channelOps = $channelOps;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController(): DataController
    {
        return $this->dataController;
    }

    /******************************************************************************************************************/
    //  END POINTS
    /******************************************************************************************************************/
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function create(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        if (isset($params['channel_id'])) {
            $params['call_channel_id'] = $params['channel_id'];
            unset($params['channel_id']);
        }

        if (!RequestValidator::validateRequest(['call_channel_id', 'is_public', 'allowed_users'], $params, $response)) {
            return $response;
        }

        $isPublic = (bool)($params['is_public'] ?? false);
        $allowedUsers = (string)($params['allowed_users'] ?? '');
        $channelId = (int)($params['call_channel_id'] ?? 0);
        $channel = null;
        // If channel is passed, validate it
        if ($channelId > 0) {
            $channel = $this->dataController->getChannelById((int)$channelId);
            if (empty($channel)) {
                return JsonOutput::error(I18n::get('messages.channel_invalid'), 404)->write($response);
            }

            if (!$this->channelOps->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_READ)) {
                return JsonOutput::error("You do not have access this channel", 401)->write($response);
            }
        }

        try {
            $call = $this->callOps->createCall($user, $channel, $isPublic, $allowedUsers);
            $callEx = $this->objectFactory->normalizedObject($call);
        } catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 400)->write($response);
        } catch (InvalidArgumentException $e) {
            return JsonOutput::error($e->getMessage(), 400)->write($response);
        }

        // Return the channel object
        return JsonOutput::success()->withContent('call', $callEx)->write($response);
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
    public function update(Request $request, Response $response): Response
    {
        // try to get the authenticated user

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['call_hash', 'is_public', 'allowed_users', 'rtm_token'], $params, $response)) {
            return $response;
        }

        if (empty($call = $this->dataController->getCallByHash($params['call_hash']))) {
            $this->logger->error(__FUNCTION__ . " Call hash does not exist : " . $params['call_hash']);
            return JsonOutput::error('Invalid ID', 404)->write($response);
        }

        if (!$this->callOps->updateCall($call, (bool)$params['is_public'], $params['allowed_users'], $params['rtm_token'])) {
            return JsonOutput::error('Failed updating call', 400)->write($response);
        }

        $callEx = $this->objectFactory->normalizedObject($call);
        return JsonOutput::success()->withContent('call', $callEx)->write($response);
    }


    /**
     * Called when a user joins a call
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function join(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['call_hash', 'rtm_token'], $params, $response)) {
            return $response;
        }

        if (empty($call = $this->dataController->getCallByHash($params['call_hash']))) {
            $this->logger->error(__FUNCTION__ . " Call hash does not exist : " . $params['call_hash']);
            return JsonOutput::error('Invalid ID', 404)->write($response);
        }

        if ($call->isPublic()) { // Only need to check if this call exists for public call
            $this->logger->debug('Valid public call : ' . $params['call_hash']);
            $payload = ['call' => $call->getArray()];

            return JsonOutput::success()->withContent('info', $payload)->write($response);
        } else {
            // For private call, user must be in the list
            if (empty($params['rtm_token'])) {
                return JsonOutput::error()->withCode(401)->write($response);
            }

            if (!empty($user = $this->callOps->checkUserAccess($call, $params['rtm_token']))) {
                $userEx = $this->objectFactory->normalizedObject($user);
                $channelUser = $this->dataController->getUserChannel((int)$call->getChannelId(), (int)$user->getId());
                if (!empty($channelUser)) {
                    $userEx->addInt('user_role', $channelUser->getUserRole());
                }
                $payload = ['user' => $userEx->getArray(), 'call' => $call->getArray()];

                return JsonOutput::success()->withContent('info', $payload)->write($response);
            } else {
                return JsonOutput::error()->withCode(401)->write($response);
            }
        }
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
    public function status(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['call_hash', 'channel_id'], $params, $response)) {
            return $response;
        }

        if (empty($call = $this->dataController->getCallByHash($params['call_hash']))) {
            $this->logger->error(__FUNCTION__ . " Call hash does not exist : " . $params['call_hash']);
            return JsonOutput::error('Invalid ID', 404)->write($response);
        }

        if (!$call->isPublic()) { // Only need to check if this call exists for public call
            $this->callOps->pushChannelUpdate((int)$params['channel_id']);
        } else {
            // Call not private. ignoring
            $this->logger->info(__FUNCTION__ . ': Call ' . ($params['call_hash']) . ' is not private. Ignoring');
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
    public function invite(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['call_hash', 'user_id'], $params, $response)) {
            return $response;
        }

        if (empty($call = $this->dataController->getCallByHash($params['call_hash']))) {
            $this->logger->error(__FUNCTION__ . " Call hash does not exist : " . $params['call_hash']);
            return JsonOutput::error('Invalid ID', 404)->write($response);
        }

        if (!$call->isPublic()) {
            // Make sure this user is part of the channel
            $channelUser = $this->dataController->getUserChannel((int)$call->getChannelId(), (int)$user->getId());
            if (empty($channelUser)) {
                return JsonOutput::error('Unauthorized', 401)->write($response);
            }
            $channelToUser = $this->dataController->getUserChannel((int)$call->getChannelId(), (int)$params['user_id']);
            if (empty($channelToUser)) { // Invited user is not part of this channel the call is associated with
                return JsonOutput::error('Unauthorized', 401)->write($response);
            }

            $this->callOps->notifyUser($call, $user, (int)$params['user_id']);
            return JsonOutput::success()->write($response);

        } else {
            return JsonOutput::error('Invalid call type', 404)->write($response);
        }
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
    public function inviteAccept(Request $request, Response $response): Response
    {
        // try to get the authenticated user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['call_hash', 'accept'], $params, $response)) {
            return $response;
        }

        if (empty($call = $this->dataController->getCallByHash($params['call_hash']))) {
            $this->logger->error(__FUNCTION__ . " Call hash does not exist : " . $params['call_hash']);
            return JsonOutput::error('Invalid ID', 404)->write($response);
        }

        if (!$call->isPublic()) {

            $this->callOps->notifyCallAcceptUser((string)$params['call_hash'], $user->getId(), (bool)$params['accept']);
            return JsonOutput::success()->write($response);

        } else {
            return JsonOutput::error('Invalid call type', 404)->write($response);
        }
    }

    /**
     * Remove a call
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function end(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['call_hash', 'rtm_token'], $params, $response)) {
            return $response;
        }

        if (empty($call = $this->dataController->getCallByHash($params['call_hash']))) {
            $this->logger->error(__FUNCTION__ . " Call hash does not exist : " . $params['call_hash']);
            return JsonOutput::error('Invalid ID', 404)->write($response);
        }

        // For private call, user must be in the list
        if (empty($params['rtm_token'])) {
            return JsonOutput::error()->withCode(401)->write($response);
        }

        if ($this->callOps->isCallOwner($call, $params['rtm_token'])) {

            if (!$call->isPublic()) { // Only need to check if this call exists for public call
                $this->callOps->pushChannelUpdate($call->getChannelId());
            } else {
                // Call not private. ignoring
                $this->logger->info(__FUNCTION__ . ': Call ' . ($params['call_hash']) . ' is not private. Ignoring');
            }

            $this->dataController->deleteCallByHash((string)$call->callHash());
            return JsonOutput::success()->write($response);
        } else {
            return JsonOutput::error('Not Authorized')->withCode(401)->write($response);
        }
    }


}
