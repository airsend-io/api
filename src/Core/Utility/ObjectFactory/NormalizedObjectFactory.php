<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Utility\ObjectFactory;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\UnconfiguredPolicyException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Exception\UnsupportedPolicyTypeException;
use CodeLathe\Core\Exception\UserMalformedException;
use CodeLathe\Core\Managers\ChannelGroup\ChannelGroupOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Messaging\Events\AbstractTeamChannelEvent;
use CodeLathe\Core\Messaging\Events\AbstractTeamMemberEvent;
use CodeLathe\Core\Messaging\Events\ActionCreatedEvent;
use CodeLathe\Core\Messaging\Events\ActionDeletedEvent;
use CodeLathe\Core\Messaging\Events\ActionMovedEvent;
use CodeLathe\Core\Messaging\Events\ActionUpdatedEvent;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\ChannelCreatedEvent;
use CodeLathe\Core\Messaging\Events\ChannelJoinUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChannelReadStatusUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChannelRemovedEvent;
use CodeLathe\Core\Messaging\Events\ChannelUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChatDeletedEvent;
use CodeLathe\Core\Messaging\Events\ChatPostedEvent;
use CodeLathe\Core\Messaging\Events\ChatUpdatedEvent;
use CodeLathe\Core\Messaging\Events\FSAddFileEvent;
use CodeLathe\Core\Messaging\Events\FSCopyEvent;
use CodeLathe\Core\Messaging\Events\FSEvent;
use CodeLathe\Core\Messaging\Events\FSUpdateEvent;
use CodeLathe\Core\Messaging\Events\GroupCreatedEvent;
use CodeLathe\Core\Messaging\Events\GroupDeletedEvent;
use CodeLathe\Core\Messaging\Events\GroupReorderEvent;
use CodeLathe\Core\Messaging\Events\GroupUpdatedEvent;
use CodeLathe\Core\Messaging\Events\LockAcquireEvent;
use CodeLathe\Core\Messaging\Events\LockReleaseEvent;
use CodeLathe\Core\Messaging\Events\MeetingInviteEvent;
use CodeLathe\Core\Messaging\Events\TeamAddChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamAddMemberEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveMemberEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateMemberEvent;
use CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent;
use CodeLathe\Core\Messaging\Events\UserOfflineEvent;
use CodeLathe\Core\Messaging\Events\UserOnlineEvent;
use CodeLathe\Core\Messaging\Events\UserProfileUpdateEvent;
use CodeLathe\Core\Messaging\Events\UserRemovedFromChannelEvent;
use CodeLathe\Core\Messaging\Events\UserTypingEvent;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\ActionHistory;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\Call;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelGroup;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\ContactForm;
use CodeLathe\Core\Objects\FileSystemObject;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Policy\Policies\StorageQuota;
use CodeLathe\Core\Policy\Policies\TeamAnnouncements;
use CodeLathe\Core\Policy\Policies\TeamTagColor;
use CodeLathe\Core\Policy\PolicyManager;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Command\CommandService;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class NormalizedObjectFactory
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

    /**0
     * @var RtmOperations
     */
    protected $rtmOps;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var CommandService
     */
    protected $commandService;

    /**
     * @var PolicyManager
     */
    protected $policyManager;

    /**
     * @var FileOperations
     */
    protected $fileOperations;

    /**
     * NormalizedObjectFactory constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param RtmOperations $rtmOps
     * @param CacheItemPoolInterface $cache
     * @param CommandService $commandService
     * @param PolicyManager $policyManager
     * @param FileOperations $fileOperations
     */
    public function __construct (DataController $dataController,
                                LoggerInterface $logger,
                                FileOperations $fOps,
                                RtmOperations $rtmOps,
                                CacheItemPoolInterface $cache,
                                CommandService $commandService,
                                PolicyManager $policyManager,
                                FileOperations $fileOperations
    )
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->rtmOps = $rtmOps;
        $this->cache = $cache;
        $this->commandService = $commandService;
        $this->policyManager = $policyManager;
        $this->fileOperations = $fileOperations;
    }

    /**
     * Get channels associated with supplied user id
     *
     * @param User $user
     * @param bool $excludeClosed
     * @return array
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function getNormalizedChannelsForUser(User $user, bool $excludeClosed=true): array
    {
        $channels = array();
        foreach($this->dataController->getChannelsForUser((int)$user->getId()) as $channelRecord) {
            $channel = Channel::withDBData($channelRecord);
            if ($excludeClosed && $channel->getChannelStatus() == Channel::CHANNEL_STATUS_CLOSED) {
                continue;
            }
            $channels[] = $this->normalizedObject($channel, $user);
        }
        return $channels;
    }

    /**
     * Get channel user list for a channel
     * @param Channel $channel
     * @param User|null $loggedUser
     * @return array of Normalized User objects
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function getNormalizedUsersForChannel(Channel $channel, ?User $loggedUser): array
    {
        $users = [];

        foreach ($this->dataController->getUsersForChannel((int)$channel->getId()) as $userRec) {

            $user = User::withDBData($userRec);

            $normalizedObject = $this->normalizedObject($user);
            $normalizedObject->addInt('user_role', (int)$userRec['channel_user_role']);

            // include the team member flag
            $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
            if (!$team->isSelfTeam()) {
                $normalizedObject->addBool('is_team_member', $this->dataController->isTeamMember($team->getId(), $user->getId()));
            }

            // if the member is not the owner of the channel, we don't include email on the payload
            if (! ($channel->getRequireJoinApproval() === true && $channel->getAllowExternalRead() === false)) {
                $normalizedObject->remove('email');
            }

            $users[] = $normalizedObject;
        }

        return $users;
    }

    /**
     *
     * Generate a normalized object. This is useful for sending data to external client
     * and all information is normalized for consumption
     *
     * @param ObjectInterface $object
     * @param User|null $user User object , if passed in can add contextual information to the normalization process
     * @param bool $abbreviated
     * @return NormalizedObject
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function normalizedObject(ObjectInterface $object, User $user=null, bool $abbreviated=true): NormalizedObject
    {
        //$this->logger->info(get_class($object));
        if (get_class($object) == Channel::class) {
            return $this->normalizedChannel($object, $user, $abbreviated);
        }else if (get_class($object) == User::class) {
            return $this->normalizedUser($object, $abbreviated);
        }else if (get_class($object) == Message::class) {
            return $this->normalizedMessage($object);
        }else if (get_class($object) == Action::class) {
            return $this->normalizedAction($object);
        }else if ($object instanceof ContactForm) {
            return $this->normalizedContactForm($object);
        }else if ($object instanceof ChannelGroup) {
            return $this->normalizedChannelGroup($object);
        } else if ($object instanceof FileSystemObject) {
            return $this->normalizedFileSystemObject($object);
        } elseif ($object instanceof ActionHistory) {
            return $this->normalizedActionHistory($object);
        } elseif ($object instanceof Call) {
            return $this->normalizedCall($object);
        } elseif ($object instanceof Team) {
            return $this->normalizedTeam($object, $user, $abbreviated);
        } elseif ($object instanceof TeamUser) {
            return $this->normalizedTeamMember($object);
        }


        // This is probably not the intention. Make it an error to get visibility
        $this->logger->error(" WARNING: NO NORMALIZATION PERFORMED FOR " . get_class($object));

        // Dont know anything about this. Just return as is
        return new NormalizedObject($object->getArray());
    }

    /**
     *
     * Normalize an event to a standard form to send to clients
     *
     * @param ASEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function normalizeEvent(ASEvent $event): NormalizedEvent
    {
        if (get_class($event) == "CodeLathe\Core\Messaging\Events\ChannelCreatedEvent") {
            return $this->normalizeChannelCreatedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ChatPostedEvent") {
            return $this->normalizeChatPostedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ChatUpdatedEvent") {
            return $this->normalizeChatUpdatedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent") {
            return $this->normalizeUserAddedToChannelEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ChannelUpdateEvent") {
            return $this->normalizeChannelUpdateEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\UserTypingEvent") {
            return $this->normalizeUserTyping($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ChatDeletedEvent") {
            return $this->normalizeChatDeletedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\UserOfflineEvent") {
            return $this->normalizeUserOfflineEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\UserOnlineEvent") {
            return $this->normalizeUserOnlineEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\UserProfileUpdateEvent") {
            return $this->normalizeUserProfileUpdate($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ChannelRemovedEvent") {
            return $this->normalizeChannelRemovedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\UserRemovedFromChannelEvent") {
            return $this->normalizeUserRemovedFromChannelEvent($event);
        } else if ($event instanceof FSEvent) { // any fs event
            return $this->normalizeFSEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ActionCreatedEvent") {
            return $this->normalizeActionCreatedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ActionUpdatedEvent") {
            return $this->normalizeActionUpdatedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\ActionDeletedEvent") {
            return $this->normalizeActionDeletedEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\LockAcquireEvent") {
            return $this->normalizeLockAcquireEvent($event);
        } else if (get_class($event) == "CodeLathe\Core\Messaging\Events\LockReleaseEvent") {
            return $this->normalizeLockReleaseEvent($event);
        } else if (get_class($event) == ChannelReadStatusUpdateEvent::class) {
            return $this->normalizeChannelReadStatusUpdateEvent($event);
        } elseif ($event instanceof ChannelJoinUpdateEvent) {
            return $this->normalizeChannelJoinEvent($event);
        } elseif ($event instanceof ActionMovedEvent) {
            return $this->normalizeActionMovedEvent($event);
        } elseif ($event instanceof GroupCreatedEvent) {
            return $this->normalizeGroupCreatedEvent($event);
        } elseif ($event instanceof GroupUpdatedEvent) {
            return $this->normalizeGroupUpdatedEvent($event);
        } elseif ($event instanceof GroupDeletedEvent) {
            return $this->normalizeGroupDeletedEvent($event);
        } elseif ($event instanceof GroupReorderEvent) {
            return $this->normalizeGroupReorderEvent($event);
        } elseif ($event instanceof TeamAddChannelEvent) {
            return $this->normalizeTeamAddChannelEvent($event);
        } elseif ($event instanceof TeamUpdateChannelEvent) {
            return $this->normalizeTeamUpdateChannelEvent($event);
        } elseif ($event instanceof TeamRemoveChannelEvent) {
            return $this->normalizeTeamRemoveChannelEvent($event);
        } elseif ($event instanceof TeamAddMemberEvent) {
            return $this->normalizeTeamAddMemberEvent($event);
        } elseif ($event instanceof TeamUpdateMemberEvent) {
            return $this->normalizeTeamUpdateMemberEvent($event);
        } elseif ($event instanceof TeamRemoveMemberEvent) {
            return $this->normalizeTeamRemoveMemberEvent($event);
        }

        // This is probably not the intention. Make it an error to get visibility
        //$this->logger->error(" WARNING: NO NORMALIZATION PERFORMED FOR EVENT " . get_class($event));

        // TODO - Trigger a fatal error here, so we prevent non-normalized events to hit frontend...

        // Return it as is
        return new NormalizedEvent($event::eventName(), $event->getPayloadArray());
    }

    /**
     * Normalizes an event considering the user that the event is being sent to
     *
     * @param ASEvent $event
     * @param User $user
     * @return NormalizedEvent
     */
    public function normalizeEventForUser(ASEvent $event, User $user): NormalizedEvent
    {
        if ($event instanceof TeamUpdateEvent) {
            return $this->normalizeTeamUpdateEvent($event, $user);
        }
    }

    /**
     * Check if an event object requires a user to be normalized
     *
     * @param ASEvent $event
     * @return bool
     */
    public function isEventNormalizedByUser(ASEvent $event): bool
    {
        $normalizedByUserList = [
            TeamUpdateEvent::class
        ];

        return in_array(get_class($event), $normalizedByUserList);
    }


    /**
     * @param Alert $alert
     * @return NormalizedEvent
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function normalizeAlertToEvent(Alert $alert): NormalizedEvent
    {
        switch ($alert->getContextType()){
            case Alert::CONTEXT_TYPE_MESSAGE:
                return $this->normalizeAlertMessageToEvent($alert);
            case Alert::CONTEXT_TYPE_ACTION:
                return $this->normalizeAlertActionToEvent($alert);
            case Alert::CONTEXT_TYPE_CHANNEL:
                return $this->normalizeAlertChannelToEvent($alert);
        }

        $this->logger->error(" WARNING: NO NORMALIZATION PERFORMED FOR ALERT TYPE " . $alert->getContextType());

        return new NormalizedEvent('alert.notification', ['event_payload' => $alert->getAlertText()]);
    }

    /**
     * @param Alert $alert
     * @return NormalizedObject
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function normalizeAlertToObject(Alert $alert): NormalizedObject
    {
        switch ($alert->getContextType()) {
            case Alert::CONTEXT_TYPE_MESSAGE:
                return $this->normalizeAlertMessageToObject($alert);
            case Alert::CONTEXT_TYPE_ACTION:
                return $this->normalizeAlertActionToObject($alert);
            case Alert::CONTEXT_TYPE_CHANNEL:
                return $this->normalizeAlertChannelToObject($alert);
        }

        $this->logger->error(" WARNING: NO NORMALIZATION PERFORMED FOR ALERT TYPE " . $alert->getContextType());

        return new NormalizedObject( ['event_payload' => $alert->getAlertText()]);
    }

    /**
     * @param Alert $alert
     * @return NormalizedEvent
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function normalizeAlertMessageToEvent(Alert $alert): NormalizedEvent
    {
        //$this->logger->info(__FUNCTION__ . " Normalizing alert " . print_r($alert->getArray(),true));
        $from = [];
        foreach ($alert->getIssuers() as $issuer) {
            $fromUser = $this->dataController->getUserById((int)$issuer->getUserId());

            if (!empty($fromUser)) {
                $from[] = $this->normalizedUser($fromUser);
            }
        }
        $message = $this->dataController->getMessageById((int)$alert->getContextId());
        $channel = "Unknown";
        if (!empty($message)) {
            $channel = $message->getChannelId();
        }

        // Alert contains
        return new NormalizedEvent('alert.notification',
            [
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getContextType(),
                'from' => $from,
                'message_id' => $alert->getContextId(),
                'alert_text' => $alert->getAlertText(),
                'channel_id' => $channel,
                'is_read' => $alert->getIsRead(),
                'created_on' => $alert->getCreatedOn()
            ]);
    }

    public function normalizeAlertActionToEvent(Alert $alert): NormalizedEvent
    {
        //$this->logger->info(__FUNCTION__ . " Normalizing alert " . print_r($alert->getArray(),true));
        $from = [];
        foreach ($alert->getIssuers() as $issuer) {
            $fromUser = $this->dataController->getUserById((int)$issuer->getUserId());

            if (!empty($fromUser)) {
                $from[] = $this->normalizedUser($fromUser);
            }
        }
        $action = $this->dataController->getActionById((int)$alert->getContextId());
        $channel = "Unknown";
        if (!empty($action)) {
            $channel = $action->getChannelId();
        }

        // Alert contains
        return new NormalizedEvent('alert.notification',
            [
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getContextType(),
                'from' => $from,
                'action_id' => $alert->getContextId(),
                'alert_text' => $alert->getAlertText(),
                'channel_id' => $channel,
                'is_read' => $alert->getIsRead(),
                'created_on' => $alert->getCreatedOn()
            ]);
    }


    /**
     * @param Alert $alert
     * @return NormalizedObject
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    private function normalizeAlertActionToObject(Alert $alert): NormalizedObject
    {
        //$this->logger->info(__FUNCTION__ . " Normalizing alert " . print_r($alert->getArray(),true));
        $from = [];
        foreach ($alert->getIssuers() as $issuer) {
            $fromUser = $this->dataController->getUserById((int)$issuer->getUserId());

            if (!empty($fromUser)) {
                $from[] = $this->normalizedUser($fromUser);
            }
        }
        $action = $this->dataController->getActionById((int)$alert->getContextId());
        $channel = "Unknown";
        if (!empty($action)) {
            $channel = $action->getChannelId();
        }

        // Alert contains
        return new NormalizedObject(
            [
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getContextType(),
                'from' => $from,
                'action_id' => $alert->getContextId(),
                'alert_text' => $alert->getAlertText(),
                'channel_id' => $channel,
                'is_read' => $alert->getIsRead(),
                'created_on' => $alert->getCreatedOn()
            ]);
    }

    /**
     * @param Alert $alert
     * @return NormalizedObject
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function normalizeAlertMessageToObject(Alert $alert): NormalizedObject
    {
        //$this->logger->info(__FUNCTION__ . " Normalizing alert " . print_r($alert->getArray(),true));
        $from = [];
        foreach ($alert->getIssuers() as $issuer) {
            $fromUser = $this->dataController->getUserById((int)$issuer->getUserId());

            if (!empty($fromUser)) {
                $from[] = $this->normalizedUser($fromUser);
            }
        }
        $message = $this->dataController->getMessageById((int)$alert->getContextId());
        $channel = "Unknown";
        if (!empty($message)) {
            $channel = $message->getChannelId();
        }

        // Alert contains
        return new NormalizedObject(
            [
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getContextType(),
                'from' => $from,
                'message_id' => $alert->getContextId(),
                'alert_text' => $alert->getAlertText(),
                'channel_id' => $channel,
                'is_read' => $alert->getIsRead(),
                'created_on' => $alert->getCreatedOn()
            ]);
    }

    /**
     * @param Channel $channel
     * @param User|null $user
     * @param bool $abbreviated
     * @return NormalizedObject
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function normalizedChannel(Channel $channel, User $user=null, bool $abbreviated=true): NormalizedObject
    {
        $channelArray = $this->getCachedChannelInfo($channel,$user,$abbreviated);

        $channelArray['last_active_on'] = $channel->getLastActiveOn(); // update this.
        $channelEx = new NormalizedObject($channelArray);

        // More realtime data has to be added and not cached.
        $userId = $user instanceof User ? $user->getId() : null;
        $latestMessage = $this->getLatestChannelMessage($channel->getId(), $userId);
        $channelEx->addObjectPayload('latest_message', $latestMessage);

        $muted = false;

        if (!empty($user)) {

            // add the slash commands hints
            $channelEx->addArray('commands', $this->commandService->getAvailableCommands($channel, $user));

            // grab the relation between the channel and the logged user
            /** @var ChannelUser $channelUser */
            $channelUser = $this->dataController->getUserChannel((int)$channel->getId(), (int)$user->getId());
            if (!empty($channelUser)) {

                $unreadMsgCount = $this->dataController->getUnReadMessageCount($channel->getId(), $user->getId(), $channelUser->getReadWatermarkId() ?? 0);
                $channelEx->addInt('unread_count', $unreadMsgCount);

                $channelEx->addInt('read_watermark_id', $channelUser->getReadWatermarkId() ?? 0);
                $muted = $channelUser->getMuted();

                $blockedOn = $channelUser->getBlockedOn();
                if ($blockedOn !== null) {
                    $channelEx->addPOD('blocked_on', $blockedOn);
                }

                if ($channelUser->getUserRole() >= ChannelUser::CHANNEL_USER_ROLE_MANAGER) {
                    $channelEx->addArray('pending_members', $this->getNormalizedPendingUsersForChannel($channel, $user));
                }
            }
            else { // public channel guest user possibly
                $channelEx->addBool('muted', false);
            }

            $channelEx->addBool('require_join_approval', $channel->getRequireJoinApproval());
            $channelEx->addBool('allow_external_read', $channel->getAllowExternalRead());
        }

        $dmMuted = !$channel->getOneOnOneApproved() && $this->dataController->getDMSentByInviterCount($channel->getId()) > ChannelUser::FREE_MESSAGES_ON_DM;

        $channelEx->addBool('muted', $muted || $dmMuted);

        $actionCount = $this->dataController->getActionCount($channel->getId());
        $channelEx->addInt('action_count', $actionCount);

        $messageCount = $this->dataController->getMessageCountByChannelId($channel->getId());
        $channelEx->addInt('message_count', $messageCount);

        $readStatus = $this->dataController->getReadStatusForChannel($channel->getId());
        $channelEx->addArray('read_status', $readStatus);

        // add the public invite link to the channel if it exists
        $hash = $this->dataController->findShortUrlHashForResource('ChannelInvite', (string) $channel->getId());
        if (!empty($hash)) {
            $channelEx->addPOD('public_url', StringUtility::generateShortUrlFromHash($hash));
        }

        if (!empty($channelUser) && !empty($user) && $user->getUserRole() !== User::USER_ROLE_PUBLIC) {
            $channelEx->addInt('user_role', $channelUser->getUserRole());
            $channelEx->addBool('is_favorite', $channelUser->getIsFavorite());
            if (($groupId = $channelUser->getGroupId()) !== null) {
                $channelEx->addInt('channel_group_id', $channelUser->getGroupId());
            }
            else {
                $channelEx->addInt('channel_group_id', -1);
            }
        }

        $meeting = $this->getMeetingInfo($channel->getId());
        if (!empty($meeting)) {
            $channelEx->addArray('meeting', $meeting);
        }

        $channelEx->addBool('open_team_join', $channel->isOpenForTeamJoin());

        return $channelEx;
    }

    /**
     * Get the active meeting info
     * @param int $channelId
     * @return array|null
     * @throws InvalidArgumentException
     */
    private function getMeetingInfo(int $channelId) : ?array
    {
        $item = $this->cache->getItem('meeting.' . $channelId);
        if ($item->isHit()) {
            // Valid meeting in progress
            return (array)$item->get();
        }

        return null;
    }


    /**
     *
     * Normalize a channel object
     *
     * @param Channel $channel
     * @param User|null $user
     * @param bool $abbreviated
     * @return array
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function getCacheableChannelData(Channel $channel, User $user=null, bool $abbreviated=true): array
    {

        $data = $this->cleanedupChannelArray($channel, $abbreviated);

        // Given a basic channel object, we want to build a full structure
        $channelEx = new NormalizedObject($data);

        // temporarily add created_by as a clone of owned_by, to prevent ui break
        $channelEx->addInt('created_by', $channelEx->getValueForKey('owned_by'));

        if ((!empty($user)) && ($user->getUserRole() !== User::USER_ROLE_PUBLIC)) {
            $memberEmail = $this->dataController->getMemberEmailForChannel($channel->getId(), $user->getId());
            if (!isset($memberEmail)) {
                $memberEmail = "Not setup";
            }
            $channelEx->addPOD('member_email', $memberEmail);
        }
        $channelEx->addArray('members', $this->getNormalizedUsersForChannel($channel, $user));

        // add channel_roots and fs stats
        try {
            $channelRoots = $this->fOps->getChannelRoots($channel);
        } catch (FSOpException $e) {
            // just log and ignore, to avoid channel listing break
            $this->logger->critical("SERVER ERROR: Failed to get channel roots: {$e->getMessage()}");
            $channelRoots = [];
        }
        $channelRootsArray = [];

        $totalFSCount = 0; // all files and folders
        $totalFSSize = 0; // total size (files + versions + sidecars)

        // entire storage
        $totalFileCount = 0; // only files
        $totalFileSize = 0; // only files
        $totalFolderCount = 0; // only folders
        $totalSidecarCount = 0; // only sidecars
        $totalSidecarSize = 0; // only sidecars
        $totalVersionsCount = 0; // only versions
        $totalVersionsSize = 0; // only versions

        // same thing for wiki
        $wikiFileCount = 0; // only files
        $wikiFileSize = 0; // only files
        $wikiFolderCount = 0; // only folders
        $wikiSidecarCount = 0; // only sidecars
        $wikiSidecarSize = 0; // only sidecars
        $wikiVersionsCount = 0; // only versions
        $wikiVersionsSize = 0; // only versions

        foreach ($channelRoots as $root) {
            try {
                $props = $this->fOps->folderProps($root->getPhysicalPath());

                $totalFSCount += $props->getTotalCount();
                $totalFSSize += $props->getTotalSize();

                $totalFileCount += $props->getFileCount();
                $totalFileSize += $props->getFileSize();
                $totalFolderCount += $props->getFolderCount();
                $totalSidecarCount += $props->getSidecarCount();
                $totalSidecarSize += $props->getSidecarSize();
                $totalVersionsCount += $props->getVersionsCount();
                $totalVersionsSize += $props->getVersionsSize();

                if ($root->getType() === 'wiki') {
                    $wikiFileCount += $totalFileCount;
                    $wikiFileSize += $totalFileSize;
                    $wikiFolderCount += $totalFolderCount;
                    $wikiSidecarCount += $totalSidecarCount;
                    $wikiSidecarSize += $totalSidecarSize;
                    $wikiVersionsCount += $totalVersionsCount;
                    $wikiVersionsSize += $totalVersionsSize;
                }


                $channelRootsArray[] = [
                    'type' => $root->getType() ?? '',
                    'location' => rtrim($root->getRelativePath() ?? '', '/'),
                ];
            } catch (NotFoundException $e) {
                continue;
            }
        }
        $channelEx->addArray('channel_roots', $channelRootsArray);

        // add fs props
        $channelEx->addInt('total_fs_count', $totalFSCount);
        $channelEx->addInt('total_fs_size', $totalFSSize);
        $channelEx->addInt('total_file_count', $totalFileCount);
        $channelEx->addInt('total_file_size', $totalFileSize);
        $channelEx->addInt('total_folder_count', $totalFolderCount);
        $channelEx->addInt('total_sidecar_count', $totalSidecarCount);
        $channelEx->addInt('total_sidecar_size', $totalSidecarSize);
        $channelEx->addInt('total_versions_count', $totalVersionsCount);
        $channelEx->addInt('total_versions_size', $totalVersionsSize);

        $channelEx->addInt('wiki_file_count', $wikiFileCount);
        $channelEx->addInt('wiki_file_size', $wikiFileSize);
        $channelEx->addInt('wiki_folder_count', $wikiFolderCount);
        $channelEx->addInt('wiki_sidecar_count', $wikiSidecarCount);
        $channelEx->addInt('wiki_sidecar_size', $wikiSidecarSize);
        $channelEx->addInt('wiki_versions_count', $wikiVersionsCount);
        $channelEx->addInt('wiki_versions_size', $wikiVersionsSize);

        // add team_id
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if ($team instanceof Team && !$team->isSelfTeam()) {
            $channelEx->addInt('team_id', $team->getId());
        }

        return $channelEx->getArray();

    }

    /**
     * @param Channel $channel
     * @param User $user
     * @param bool $abbreviated
     * @return array|null
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function getCachedChannelInfo(Channel $channel, ?User $user, bool $abbreviated) : ?array
    {

        // skip caching for public users
        if (isset($user) && $user->getUserRole() === User::USER_ROLE_PUBLIC) {
            return $this->getCacheableChannelData($channel, $user, $abbreviated);
        }

        $key = 'normalized_channel.' . $channel->getId();
        $key .= '.' . ($user ?  $user->getId() : '');
        $key .= $abbreviated ? '.abbreviated' : '';

        $cacheItem = $this->cache->getItem($key);

        if ($cacheItem->isHit()) {
            //$this->logger->debug(__FUNCTION__ . ' : Using cached channel ' . $key);

            $cacheItem->expiresAfter(3 * 24 * 3600); // extend expiration3$this->cache->save($cacheItem);
            return unserialize($cacheItem->get());
        } else {
            $this->logger->info(__FUNCTION__ . ' : Caching channel with key ' . $key);
            $data = $this->getCacheableChannelData($channel, $user, $abbreviated);
            $cacheItem->set(serialize($data));
            $cacheItem->expiresAfter(3 * 24 * 3600); // extend expiration
            $this->cache->save($cacheItem);
            return $data;
        }
    }

    /**
     * Normalize a message
     *
     * @param Message $message
     * @return NormalizedObject
     * @throws DatabaseException
     */
    private function normalizedMessage(Message $message): NormalizedObject
    {
        $messageEx = new NormalizedObject($message->getArray());
        $channel = $this->dataController->getChannelById($message->getChannelId());

        if (empty($channel)) {
            $this->logger->error('Invalid channel associated with message!' . print_r($message->getArray(),true));
            return $messageEx;
        }
        $messageEx->transform('source', function ($item) {
            return Message::MESSAGE_SOURCE_MAP[$item] ?? 'chat';
        });

        $messageEx->addPOD('channel_name', $channel->getName());
        $messageEx->addBool('one_one', $channel->getOneOnOne());

        $messageAttachments = $this->dataController->findAttachmentsForMessage($message->getId());
        $attachmentsOutput = [];
        foreach ($messageAttachments as $attachment) {
            $content = $attachment->getContent();
            if ($attachment->getContentType() === MessageAttachment::ATTACHMENT_TYPE_FILE) {
                $content['has_thumb'] = $this->fOps->hasThumb($content['path'], 120, 120);
            }
            $attachmentsOutput[] = [
                'ctp' => $attachment->getContentType(),
                'content' => $content,
            ];
        }
        $messageEx->addArray('attachments', $attachmentsOutput);

        if ($message->getMessageType() === Message::MESSAGE_TYPE_BOT) {

            try {
                $jArray = json_decode(($message->getArray())['content_text'], true);

                if (!empty($jArray)) {
                    $messageEx->addArray('content', $jArray);
                }
            } catch (\Exception $exception) {
                $this->logger->error('Invalid encoded json content in content');
            }
        }

        return $messageEx;
    }


    /**
     *
     * Send only white listed data
     *
     * @param Channel $channel
     * @param bool $abbr
     * @return array
     */
    private function cleanedupChannelArray(Channel $channel, bool $abbr) : array
    {
        $whiteList = [
            'id',
            'channel_email',
            'channel_name',
            'blurb',
            'created_on',
            'owned_by',
            'updated_on',
            'last_active_on',
            'channel_status',
            'has_logo',
            'has_background',
            'one_one',
            'one_one_approved',
            'locale',
        ];

        $whiteListExt = [
            'updated_on',
            'updated_by',
        ];

        if (!$abbr) {
            $whiteList = array_merge($whiteList, $whiteListExt);
        }

        $cArray = [];
        foreach ($channel->getArray() as $key=>$value) {
            if (in_array($key, $whiteList)) {
                $cArray[$key] = $value;
            }
        }

        return $cArray;
    }

    /**
     * Send only white listed data
     *
     * @param User $user
     * @param bool $abbr
     * @return array
     */
    private function cleanedupUserArray(User $user, bool $abbr) : array
    {

        $whiteList = [
            'id',
            'display_name',
            'last_active_on',
            'has_avatar',
            'notifications_config',
            'account_status',
            'timezone',
            'updated_on',
            'locale',
            'user_status',
            'user_status_message'
        ];

        $whiteListExt = [
            'email',
            'phone',
            'is_terms_agreed',
            'is_tour_complete',
            'approval_status',
            'lang_code',
            'data_format',
            'time_format',
            'invited_by',
            'created_on',
            'updated_by',
        ];


        if (!$abbr) {
            $whiteList = array_merge($whiteList, $whiteListExt);
        }
        $userArray = [];
        foreach ($user->getArray() as $key=>$value) {
            if (in_array($key, $whiteList)) {
                $userArray[$key] = $value;
            }
        }

        return $userArray;
    }


    /**
     * @param Action $action
     * @return array
     */
    private function cleanedupActionArray(Action $action) : array
    {
        $whiteList = [
            'id',
            'channel_id',
            'parent_id',
            'action_name',
            'action_desc',
            'action_type',
            'action_status',
            'due_on',
            'created_on',
            'created_by',
            'updated_on',
            'updated_by'
        ];

        $array = [];
        foreach ($action->getArray() as $key=>$value) {
            if (in_array($key, $whiteList)) {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * @param Action $action
     * @return NormalizedObject
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    private function normalizedAction(Action $action): NormalizedObject
    {
        $data = $this->cleanedupActionArray($action);
        $actionEx = new NormalizedObject($data);

        // add channel name
        $channel = $this->dataController->getChannelById($action->getChannelId());
        $actionEx->addPOD('channel_name', $channel->getName());

        // add children
        $children = array_map(function (Action $item) {
            return $this->normalizedAction($item);
        }, $action->getChildren());
        $actionEx->addArray('children', $children);

        // add users
        $users = array_map(function ($item) {
            $user = $this->dataController->getUserById((int)$item['user_id']);
            return $this->normalizedUser($user)->jsonSerialize();
        }, $action->getUsers());
        $actionEx->addArray('users', $users);

        $actionUsers = $action->getUserObjects();
        if (!empty($actionUsers)) {
            $nusers = [];
            foreach ($actionUsers as $user) {
               $nusers[] = $this->normalizedObject($user)->getArray();
            }
            $actionEx->addArray('users', $nusers);
        }

        return $actionEx;
    }


    /**
     * Normalize the user object with external consumables
     *
     * @param User $user
     * @param bool $abbreviated
     * @param bool $forAdmin
     * @return NormalizedObject
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws UserMalformedException
     * @throws UnconfiguredPolicyException
     * @throws UnknownPolicyEntityException
     * @throws UnsupportedPolicyException
     * @throws UnsupportedPolicyTypeException
     */
    public function normalizedUser(User $user, bool $abbreviated=true, bool $forAdmin=false): NormalizedObject
    {
        if ($forAdmin) {
            $data = $user->getArray();
            unset($data['password']);
        }
        else {
            $data = $this->cleanedupUserArray($user, $abbreviated);
        }
        $data['user_status'] = $data['user_status'] ?? 1;
        $data['user_status_message'] = $data['user_status_message'] ?? '';
        $data['online_status'] = $data['user_status'] === 0 ? false : $this->rtmOps->isUserOnline((int)$user->getId());
        $data['notifications_config'] = array_search($data['notifications_config'],
            User::NOTIFICATIONS_CONFIG_MAP);

        $userEx = new NormalizedObject($data);

        if (!$abbreviated) {
            $userRoots = array();
            if (!$this->fOps->getUserRoots($user, $userRoots)) {
                $this->logger->error("Roots not found for User " . $user->getId());
                throw new UserMalformedException("Roots not found for User " . $user->getId());
            }

            $userEx->addArray('user_fs_roots', $userRoots);

            if ($user->getUserRole() != User::USER_ROLE_SERVICE_ADMIN) {
                $props = $this->fOps->getUserQuota($user);
                $userEx->addArray('user_fs_stats', $props);
            }

        }

        // TODO - set the correct plan
        $userEx->addPOD("plan", "pro");

        return $userEx;
    }

    /**
     *
     * Get the last channel message
     *
     * @param int $channelId
     * @param int|null $userId
     * @return NormalizedObject|null
     * @throws DatabaseException
     */
    private function getLatestChannelMessage(int $channelId, ?int $userId): ?NormalizedObject
    {

        $before = null;
        if ($userId !== null) {
            $channelUser = $this->dataController->getUserChannel($channelId, $userId);
            $before = $channelUser instanceof ChannelUser ? $channelUser->getBlockedOn() : null;
        }

        $iterator = $this->dataController->getMessagesByChannelId($channelId, 1, 0, $before);
        foreach ($iterator as $rec) {
            // This is the very first (top) message
            return $this->normalizedMessage(Message::withDBData($rec));
        }
        return NULL;
    }

    /**
     * Convert Channel created event for RTM use
     *
     * @param ChannelCreatedEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    private function normalizeChannelCreatedEvent (ChannelCreatedEvent $event): NormalizedEvent
    {
        $channel = $this->normalizedChannel($event->channel());
        return  new NormalizedEvent($event::eventName(),
            [
                'channel' => $channel->getArray()
            ]);
    }

    /**
     * Return normalized channel update event
     *
     * @param ChannelUpdateEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    private function normalizeChannelUpdateEvent (ChannelUpdateEvent $event): NormalizedEvent
    {
        $channel = $this->normalizedChannel($event->channel(), $event->user());
        return  new NormalizedEvent($event::eventName(),
            [
                'channel' => $channel->getArray()
            ]);
    }


    /**
     * Return normalized channel removed event
     *
     * @param ChannelRemovedEvent $event
     * @return NormalizedEvent
     */
    private function normalizeChannelRemovedEvent (ChannelRemovedEvent $event): NormalizedEvent
    {
        return  new NormalizedEvent($event::eventName(),
            [
                'channel_id' => $event->channel()->getId(),
            ]);
    }

    /**
     * Return normalized channel renamed event
     *
     * @param ChatDeletedEvent $event
     * @return NormalizedEvent
     */
    private function normalizeChatDeletedEvent (ChatDeletedEvent $event): NormalizedEvent
    {
        return  new NormalizedEvent($event::eventName(),
            [
                'message_id' => $event->getMessageId(),
                'channel_id' => $event->getAssociatedChannelId()
            ]);
    }

    /**
     * Chat posted event normalization
     * @param ChatUpdatedEvent $event
     * @return N-ormalizedEvent
     * @throws DatabaseException
     */
    private function normalizeChatUpdatedEvent (ChatUpdatedEvent $event): NormalizedEvent
    {
        $message = $this->normalizedMessage($event->getMessage());
        return  new NormalizedEvent($event::eventName(),
            [
                'message' => $message->getArray()
            ]);
    }

    /**
     * Chat posted event normalization
     * @param ChatPostedEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     */
    private function normalizeChatPostedEvent (ChatPostedEvent $event): NormalizedEvent
    {
        $message = $this->normalizedMessage($event->getMessage());

        return  new NormalizedEvent($event::eventName(),
            [
                'message' => $message->getArray()
            ]);
    }

    /**
     * Chat posted event normalization
     * @param UserAddedToChannelEvent $event
     * @return NormalizedEvent
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    private function normalizeUserAddedToChannelEvent (UserAddedToChannelEvent $event): NormalizedEvent
    {
        $user = $this->dataController->getUserById($event->channelUser()->getUserId());
        $userEx = $this->normalizedUser($user);
        $userEx->addInt('user_role', $event->channelUser()->getUserRole());

        return  new NormalizedEvent($event::eventName(),
            [
                'channel_id' => $event->getAssociatedChannelId(),
                'user' => $userEx->getArray(),
            ]);
    }

    /**
     * Chat posted event normalization
     *
     * @param UserTypingEvent $event
     * @return NormalizedEvent
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     */
    private function normalizeUserTyping (UserTypingEvent $event): NormalizedEvent
    {
        $userEx = $this->normalizedUser($event->getUser());

        return  new NormalizedEvent($event::eventName(),
            [
                'user' => $userEx->getArray(),
                'channel_id' => $event->getAssociatedChannelId()
            ]);
    }

    /**
     *
     *
     * @param UserOfflineEvent $event
     * @return NormalizedEvent
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     */
    private function normalizeUserOfflineEvent (UserOfflineEvent $event): NormalizedEvent
    {
        $userEx = $this->normalizedUser($event->getUser());

        return  new NormalizedEvent($event::eventName(),
            [
                'user' => $userEx->getArray(),
                'channel_id' => $event->getAssociatedChannelId()
            ]);
    }

    /**
     *
     *
     * @param UserOnlineEvent $event
     * @return NormalizedEvent
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function normalizeUserOnlineEvent (UserOnlineEvent $event): NormalizedEvent
    {
        $userEx = $this->normalizedUser($event->getUser());

        return  new NormalizedEvent($event::eventName(),
            [
                'user' => $userEx->getArray(),
                'channel_id' => $event->getAssociatedChannelId()
            ]);
    }

    /**
     *
     *
     * @param UserProfileUpdateEvent $event
     * @return NormalizedEvent
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function normalizeUserProfileUpdate (UserProfileUpdateEvent $event): NormalizedEvent
    {
        $userEx = $this->normalizedUser($event->getUser());

        return  new NormalizedEvent($event::eventName(),
            [
                'user' => $userEx->getArray(),
                'channel_id' => $event->getAssociatedChannelId()
            ]);
    }

    /**
     *
     *
     * @param UserRemovedFromChannelEvent $event
     * @return NormalizedEvent
     */
    private function normalizeUserRemovedFromChannelEvent (UserRemovedFromChannelEvent $event): NormalizedEvent
    {
        return  new NormalizedEvent($event::eventName(),
            [
                'removed_user_id' => (int)$event->userRemovedId(),
                'channel_id' => $event->getAssociatedChannelId(),
                'removed_by_user_id' => (int)$event->removedById()
            ]);
    }

    /**
     *
     *
     * @param FSEvent $event
     * @return NormalizedEvent
     */
    private function normalizeFSEvent (FSEvent $event): NormalizedEvent
    {

        // split the extension and classify as media or not
        $fileName = empty($event->getFileName()) ? $event->getAssociatedFSPath() : $event->getFileName();
        if (preg_match('/\.([^.]+)$/', $fileName, $matches)) {
            $extension = trim($matches[1]);
        } else {
            $extension = '';
        }
        $media = in_array($extension, FileOperations::MEDIA_EXTENSIONS);

        $data  = [
            'user_id' => (int)$event->getUserID(),
            'channel_id' => $event->getAssociatedChannelId(),
            'fs_name' => $event->getFileName(),
            'fs_path' => $event->getAssociatedFSPath(),
//           'resource' => $event->getResource(),
            'extension' => $extension,
            'media' => $media,
        ];

        // if we're uploading a file, add the file size
        if ($event instanceof FSAddFileEvent) {
            $data['file_size'] = $event->getFileSize();
            $data['is_new'] = $event->isNew();
        }

        // if we're updating a file, add the previous path
        if ($event instanceof FSUpdateEvent || $event instanceof FSCopyEvent) {
            $data['previous_path'] = $event->getFromTranslatedPath()->getPath();
        }

        return  new NormalizedEvent($event::eventName(), $data);
    }

    /**
     *
     *
     * @param ActionCreatedEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function normalizeActionCreatedEvent(ActionCreatedEvent $event): NormalizedEvent
    {
        $action =   $this->dataController->getActionWithUsers($event->getActionId());
        $actionEx = $this->normalizedAction($action);

        return  new NormalizedEvent($event::eventName(),
            [
                'action' => $actionEx->getArray(),
                'channel_id' => $event->getAssociatedChannelId(),
            ]);
    }

    /**
     *
     * @param ActionUpdatedEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function normalizeActionUpdatedEvent(ActionUpdatedEvent $event): NormalizedEvent
    {

        $action =   $this->dataController->getActionWithUsers($event->getActionId());
        $childrenRecords = $this->dataController->getActions($action->getChannelId(), null, $action->getId());
        foreach ($childrenRecords as $childRecord) {
            $action->addChild($this->dataController->getActionWithUsers((int)$childRecord['id']));
        }
        $actionEx = $this->normalizedAction($action);

        return  new NormalizedEvent($event::eventName(),
            [
                'action' => $actionEx->getArray(),
                'channel_id' => $event->getAssociatedChannelId(),
                'old_channel_id' => $event->getOldChannelId(),
            ]);
    }

    /**
     *
     * @param ActionDeletedEvent $event
     * @return NormalizedEvent
     */
    private function normalizeActionDeletedEvent(ActionDeletedEvent $event): NormalizedEvent
    {
        return  new NormalizedEvent($event::eventName(),
            [
                'action_id' => $event->getActionId(),
                'channel_id' => $event->getAssociatedChannelId(),
                'parent_id' => $event->getParentId(),
            ]);
    }

    /**
     * @param LockAcquireEvent $event
     * @return NormalizedEvent
     */
    private function normalizeLockAcquireEvent(LockAcquireEvent $event): NormalizedEvent
    {
        $payload = $event->getPayloadArray();
        return  new NormalizedEvent($event::eventName(),
            [
                'lock_id' => $payload['lock_id'],
                'channel_id' => $payload['channel_id'],
                'user_id' => $payload['user_id'],
                'path' => $payload['path']
            ]);
    }

    /**
     * @param LockReleaseEvent $event
     * @return NormalizedEvent
     */
    private function normalizeLockReleaseEvent(LockReleaseEvent $event): NormalizedEvent
    {
        $payload = $event->getPayloadArray();

        return  new NormalizedEvent($event::eventName(),
            [
                'lock_id' => $payload['lock_id'],
                'channel_id' => $payload['channel_id'],
                'user_id' => $payload['user_id'],
                'path' => $payload['path']
            ]);
    }

    private function normalizeChannelReadStatusUpdateEvent(ChannelReadStatusUpdateEvent $event): NormalizedEvent
    {
        return  new NormalizedEvent($event::eventName(), $event->getPayloadArray());
    }

    private function normalizeChannelJoinEvent(ChannelJoinUpdateEvent $event): NormalizedEvent
    {
        return  new NormalizedEvent($event::eventName(), $event->getPayloadArray());
    }

    private function normalizeActionMovedEvent(ActionMovedEvent $event): NormalizedEvent
    {
        return new NormalizedEvent($event::eventName(), $event->getPayloadArray());
    }

    protected function normalizeGroupCreatedEvent(GroupCreatedEvent $event): NormalizedEvent
    {
        $group = $this->dataController->getChannelGroup($event->groupId());
        if ($group !== null) {
            $normalizedGroup = $this->normalizedChannelGroup($group);
            return new NormalizedEvent($event::eventName(), $normalizedGroup->jsonSerialize());
        } else {
            $this->logger->error('NO GROUP FOUND FOR ID ' . $event->groupId());
            return new NormalizedEvent($event::eventName(), [
                'error' => 'Group not found'
            ]);
        }
    }

    protected function normalizeGroupUpdatedEvent(GroupUpdatedEvent $event): NormalizedEvent
    {
        $group = $this->dataController->getChannelGroup($event->groupId());
        if ($group !== null) {
            $normalizedGroup = $this->normalizedChannelGroup($group);
            return new NormalizedEvent($event::eventName(), $normalizedGroup->jsonSerialize());
        } else {
            $this->logger->error('NO GROUP FOUND FOR ID ' . $event->groupId());
            return new NormalizedEvent($event::eventName(), [
                'error' => 'Group not found'
            ]);
        }
    }

    protected function normalizeGroupDeletedEvent(GroupDeletedEvent $event): NormalizedEvent
    {
        return new NormalizedEvent($event::eventName(), [
            'id' => $event->groupId(),
        ]);
    }

    protected function normalizeGroupReorderEvent(GroupReorderEvent $event): NormalizedEvent
    {
        /** @var ChannelGroupOperations $channelOps */
        $channelOps = ContainerFacade::get(ChannelGroupOperations::class);;
        return new NormalizedEvent($event::eventName(), $channelOps->listGroupsForUser($event->userId()));
    }

    private function normalizeAlertChannelToEvent(Alert $alert)
    {

        $from = [];
        foreach ($alert->getIssuers() as $issuer) {
            $fromUser = $this->dataController->getUserById((int)$issuer->getUserId());

            if (!empty($fromUser)) {
                $from[] = $this->normalizedUser($fromUser);
            }
        }

        return new NormalizedEvent('alert.notification',
            [
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getContextType(),
                'alert_text' => $alert->getAlertText(),
                'channel_id' => $alert->getContextId(),
                'is_read' => $alert->getIsRead(),
                'created_on' => $alert->getCreatedOn(),
                'from' => $from
            ]);
    }

    protected function normalizeMeetingInviteEvent(MeetingInviteEvent $event): NormalizedEvent
    {
        return new NormalizedEvent($event::eventName(), $event->getPayloadArray() );
    }

    private function getNormalizedPendingUsersForChannel(Channel $channel, User $loggedUser)
    {
        $users = [];

        foreach ($this->dataController->getPendingUsersForChannel($channel->getId()) as $user) {

            $normalizedObject = $this->normalizedObject($user);

            // if the member is not the owner of the channel, we don't include email on the payload
            if (isset($loggedUser) && ((int)($loggedUser->getId()) !== (int)$channel->getOwnedBy())) {
                $normalizedObject->remove('email');
            }

            $users[] = $normalizedObject;
        }

        return $users;
    }

    private function normalizeAlertChannelToObject(Alert $alert)
    {
        $from = [];
        foreach ($alert->getIssuers() as $issuer) {
            $fromUser = $this->dataController->getUserById((int)$issuer->getUserId());

            if (!empty($fromUser)) {
                $from[] = $this->normalizedUser($fromUser);
            }
        }

        // Alert contains
        return new NormalizedObject(
            [
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getContextType(),
                'from' => $from,
                'alert_text' => $alert->getAlertText(),
                'channel_id' => $alert->getContextId(),
                'is_read' => $alert->getIsRead(),
                'created_on' => $alert->getCreatedOn()
            ]);
    }

    protected function normalizedContactForm(ContactForm $object): NormalizedObject
    {
        $data = $object->getArray();
        $data['color'] = $data['color'] ? dechex($data['color']) : null;
        return new NormalizedObject($data);
    }

    protected function normalizedChannelGroup(ObjectInterface $object): NormalizedObject
    {
        $data = $object->getArray();

        // remove useless data from response
        unset($data['user_id']);
        unset($data['order_score']);

        // add virtual groups specific data
        if ($data['virtual']) {
            unset($data['created_on']);
            unset($data['created_on_ts']);
            $data['id'] = $data['name'];
            $data['name'] = I18n::get("channel_groups.{$data['name']}");
        }

        return new NormalizedObject($data);
    }

    protected function normalizedFileSystemObject(FileSystemObject $object): NormalizedObject
    {
        $data = $object->getArray();

        $path = preg_replace('/\/{2,}/', '/', $object->getFullPath());
        $hash = $this->dataController->findShortUrlHashForResource('File', $path);
        $data['public_hash'] = $hash ?? null;
        $data['external_link'] = StringUtility::generateShortUrlFromHash($hash);
        $data['media'] = in_array($data['ext'] ?? '', FileOperations::MEDIA_EXTENSIONS);

        return new NormalizedObject($data);
    }

    private function normalizedActionHistory(ActionHistory $actionHistory): NormalizedObject
    {
        $historyEx = new NormalizedObject($actionHistory->getArray());

        // add the history entry author (full object)
        $user = $this->dataController->getUserById($actionHistory->getUserId());
        $historyEx->addArray('user', $this->normalizedUser($user)->jsonSerialize());

        // normalize the objects on the attachments
        $attachments = $actionHistory->getAttachments() ?? [];
        foreach ($attachments as $key => $value) {
            switch ($key) {

                // replace message_id with the normalized message
                case 'message_id':
                    $message = $this->dataController->getMessageById($value);
                    $attachments['message'] = $this->normalizedMessage($message)->jsonSerialize();
                    unset($attachments['message_id']);
                    break;

                case 'user_id':
                    $user = $this->dataController->getUserById($value);
                    $attachments['user'] = $this->normalizedUser($user)->jsonSerialize();
                    unset($attachments['user_id']);
                    break;

                case 'action_id':
                    $action = $this->dataController->getActionById($value);
                    if ($action !== null) {
                        $attachments['action'] = $this->normalizedAction($action)->jsonSerialize();
                    } else {
                        $attachments['action'] = ['name' => 'Deleted Action'];
                    }
                    unset($attachments['action_id']);
                    break;
            }
        }
        $historyEx->addArray('attachments', $attachments);


//        // add channel name
//        $channel = $this->dataController->getChannelById($action->getChannelId());
//        $actionEx->addPOD('channel_name', $channel->getName());
//
//        // add children
//        $children = array_map(function (Action $item) {
//            return $this->normalizedAction($item);
//        }, $action->getChildren());
//        $actionEx->addArray('children', $children);
//
//        // add users
//        $users = array_map(function ($item) {
//            $user = $this->dataController->getUserById((int)$item['user_id']);
//            return $this->normalizedUser($user)->jsonSerialize();
//        }, $action->getUsers());
//        $actionEx->addArray('users', $users);
//
//        $actionUsers = $action->getUserObjects();
//        if (!empty($actionUsers)) {
//            $nusers = [];
//            foreach ($actionUsers as $user) {
//                $nusers[] = $this->normalizedObject($user)->getArray();
//            }
//            $actionEx->addArray('users', $nusers);
//        }

        return $historyEx;
    }

    /**
     * @param Call $call
     * @return NormalizedObject
     */
    private function normalizedCall(Call $call): NormalizedObject
    {
        $retArray = $call->getArray();
        $retArray['hash'] = $call->callHash();

        $channelId = $call->getChannelId();
        if ($channelId) {
            $channel = $this->dataController->getChannelById($channelId);
            if ($channel !== null) {
                $retArray['channel_name'] = $channel->getName();
            }
        }

        return new NormalizedObject($retArray);
    }

    private function normalizedTeam(Team $team, User $user, bool $abbreviated): NormalizedObject
    {

        $teamUser = $this->dataController->getTeamUser($user->getId(), $team->getId());
        $output = [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'members_count' => $this->dataController->getTeamUsersCount($team->getId()),
            'role_id' => $teamUser->getUserRole(),
            'role' => $teamUser->getUserRoleAsString(),
            'tag_color' => $this->policyManager->getPolicyValue($team, TeamTagColor::class),
        ];

        if ($abbreviated) {
            return new NormalizedObject($output);
        }

        $teamChannels = $this->dataController->findTeamChannels($team->getId());
        $openChannels = array_values(array_filter($teamChannels, function (Channel $channel) {
            return $channel->isOpenForTeamJoin();
        }));

        // additional global info (for all members)
        $output = array_merge($output, [
            'announcements' =>  $this->policyManager->getPolicyValue($team, TeamAnnouncements::class),
            'open_channels' => array_map(function (Channel $channel) use ($user) {
                return [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'blurb' => $channel->getBlurb() ?? '',
                    'has_logo' => $channel->getHasLogo(),
                    'members_count' => $this->dataController->getChannelUserCount($channel->getId()),
                    'has_joined' => $this->dataController->isChannelMember($channel->getId(), $user->getId()),
                ];
            }, $openChannels),
        ]);

        if ($user->can('manage', $team)) {

            // additional manager info
            $storageStats = $this->fOps->folderProps("/{$team->getId()}");
            $availableSize = $this->policyManager->getPolicyValue($team, StorageQuota::class);
            $availableSize *= (1024*1024*1024); // convert to bytes

            $output = array_merge($output, [
                'storage_stats' => $storageStats,
                'storage_available_size' => $availableSize,
                'storage_used_size' => $storageStats->getTotalSize(),
                'all_channels' => array_map(function (Channel $channel) {
                    return $this->normalizedObject($channel);
                }, $teamChannels),
            ]);

        }

        return new NormalizedObject($output);
    }

    /**
     * @param TeamUser $teamUser
     * @param User|null $member
     * @return NormalizedObject
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function normalizedTeamMember(TeamUser $teamUser, ?User $member = null): NormalizedObject
    {

        $userId = $teamUser->getUserId();
        $teamId = $teamUser->getTeamId();
        $channels = $this->dataController->getTeamChannelsForUser($teamId, $userId);
        $channelsOwned = $this->dataController->getTeamChannelsOwnedByUser($teamId, $userId);

        // calculate fs stats on team
        $fsCount = 0;
        $fsSize = 0;
        try {
            foreach ($channelsOwned as $channel) {

                $channelRoots = $this->fileOperations->getChannelRoots($channel);

                foreach ($channelRoots as $root) {
                    $props = $this->fileOperations->folderProps($root->getPhysicalPath());
                    $fsCount += $props->getTotalCount();
                    $fsSize += $props->getTotalSize();
                }
            }
        } catch (FSOpException $e) {
            // just ignore
        }

        // if a member is not provided, find it
        if ($member === null) {
            $member = $this->dataController->getUserById($teamUser->getUserId());
        }

        $data = [
            'id' => $member->getId(),
            'name' => $member->getDisplayName(),
            'email' => $member->getEmail(),
            'has_avatar' => $member->getHasAvatar(),
            'updated_on' => $member->getUpdatedOn(),
            'online_status' => ($item['user_status'] ?? null) === 0 ? false : $this->rtmOps->isUserOnline($userId),
            'role' => $teamUser->getUserRole(),
            'member_since' => $member->getCreatedOn(),
            'invited_by' => $teamUser->getCreatedBy(),
            'channels_count' => count($channels),
            'channels_owned' => count($channelsOwned),
            'fs_count' => $fsCount,
            'fs_size' => $fsSize,
        ];

        return new NormalizedObject($data);
    }

    /**
     * @param AbstractTeamChannelEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function normalizeTeamChannelEvent(AbstractTeamChannelEvent $event): NormalizedEvent
    {
        $team = $event->getTeam();
        $channel = $event->getChannel();

        $payload = [
            'team_id' => $team->getId(),
            'channel' => $this->normalizedChannel($channel),
        ];

        return new NormalizedEvent($event::eventName(), $payload);
    }

    /**
     * @param TeamAddChannelEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function normalizeTeamAddChannelEvent(TeamAddChannelEvent $event): NormalizedEvent
    {
        return $this->normalizeTeamChannelEvent($event);
    }

    /**
     * @param TeamUpdateChannelEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function normalizeTeamUpdateChannelEvent(TeamUpdateChannelEvent $event): NormalizedEvent
    {
        return $this->normalizeTeamChannelEvent($event);
    }

    /**
     * @param TeamRemoveChannelEvent $event
     * @return NormalizedEvent
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function normalizeTeamRemoveChannelEvent(TeamRemoveChannelEvent $event): NormalizedEvent
    {
        return $this->normalizeTeamChannelEvent($event);
    }

    /**
     * @param AbstractTeamMemberEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    protected function normalizeTeamMemberEvent(AbstractTeamMemberEvent $event): NormalizedEvent
    {
        $team = $event->getTeam();
        $user = $event->getMember();
        $userTeam = $this->dataController->getTeamUser($user->getId(), $team->getId());

        $payload = [
            'team_id' => $team->getId(),
            'member' => $this->normalizedTeamMember($userTeam, $user),
        ];

        return new NormalizedEvent($event::eventName(), $payload);
    }

    /**
     * @param TeamAddMemberEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    protected function normalizeTeamAddMemberEvent(TeamAddMemberEvent $event): NormalizedEvent
    {
        return $this->normalizeTeamMemberEvent($event);
    }

    /**
     * @param TeamAddMemberEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    protected function normalizeTeamUpdateMemberEvent(TeamUpdateMemberEvent $event): NormalizedEvent
    {
        return $this->normalizeTeamMemberEvent($event);
    }

    /**
     * @param TeamRemoveMemberEvent $event
     * @return NormalizedEvent
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    protected function normalizeTeamRemoveMemberEvent(TeamRemoveMemberEvent $event): NormalizedEvent
    {
        $team = $event->getTeam();
        $user = $event->getMember();
        $userTeam = $event->getTeamUser();

        $payload = [
            'team_id' => $team->getId(),
            'member' => $this->normalizedTeamMember($userTeam, $user),
        ];

        return new NormalizedEvent($event::eventName(), $payload);
    }

    protected function normalizeTeamUpdateEvent(TeamUpdateEvent $event, User $user): NormalizedEvent
    {
        $payload = [
            'team' => $this->normalizedTeam($event->getTeam(), $user, false),
        ];

        return new NormalizedEvent($event::eventName(), $payload);
    }


}