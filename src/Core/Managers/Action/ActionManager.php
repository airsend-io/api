<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Action;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\SearchDataController;
use CodeLathe\Core\Exception\ActionOpException;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\HttpException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObject;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Core\Utility\Utility;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class ActionManager extends ManagerBase
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
     * @var ActionOperations
     */
    protected $actionOps;

    /**
     * @var NormalizedObjectFactory
     */
    protected $normalizedObjectFactory;

    /**
     * @var SearchDataController
     */
    protected $searchDataController;

    /**
     * ActionManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param ActionOperations $actionOps
     * @param NormalizedObjectFactory $objectFactory
     * @param SearchDataController $searchDataController
     */
    public function __construct(DataController $dataController,
        LoggerInterface $logger,
        FileOperations $fOps,
        EventManager $eventManager,
        ActionOperations $actionOps,
        NormalizedObjectFactory $objectFactory,
        SearchDataController $searchDataController)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->actionOps = $actionOps;
        $this->normalizedObjectFactory = $objectFactory;
        $this->searchDataController = $searchDataController;
    }

    /**
     * Derived class must give us the dataController
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    /**
     * Creates a new action
     *
     * Auth Requirement: Requires a valid user login
     *
     * @param Request $request .
     * @param Response $response
     * @return Response|void
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function create(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        // Validate request.
        if (isset($params['user_ids'])) {
            $params['action_user_ids'] = $params['user_ids'];
            unset($params['user_ids']);
        }

        //$this->logger->info("Params = " . print_r($params,true));

        if (!RequestValidator::validateRequest(['action_name', 'action_desc', 'channel_id', 'action_type', 'action_status', 'action_user_ids', 'action_due_date'], $params, $response)) { return $response;}

        if (empty($params['action_name'])) {
            return JsonOutput::error("action name is required", 422)->write($response);
        }

        // Required
        $createdBy = $user->getId();
        $channelId = (int)$params['channel_id'];
        $actionName = $params['action_name'];
        $actionType = (int)$params['action_type'];

        // Optional
        $actionStatus = Action::ACTION_STATUS_PENDING;
        if (!empty($params['action_status'])) {
            $actionStatus = (int)$params['action_status'];
        }
        $userIds = '';
        $userArray = [];
        if (!empty($params['action_user_ids'])) {
            $userIds = $params['action_user_ids'];
            $userArray = explode(',',$userIds);
        }

        $parentId = isset($params['parent_id']) ? (int) $params['parent_id'] : null;

        // if integer is passed assume it is timestamp
        if (Utility::isValidParams($params,'action_due_date')) {
            if (is_numeric($params['action_due_date'])) {
                $dueOn = date('Y-m-d H:i:s', intval($params['action_due_date']));
            }
            else {
                $dueOn = strval($params['action_due_date']);
            }
        }
        else {
            $dueOn = null;
        }

        $actionDesc =  isset($params['action_desc']) ? $params['action_desc'] : null;



        try {
            $action = $this->actionOps->createAction(
                $channelId,
                $actionName,
                $actionDesc,
                $actionType,
                $actionStatus,
                $dueOn,
                $createdBy,
                $userArray,
                $parentId
            );

            $actionWithUsers = $this->actionOps->getAction($action->getId(), $user->getId());
        } catch (ActionOpException $e) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $e->getMessage());
            return JsonOutput::error($e->getMessage(), $e->getHttpCode())->write($response);
        }

        $actionEx = $this->normalizedObjectFactory->normalizedObject($actionWithUsers);
        // Return the channel object
        return JsonOutput::success()->withContent('action', $actionEx)->write($response);
    }

    /**
     * get action info for a specific action
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function info(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['action_id'], $params, $response)) { return $response;}

        $actionId = (int)$params['action_id'];

        try {
            $actionWithUsers = $this->actionOps->getAction($actionId, $user->getId());
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        $actionEx = $this->normalizedObjectFactory->normalizedObject($actionWithUsers);

        // Return the channel object
        return JsonOutput::success()->withContent('action', $actionEx)->write($response);
    }

    /**
     * get list of actions for a user and/or a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     */
    public function list(Request $request, Response $response) : Response
    {
        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();

        // filters ------
        $userId = isset($params['user_id']) ? (int)$params['user_id'] : null;
        if ($userId !== null && $this->dataController->getUserById($userId) === null) {
            return JsonOutput::error('Invalid user.', 422)->write($response);
        }

        $channelId = isset($params['channel_id']) ? (int)$params['channel_id'] : null;
        if ($channelId !== null) {

            // when a channel id is defined, we only allow access for channel members or public user (if public user made it until here, he's allowed to access the channel)
            if ($this->dataController->getChannelById($channelId) === null || (!$this->dataController->isChannelMember($channelId, $loggedUser->getId()) && $loggedUser->getUserRole() !== User::USER_ROLE_PUBLIC)) {
                return JsonOutput::error('Invalid channel', 422)->write($response);
            }
        }

        $status = isset($params['status']) ? (int)$params['status'] : null;
        if ($status !== null && !in_array($status, [Action::ACTION_STATUS_PENDING, Action::ACTION_STATUS_COMPLETE])) {
            return JsonOutput::error('Invalid status.', 422)->write($response);
        }

        // sorting --------
        $sortBy = trim($params['sort_by'] ?? 'default');
        if ($sortBy !== null && !in_array($sortBy, ['default', 'name', 'channel', 'due_date'])) {
            return JsonOutput::error('Invalid sort_by.', 422)->write($response);
        }
        $sortDesc = (bool)($params['sort_desc'] ?? false);

        // default sorting only makes sense when the listing is filtered by channel
        if ($sortBy === 'default' && $channelId === null) {
            return JsonOutput::error('Default order is only available inside a channel', 422)->write($response);
        }

        // search -------
        $searchResult = [];
        $searchQuery = $params['search'] ?? null;
        $searchFilter = null;
        if ($searchQuery !== null) {

            // search the index
            $highlightHandler = function($highlightData) {
                return $highlightData;
            };
            $searchResult = $this->searchDataController->searchActions($loggedUser->getId(), $searchQuery, null, $channelId, 0, $highlightHandler);
            $searchFilter = array_keys($searchResult);

        }

        // infinite pagination -----

        $cursor = $params['cursor'] ?? null;
        if ($cursor !== null) {

            // cursor must be a valid base64
            if (!preg_match('/^[a-zA-Z0-9+\/=]+$/', $cursor)) {
                return JsonOutput::error('Invalid cursor.', 422)->write($response);
            }

            // decode the cursor and find the action
            $cursor = (int)base64_decode($cursor);
            $cursor = $this->dataController->getActionById($cursor);
            if ($cursor === null) {
                return JsonOutput::error('Invalid cursor.', 422)->write($response);
            }
        }

        // means get x actions after the cursor (optional). When both limits are set, the cursor action is included
        $limitAfter = isset($params['limit_after']) ? (int)($params['limit_after']) : null;

        // means get x actions before the cursor (optional). When both limits are set, the cursor action is included
        $limitBefore = isset($params['limit_before']) ? (int)($params['limit_before']) : null;

        // query the database...
        $actions = $this->dataController->getActionsPaginated($loggedUser->getId(), $sortBy, $sortDesc, $channelId, $userId, $status, $searchFilter, $cursor, $limitAfter, $limitBefore);

        // normalize the results
        $normalizedActions = array_map(function ($action) use ($searchResult) {
            $normalizedAction = $this->normalizedObjectFactory->normalizedObject($action);
            $normalizedAction = $normalizedAction->jsonSerialize();

            // add search highlights
            $normalizedAction['highlights'] = $searchResult[$normalizedAction['id']]['highlights'] ?? [];
            $normalizedAction['children'] = array_map(function (NormalizedObject $subAction) use ($searchResult) {
                $normalizedSubAction = $subAction->jsonSerialize();
                $normalizedSubAction['highlights'] = $searchResult[$normalizedSubAction['id']]['highlights'] ?? [];
                return $normalizedSubAction;
            }, $normalizedAction['children']);

            return $normalizedAction;
        }, $actions);

        return JsonOutput::success()->withContent('actions', $normalizedActions)->write($response);
    }

    /**
     * Update action
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     */
    public function update(Request $request, Response $response) : Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        $this->logger->info(print_r($params,true));
        if (isset($params['user_ids'])) {
            $params['action_user_ids'] = $params['user_ids'];
            unset($params['user_ids']);
        }


        // Validate request.
        if (!RequestValidator::validateRequest(['action_id', 'action_name', 'action_desc', 'action_status','action_user_ids'], $params, $response)) { return $response;}


        $actionId = (int)$params['action_id'];
        $action = $this->dataController->getActionWithUsers($actionId);
        if (empty($action)){
            return JsonOutput::error("Invalid Action", 400)->write($response);
        }

        if (Utility::isValidParams($params,'action_name')) {
            $action->setName(strval($params['action_name']));
        }


        if (isset($params['action_desc'])) {
            if (Utility::isValidParams($params, 'action_desc')) {
                $action->setDesc(strval($params['action_desc']));
            } else {
                $action->setDesc(null);
            }
        }

        if (Utility::isValidParams($params,'action_type')) {
            if (!empty($params['action_type'])) {
                $action->setActionType(intval($params['action_type']));
            }
        }

        if (Utility::isValidParams($params,'action_status')) {
            $action->setActionStatus(intval($params['action_status']));
        }

        $actionDueDate = $params['action_due_date'] ?? null;

        // -1 or 0 or empty string removes the action due date
        if ($actionDueDate !== null){
            if ($params['action_due_date'] !== '' && $actionDueDate !== '0' && $actionDueDate !== '-1') {

                // if integer is passed assume it is timestamp
                $dueOn = is_numeric($params['action_due_date']) ?
                            date('Y-m-d H:i:s', intval($params['action_due_date'])) :
                            strval($params['action_due_date']);
                $action->setDueOn($dueOn);

            } else {
                $action->setDueOn(null);
            }
        }


        $userArray = [];
        if (isset($params['action_user_ids'])) {
            $userIds = trim($params['action_user_ids']);
            $userArray = [];
            if (!empty($userIds)) {
                $userArray = array_map(function ($item) {
                    return (int)trim($item);
                }, explode(',', $userIds));
                $userArray = array_unique($userArray);
            }
        } else {
            // set the current users assigned to action
            foreach($action->getUsers() as $users) {
                $userArray[] = $users['user_id'];
            }
        }

        $channelId = isset($params['channel_id']) ? (int)$params['channel_id'] : null;

        try {
            $this->actionOps->updateAction($actionId,
                $action->getName(),
                $action->getDesc(),
                $action->getActionType(),
                $action->getActionStatus(),
                $action->getDueOn(),
                (int)$user->getId(),
                $userArray,
                $channelId);

            $actionWithUsers = $this->actionOps->getAction($actionId, $user->getId());
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            $code = $ex->getCode() == 0 ? 500 : $ex->getCode();
            return JsonOutput::error($ex->getMessage(), $code)->write($response);
        }

        $actionEx = $this->normalizedObjectFactory->normalizedObject($actionWithUsers);

        // Return the channel object
        return JsonOutput::success()->withContent('action', $actionEx)->write($response);
    }



    /**
     * get action info for a specific action
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function delete(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['action_id'], $params, $response)) { return $response;}

        $actionId = (int)$params['action_id'];

        try {
            $this->actionOps->deleteAction($actionId, $user->getId());
        }

        catch (ASException $ex) {
            $code = $ex->getCode();
            if ($code == 0) { $code = 500;}
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), $code)->write($response);
        }
        // Return the channel object
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
    public function move(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $queryParams = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['action_id'], $queryParams, $response)) {
            return $response;
        }

        $actionId = (int) $queryParams['action_id'];

        $params = $request->getParsedBody();
        $parentActionId = isset($params['under']) ? (int)$params['under'] : null;
        $afterActionId = isset($params['after']) ? (int)$params['after'] : null;

        try {
            $this->actionOps->moveAction($user, $actionId, $parentActionId, $afterActionId);
        } catch (HttpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getHttpCode())->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function history(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['action_id'], $queryParams, $response)) {
            return $response;
        }

        $actionId = (int) $queryParams['action_id'];

        $history = $this->dataController->findActionHistory($actionId);

        $normalizedHistory = array_map(function ($entry) {
            return $this->normalizedObjectFactory->normalizedObject($entry);
        }, $history);

        return JsonOutput::success()->withContent('history', $normalizedHistory)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $channelId = (int) $queryParams['channel_id'];
        $searchQuery = $queryParams['search'];

        $results = $this->searchDataController->searchActionsForMention($searchQuery, $channelId);

        return JsonOutput::success()->withContent('results', $results)->write($response);
    }


}