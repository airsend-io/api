<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\ChannelGroup;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\GroupDeletedEvent;
use CodeLathe\Core\Messaging\Events\GroupUpdatedEvent;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class ChannelGroupManager extends ManagerBase
{

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ChannelGroupOperations
     */
    protected $channelGroupOps;

    /**
     * @var EventManager
     */
    protected $eventHandler;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    public function __construct(DataController $dataController,
                                ChannelGroupOperations $channelGroupOps,
                                EventManager $eventManager,
                                NormalizedObjectFactory $objectFactory)
    {
        $this->dataController = $dataController;
        $this->channelGroupOps = $channelGroupOps;
        $this->eventHandler = $eventManager;
        $this->objectFactory = $objectFactory;
    }

    public function list(Request $request, Response $response): Response
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $groups = $this->channelGroupOps->listGroupsForUser($user->getId());

        return JsonOutput::success()->withContent('groups', $groups)->write($response);
    }

    public function create(Request $request, Response $response): Response
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // name is required
        $name = $params['name'] ?? null;
        if (empty($name)) {
            return JsonOutput::error("Group name is required", 422)->write($response);
        }

        // a list of channel id's can be provided.
        $channels = $params['channels'] ?? null;
        if ($channels !== null) {
            $channels = explode(',', $channels);
            $channels = array_map(function ($item) {
                return (int)trim($item);
            }, $channels);
        }

        $channelGroup = $this->channelGroupOps->createGroup($name, $user->getId(), $channels ?? []);

        $normalizedGroup = $this->objectFactory->normalizedObject($channelGroup);

        return JsonOutput::success()->withContent('channel_group', $normalizedGroup)->write($response);
    }

    public function update(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // id is required
        $groupId = $params['channel_group_id'] ?? null;
        if ($groupId === null) {
            return JsonOutput::error("channel_group_id is required", 422)->write($response);
        }

        // name is required
        $name = $params['name'] ?? null;
        if (empty($name)) {
            return JsonOutput::error("Group name is required", 422)->write($response);
        }

        // the group must exist
        $groupId = (int) $groupId;
        $group = $this->dataController->findChannelGroupById($groupId);
        if ($group === null) {
            return JsonOutput::error("Group $groupId not found.", 404)->write($response);
        }

        // the requester user must be the owner of the group
        if ($group->getUserId() !== $user->getId()) {
            return JsonOutput::error("You're not the owner of this group", 403)->write($response);
        }

        $newGroup = $this->dataController->updateChannelGroup($groupId, $name);

        // Raise an event
        $event = new GroupUpdatedEvent($user->getId(), $newGroup->getId());
        $this->eventHandler->publishEvent($event);

        $normalizedGroup = $this->objectFactory->normalizedObject($newGroup);

        return JsonOutput::success()->withContent('channel_group', $normalizedGroup)->write($response);
    }

    public function delete(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // id is required
        $groupId = $params['channel_group_id'] ?? null;
        if ($groupId === null) {
            return JsonOutput::error("channel_group_id is required", 422)->write($response);
        }

        // the group must exist
        $groupId = (int) $groupId;
        $group = $this->dataController->findChannelGroupById($groupId);
        if ($group === null) {
            return JsonOutput::error("Group $groupId not found.", 404)->write($response);
        }

        // the requester user must be the owner of the group
        if ($group->getUserId() !== $user->getId()) {
            return JsonOutput::error("You're not the owner of this group", 403)->write($response);
        }

        $this->dataController->deleteChannelGroup($groupId);

        // Raise an event
        $event = new GroupDeletedEvent($user->getId(), $groupId);
        $this->eventHandler->publishEvent($event);

        return JsonOutput::success(204)->write($response);

    }

    public function move(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $groupId = trim($params['group_id'] ?? '');
        if (empty($groupId)) {
            return JsonOutput::error("Group id is required", 422)->write($response);
        }

        if (preg_match('/^[0-9]+$/', $groupId)) {

            // if groupId is an integer, it must exist on database
            $groupId = (int) $groupId;
            if (!$this->dataController->channelGroupExists($groupId)) {
                return JsonOutput::error("Invalid group id", 404)->write($response);
            }

        } else {

            // if groupId is not an integer, it must be an virtual group
            if (!in_array($groupId, ChannelGroupOperations::VIRTUAL_GROUPS)) {
                return JsonOutput::error("Invalid group id", 404)->write($response);
            }

        }
        $after = trim($params['after'] ?? '');
        if ($after === '') {
            return JsonOutput::error("After id is required", 422)->write($response);
        }

        if (preg_match('/^[0-9]+$/', $after)) {

            // if after id is an integer, it must exist on database or be zero
            $after = (int) $after;
            if ($after !== 0 && !$this->dataController->channelGroupExists($after)) {
                return JsonOutput::error("Invalid after id", 404)->write($response);
            }

        } else {

            // if after id is not an integer, it must be an virtual group
            if (!in_array($after, ChannelGroupOperations::VIRTUAL_GROUPS)) {
                return JsonOutput::error("Invalid after id", 404)->write($response);
            }

        }

        $this->channelGroupOps->move($user->getId(), $groupId, $after);

        return JsonOutput::success(204)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws \CodeLathe\Core\Exception\HttpException
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     * @throws \CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException
     */
    public function add(Request $request, Response $response): Response
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        $channelId = (int)($params['channel_id'] ?? 0);
        if ($channelId === 0) {
            return JsonOutput::error("Channel id is required", 422)->write($response);
        }
        $channel = $this->dataController->getChannelById($channelId);
        if ($channel === null) {
            return JsonOutput::error("Channel not found", 404)->write($response);
        }
        if (!$this->dataController->isChannelMember($channelId, $user->getId())) {
            return JsonOutput::error("Channel not found", 404)->write($response);
        }

        $groupId = (int)($params['channel_group_id'] ?? 0);
        if ($groupId === 0) {
            return JsonOutput::error("Group id is required", 404)->write($response);
        }
        $this->channelGroupOps->addChannelToGroup($user, $groupId, $channelId);
        return JsonOutput::success(201)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws \CodeLathe\Core\Exception\HttpException
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     * @throws \CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException
     */
    public function remove(Request $request, Response $response): Response
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        $channelId = (int)($params['channel_id'] ?? 0);
        if ($channelId === 0) {
            return JsonOutput::error("Channel id is required", 422)->write($response);
        }

        $groupId = (int)($params['channel_group_id'] ?? 0);
        if ($groupId === 0) {
            return JsonOutput::error("Group id is required", 404)->write($response);
        }

        $this->channelGroupOps->removeChannelFromGroup($user, $groupId, $channelId);
        return JsonOutput::success(204)->write($response);
    }

    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}