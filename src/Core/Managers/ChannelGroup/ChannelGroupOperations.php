<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\ChannelGroup;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelGroupOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\HttpException;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChannelUpdateEvent;
use CodeLathe\Core\Messaging\Events\GroupCreatedEvent;
use CodeLathe\Core\Messaging\Events\GroupReorderEvent;
use CodeLathe\Core\Objects\ChannelGroup;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObject;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use phpDocumentor\Reflection\Types\Static_;

class ChannelGroupOperations
{

    public const VIRTUAL_GROUPS = [
        'all',
        'dm'
    ];

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;
    /**
     * @var EventManager
     */
    private $eventHandler;

    public function __construct(DataController $dataController, NormalizedObjectFactory $objectFactory, EventManager $eventManager)
    {
        $this->dataController = $dataController;
        $this->objectFactory = $objectFactory;
        $this->eventHandler = $eventManager;
    }

    /**
     * @param string $name
     * @param int $userId
     * @param int[] $channels
     * @return ChannelGroup
     */
    public function createGroup(string $name, int $userId, array $channels): ChannelGroup
    {

        $channelGroup = $this->dataController->createChannelGroup($name, $userId);
        foreach ($channels as $channelId) {
            $this->dataController->addChannelToGroup($channelId, $userId, $channelGroup->getId());
        }

        // Raise an event
        $event = new GroupCreatedEvent($userId, $channelGroup->getId());
        $this->eventHandler->publishEvent($event);

        return $channelGroup;
    }

    public function positionVirtualGroups(int $userId)
    {
        foreach (static::VIRTUAL_GROUPS as $virtualGroup) {
            $id = $this->dataController->findVirtualChannelGroupId($userId, $virtualGroup);
            var_dump($id);
        }
        die;
    }

    /**
     * @param int $userId
     * @return ChannelGroup[]
     * @throws DatabaseException
     */
    public function listGroupsForUser(int $userId): array
    {
        $orderedGroups = $this->dataController->findChannelGroupsForUser($userId);

        // convert to output
        $output = [];
        foreach ($orderedGroups as $group) {
            $key = $group->isVirtual() ? $group->getName() : $group->getId();
            $output[$key] = $this->objectFactory->normalizedObject($group);
        }

        // add unordered virtual groups
        foreach (static::VIRTUAL_GROUPS as $virtualGroup) {

            // only add virtual channels that aren't ordered yet
            if (!isset($output[$virtualGroup])) {

                $output[] = new NormalizedObject([
                    'id' => $virtualGroup,
                    'name' => I18n::get("channel_groups.$virtualGroup"),
                    'virtual' => true,
                ]);
            }
        }

        return array_values($output);
    }

    /**
     * @param int $userId
     * @param int|string $groupId
     * @param int|string $after
     */
    public function move(int $userId, $groupId, $after): void
    {

        // put everything inside a transaction
        $this->dataController->beginTransaction();

        // group id is a virtual group? Ensure that it's positioned and get it's numeric id
        if (!is_int($groupId)) {
            // check if the virtual group already have a position
            $virtualKey = $groupId;
            $groupId = $this->dataController->findVirtualChannelGroupId($userId, $virtualKey);

            // if not, insert it...
            if ($groupId === null) {
                $group = $this->dataController->createChannelGroup($virtualKey, $userId, true);
                $groupId = $group->getId();
            }
        }

        // after is a virtual group? Ensure that it's positioned and get it's numeric id
        if (!is_int($after)) {

            // ensure that all virtual group before this one is positioned (have an id on the database)
            $virtualKey = $after;
            foreach (static::VIRTUAL_GROUPS as $virtualGroup) {
                $after = $this->dataController->findVirtualChannelGroupId($userId, $virtualGroup);
                if ($after !== null) {
                    continue;
                }
                $group = $this->dataController->createChannelGroup($virtualGroup, $userId, true);
                $after = $group->getId();
                if ($virtualGroup === $virtualKey) {
                    break;
                }
            }
        }

        // move the group to the correct position
        $this->dataController->moveChannelGroup($userId, $groupId, $after);

        // commit the transaction
        $this->dataController->commit();

        // Raise an event
        $event = new GroupReorderEvent($userId);
        $this->eventHandler->publishEvent($event);

    }

    /**
     * @param User $user
     * @param int $groupId
     * @param int $channelId
     * @return bool
     * @throws HttpException
     * @throws DatabaseException
     */
    public function addChannelToGroup(User $user, int $groupId, int $channelId): bool
    {
        $group = $this->dataController->findChannelGroupById($groupId);
        if ($group === null) {

            throw new ChannelGroupOpException(404,"Channel group not found");
        }
        if ($group->isVirtual()) {
            throw new ChannelGroupOpException(422,"Cannot add channels to a virtual group");
        }
        if ($group->getUserId() !== $user->getId()) {
            throw new ChannelGroupOpException(404,"Channel group not found");
        }

        $this->dataController->addChannelToGroup($channelId, $user->getId(), $groupId);

        $channel = $this->dataController->getChannelById($channelId);
        $this->eventHandler->publishEvent(new ChannelUpdateEvent($channel, $user, true));
        return true;
    }


    /**
     * @param User $user
     * @param int $groupId
     * @param int $channelId
     * @return bool
     * @throws HttpException
     * @throws DatabaseException
     */
    public function removeChannelFromGroup(User $user, int $groupId, int $channelId): bool
    {
        $channelUser = $this->dataController->getUserChannel($channelId, $user->getId());
        if ($channelId === null || $channelUser->getGroupId() !== $groupId) {
            throw new ChannelGroupOpException(422,"Channel is not part of this group");
        }

        $this->dataController->removeChannelFromGroup($channelId, $user->getId());
        $channel = $this->dataController->getChannelById($channelId);
        $this->eventHandler->publishEvent(new ChannelUpdateEvent($channel, $user));
        return true;
    }

}