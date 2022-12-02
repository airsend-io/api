<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Notification;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Indexers\ActionIndexer;
use CodeLathe\Core\Indexers\ChannelIndexer;
use CodeLathe\Core\Indexers\FileIndexer;
use CodeLathe\Core\Indexers\MessageIndexer;
use CodeLathe\Core\Managers\Action\ActionOperations;
use CodeLathe\Core\Managers\Channel\ChannelEventHandler;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\Realtime\RtmMessage;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\AbstractTeamChannelEvent;
use CodeLathe\Core\Messaging\Events\AbstractTeamMemberEvent;
use CodeLathe\Core\Messaging\Events\ActionCreatedEvent;
use CodeLathe\Core\Messaging\Events\ActionDeletedEvent;
use CodeLathe\Core\Messaging\Events\ActionMovedEvent;
use CodeLathe\Core\Messaging\Events\ActionUpdatedEvent;
use CodeLathe\Core\Messaging\Events\AdminReportRequestedEvent;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\ChannelCreatedEvent;
use CodeLathe\Core\Messaging\Events\ChannelJoinUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChannelRemovedEvent;
use CodeLathe\Core\Messaging\Events\ChannelUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChatDeletedEvent;
use CodeLathe\Core\Messaging\Events\ChatPostedEvent;
use CodeLathe\Core\Messaging\Events\ChatUpdatedEvent;
use CodeLathe\Core\Messaging\Events\FSAddFileEvent;
use CodeLathe\Core\Messaging\Events\FSCopyEvent;
use CodeLathe\Core\Messaging\Events\FSCreateFolderEvent;
use CodeLathe\Core\Messaging\Events\FSDeleteEvent;
use CodeLathe\Core\Messaging\Events\FSEvent;
use CodeLathe\Core\Messaging\Events\FSUpdateEvent;
use CodeLathe\Core\Messaging\Events\GroupCreatedEvent;
use CodeLathe\Core\Messaging\Events\GroupDeletedEvent;
use CodeLathe\Core\Messaging\Events\GroupReorderEvent;
use CodeLathe\Core\Messaging\Events\GroupUpdatedEvent;
use CodeLathe\Core\Messaging\Events\LockAcquireEvent;
use CodeLathe\Core\Messaging\Events\LockReleaseEvent;
use CodeLathe\Core\Messaging\Events\MeetingInviteAcceptEvent;
use CodeLathe\Core\Messaging\Events\MeetingInviteEvent;
use CodeLathe\Core\Messaging\Events\NotifyAlert;
use CodeLathe\Core\Messaging\Events\PingEvent;
use CodeLathe\Core\Messaging\Events\TeamAddChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamAddMemberEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveMemberEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateMemberEvent;
use CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent;
use CodeLathe\Core\Messaging\Events\UserCreatedEvent;
use CodeLathe\Core\Messaging\Events\UserLoginEvent;
use CodeLathe\Core\Messaging\Events\UserOnlineEvent;
use CodeLathe\Core\Messaging\Events\UserProfileUpdateEvent;
use CodeLathe\Core\Messaging\Events\UserRemovedFromChannelEvent;
use CodeLathe\Core\Messaging\MessageQueue\MQProducer;
use CodeLathe\Core\Messaging\MessageQueue\NodeUtilMessage;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\ActionHistory;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\AlertIssuer;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\Timeline;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\CSV;
use CodeLathe\Core\Utility\Markdown;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedEvent;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Auth\JwtServiceInterface;
use CodeLathe\Service\Database\RootDatabaseService;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Elasticsearch\Client as ElasticSearchClient;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationManager implements EventSubscriberInterface
{
    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var MQProducer
     */
    protected $mqProducer;

    /**
     * @var
     */
    protected $dataController;

    /**
     * @var RtmOperations
     */
    protected $rtmOps;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    /**
     * @var NotificationFilter
     */
    protected $notificationFilter;

    /**
     * @var FileOperations
     */
    protected $fOps;

    /**
     * @var UserOperations
     */
    protected $userOps;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var ActionOperations
     */
    protected $actionOps;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;


    /**
     * @var ServiceRegistryInterface
     */
    protected $registry;

    /**
     * @var JwtServiceInterface
     */
    protected $jwt;

    /**
     * @var ChannelEventHandler
     */
    protected $channelEventHandler;

    /**
     * @var ElasticSearchClient
     */
    protected $elasticClient;

    /**
     * @var MessageIndexer
     */
    protected $messageIndexer;

    /**
     * @var ActionIndexer
     */
    protected $actionIndexer;

    /**
     * @var ChannelIndexer
     */
    protected $channelIndexer;

    /**
     * @var FileIndexer
     */
    protected $fileIndexer;

    /**
     * NotificationManager constructor.
     * @param LoggerInterface $logger
     * @param EventManager $eventManager
     * @param MQProducer $mqProducer
     * @param DataController $dataController
     * @param RtmOperations $rtmOps
     * @param ChannelOperations $channelOps
     * @param NotificationFilter $notificationFilter
     * @param FileOperations $fOps
     * @param UserOperations $userOperations
     * @param ConfigRegistry $config
     * @param ActionOperations $actionOperations
     * @param MailerServiceInterface $mailerService
     * @param ServiceRegistryInterface $registry
     * @param JwtServiceInterface $jwt
     * @param ChannelEventHandler $channelEventHandler
     * @param ElasticSearchClient $elasticClient
     * @param MessageIndexer $messageIndexer
     * @param ActionIndexer $actionIndexer
     * @param ChannelIndexer $channelIndexer
     * @param FileIndexer $fileIndexer
     */
    public function __construct(LoggerInterface $logger,
                                EventManager $eventManager,
                                MQProducer $mqProducer,
                                DataController $dataController,
                                RtmOperations $rtmOps,
                                ChannelOperations $channelOps,
                                NotificationFilter $notificationFilter,
                                FileOperations $fOps,
                                UserOperations $userOperations,
                                ConfigRegistry $config,
                                ActionOperations $actionOperations,
                                MailerServiceInterface $mailerService,
                                ServiceRegistryInterface $registry,
                                JwtServiceInterface $jwt,
                                ChannelEventHandler $channelEventHandler,
                                ElasticSearchClient $elasticClient,
                                MessageIndexer $messageIndexer,
                                ActionIndexer $actionIndexer,
                                ChannelIndexer $channelIndexer,
                                FileIndexer $fileIndexer
                                )
    {
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->mqProducer = $mqProducer;
        $this->dataController = $dataController;
        $this->rtmOps = $rtmOps;
        $this->channelOps = $channelOps;
        $this->notificationFilter = $notificationFilter;
        $this->fOps = $fOps;
        $this->userOps = $userOperations;
        $this->config = $config;
        $this->actionOps = $actionOperations;
        $this->mailer = $mailerService;
        $this->registry = $registry;
        $this->jwt = $jwt;
        $this->channelEventHandler = $channelEventHandler;
        $this->elasticClient = $elasticClient;
        $this->messageIndexer = $messageIndexer;
        $this->actionIndexer = $actionIndexer;
        $this->channelIndexer = $channelIndexer;
        $this->fileIndexer = $fileIndexer;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            ChannelCreatedEvent::backgroundEventName() => 'onChannelCreated',
            UserLoginEvent::backgroundEventName() => 'onUserLoggedIn',
            UserCreatedEvent::backgroundEventName() => 'onUserCreated',
            ChatPostedEvent::backgroundEventName() => 'onChatPosted',
            ChatUpdatedEvent::backgroundEventName() => 'onChatUpdated',
            ChatDeletedEvent::backgroundEventName() => 'onChatDeleted',
            UserAddedToChannelEvent::backgroundEventName() => 'onUserAddedToChannel',
            UserOnlineEvent::backgroundEventName() => 'onUserOnline',
            UserProfileUpdateEvent::backgroundEventName() => 'onUserProfileUpdated',
            ChannelRemovedEvent::backgroundEventName() => 'onChannelRemoved',
            UserRemovedFromChannelEvent::backgroundEventName() => 'onUserRemovedFromChannel',
            ChannelUpdateEvent::backgroundEventName() => 'onChannelUpdated',
            FSAddFileEvent::backgroundEventName() => 'onFsEvent',
            FSCreateFolderEvent::backgroundEventName() => 'onFsEvent',
            FSUpdateEvent::backgroundEventName() => 'onFsEvent',
            FSDeleteEvent::backgroundEventName() => 'onFsEvent',
            FSCopyEvent::backgroundEventName() => 'onFsEvent',
            ActionCreatedEvent::backgroundEventName() => 'onActionCreated',
            ActionUpdatedEvent::backgroundEventName() => 'onActionUpdated',
            ActionDeletedEvent::backgroundEventName() => 'onActionDeleted',
            LockAcquireEvent::backgroundEventName() => 'onLockAcquire',
            LockReleaseEvent::backgroundEventName() => 'onLockRelease',
            NotifyAlert::backgroundEventName() => 'onNotifyAlert',
            AdminReportRequestedEvent::backgroundEventName() => 'onAdminReportRequest',
            ChannelJoinUpdateEvent::backgroundEventName() => 'onChannelJoinUpdateEvent',
            ActionMovedEvent::backgroundEventName() => 'onActionMoved',
            GroupCreatedEvent::backgroundEventName() => 'onGroupCreated',
            GroupUpdatedEvent::backgroundEventName() => 'onGroupUpdated',
            GroupDeletedEvent::backgroundEventName() => 'onGroupDeleted',
            GroupReorderEvent::backgroundEventName() => 'onGroupReorder',
            PingEvent::backgroundEventName() => 'onPing',
            MeetingInviteEvent::backgroundEventName() => 'onMeetingInvite',
            MeetingInviteAcceptEvent::backgroundEventName() => 'onMeetingInviteAccept',
            TeamAddMemberEvent::backgroundEventName() => 'onTeamAddMember',
            TeamUpdateMemberEvent::backgroundEventName() => 'onTeamUpdateMember',
            TeamRemoveMemberEvent::backgroundEventName() => 'onTeamRemoveMember',
            TeamAddChannelEvent::backgroundEventName() => 'onTeamAddChannel',
            TeamUpdateChannelEvent::backgroundEventName() => 'onTeamUpdateChannel',
            TeamRemoveChannelEvent::backgroundEventName() => 'onTeamRemoveChannel',
            TeamUpdateEvent::backgroundEventName() => 'onTeamUpdate'
        ];
    }

    /**
     *
     * Method to send an event message to Realtime websocket server.
     * one message per valid connection for the client will be sent
     *
     * @param ASEvent $event
     * @param int $userToIgnore
     * @param int $additionalUserToNotify
     * @param bool $onlyAdditionalUserId
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function routeToClients(ASEvent $event, int $userToIgnore = 0, int $additionalUserToNotify = 0,bool $onlyAdditionalUserId = false): void
    {
        $this->notificationFilter->notifyClients($event, $userToIgnore, $additionalUserToNotify, $onlyAdditionalUserId);
    }

    /**
     * Called when user is logged in
     *
     * @param UserLoginEvent $event
     * @throws DatabaseException
     */
    public function onUserLoggedIn(UserLoginEvent $event): void
    {
        $this->logger->debug(__FUNCTION__ . " : Received User logged in event " . $event->getUserId());

        // Update active time
        /** Update the active time for the user */
        $user = $this->dataController->getUserById($event->getUserId());
        if (!empty($user)){
            $user->setLastActiveOn(date('Y-m-d H:i:s'));
            $this->dataController->updateUser($user);
        }
    }

    /**
     * Called when user is logged in
     *
     * @param UserAddedToChannelEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onUserAddedToChannel(UserAddedToChannelEvent $event): void
    {
        $this->logger->debug(__FUNCTION__ . " : Received User ".$event->channelUser()->getUserId() . " added to channel " . $event->channelId());
        $this->channelEventHandler->channelCacheUpdate($event);

        $this->routeToClients($event);


        // ... Update the active time for this channel
        $channel  = $this->dataController->getChannelById((int)$event->channelId());
        if (!empty($channel))
        {
            $channel->setLastActiveOn(date('Y-m-d H:i:s'));
            if ($channel->getChannelStatus() == Channel::CHANNEL_STATUS_CLOSED) {
                $channel->setChannelStatus(Channel::CHANNEL_STATUS_OPEN);

                // Raise event
                $event = new ChannelUpdateEvent($channel);
                $this->onChannelUpdated($event);
            }

            // Set status to open if message is being added.
            $this->dataController->updateChannel($channel);
        }
    }

    /**
     * Called when user is created
     *
     * @param UserCreatedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onUserCreated(UserCreatedEvent $event): void
    {
        $this->logger->debug(__FUNCTION__ ." : Received User ".$event->getUser()->getEmail()." created in event ");
        $this->routeToClients($event);
    }

    /**
     * Called when a channel is created
     *
     * @param ChannelCreatedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onChannelCreated(ChannelCreatedEvent $event): void
    {
        $channel = $event->channel();

        $this->logger->debug(__FUNCTION__ ." : Received Channel ". $channel->getName(). " created in event ");
        $this->routeToClients($event);

        $this->channelIndexer->indexChannel($channel->getId(), $channel->getName(), $channel->getBlurb());

    }

    /**
     *
     * Called when a chat is posted
     *
     * @param ChatPostedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     * @throws InvalidEmailAddressException
     */
    public function onChatPosted(ChatPostedEvent $event): void
    {
//        $this->logger->debug(__FUNCTION__ ." : Received Chat \"" .$event->getMessage()->getText() . "\" by user [" .
//                             $event->getMessage()->getUserId() .
//                             "] in channel " . $event->getMessage()->getChannelId());


        // ... add post activity to timeline
        $message = $event->getMessage();
        $channel  = $this->dataController->getChannelById($message->getChannelId());
        if (empty($channel)) {
            return;
        }

        $timeline = Timeline::create($message->getChannelId(), $message->getUserId(), $message->getId(), Timeline::ACTIVITY_WRITE);
        $this->dataController->addTimeline($timeline);

        // ... approve DM channel - Must be a DM channel and must not be approved...
        if ($channel->getOneOnOne() && !$channel->getOneOnOneApproved()) {

            $raiseEvent = false;

            // ... if the user sending the message is not the inviter/owner (is the invitee), set the channel as approved
            if ($channel->getOwnedBy() !== $message->getUserId()) {

                $channel->setOneOnOneApproved(true);
                $this->dataController->updateChannel($channel);
                $raiseEvent = true;

            }

            // if it's the 4th sent message by the inviter
            if ($this->dataController->getDMSentByInviterCount($channel->getId()) === (ChannelUser::FREE_MESSAGES_ON_DM + 1)) {
                $raiseEvent = true;
            }

            // raise the event
            if ($raiseEvent) {
                $updateChannelEvent = new ChannelUpdateEvent($channel);
                $this->eventManager->publishEvent($updateChannelEvent);
            }
        }

        // ... Send to clients
        $this->routeChatPostedToClients($event);
        $this->notificationFilter->notifyChat($channel, $message);

        // ... Start unfurling process
        $this->unfurlMessage($message);

        // ... Housekeeping - Update active times
        $user = $this->dataController->getUserById($message->getUserId());
        if (!empty($user)){
            $user->setLastActiveOn(date('Y-m-d H:i:s'));
            $this->dataController->updateUser($user);
        }

        $channel->setLastActiveOn(date('Y-m-d H:i:s'));
        // Set status to open if message is being added.
        if ($channel->getChannelStatus() == Channel::CHANNEL_STATUS_CLOSED) {
            $channel->setChannelStatus(Channel::CHANNEL_STATUS_OPEN);

            // Raise event
            $event = new ChannelUpdateEvent($channel);
            $this->onChannelUpdated($event);
        }
        $this->dataController->updateChannel($channel);

        // ... Detect quote / mentions and add it to alerts (the current message and quote)
        $this->recordMentions($message);
        //foreach ($message->getParentMessages() as $parentMessage) {
        //    $this->recordMentions($message, $parentMessage->getContentText());
        //}

        if ($message->getMessageType() === Message::MESSAGE_TYPE_QUOTE) {
            $this->recordQuote($message);
        }

        // ... Send via email if needed
        if ($message->getSendEmail()) {
            $this->sendEmail($channel, $event);
        }

        // ... update search index
        if (!empty($message->getText()) && $message->getMessageType() !== Message::MESSAGE_TYPE_BOT) {
            $this->messageIndexer->indexMessage($message->getId(), $message->getChannelId(), $message->getUserId(), $message->getText());
        }
    }

    /**
     * Chat posted have a special handling because some users may have block this channel.
     *
     * @param ChatPostedEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function routeChatPostedToClients(ChatPostedEvent $event): void
    {

        $channelId = $event->getAssociatedChannelId();

        // find all channel-users relations
        $channelUsers = $this->dataController->getChannelUsersByChannelId($channelId);

        // find the users that didn't blocked the channel
        $usersToNotify = array_filter($channelUsers, function (ChannelUser $channelUser) {
            return $channelUser->getBlockedOn() === null;
        });

        // check if there is any blocker on the channel
        if (count($usersToNotify) === count($channelUsers)) {

            // no blockers on the channel, just route to everyone
            $this->routeToClients($event);
            return;

        }

        // there are blockers, so route only to the non-blockers
        foreach ($usersToNotify as $channelUser) {
            $this->routeToClients($event, 0, $channelUser->getUserId(), true);
        }
    }

    /**
     *
     * Send email message of chat message to each user of this channel
     *
     * @param ChatPostedEvent $event
     * @throws DatabaseException
     * @throws InvalidEmailAddressException
     */
    private function sendEmail(Channel $channel, ChatPostedEvent $event)
    {
        foreach ($this->dataController->getUsersForChannel($channel->getId()) as $userRec) {
            $user = User::withDBData($userRec);
            if ($event->getMessage()->getUserId() != $user->getId()) {
                $this->sendChatMail($user, $channel, $event);
            }
        }
    }

    /**
     *
     * Send message via email
     *
     * @param User $user
     * @param Channel $channel
     * @param ChatPostedEvent $event
     * @throws DatabaseException
     * @throws InvalidEmailAddressException
     */
    private function sendChatMail(User $user, Channel $channel, ChatPostedEvent $event)
    {
        $this->logger->debug(__FUNCTION__ . " : Sending chat message to " . $user->getEmail());
        // define the from header, and the reply-to header (used for automatic responding inside the channel)
        $to = $user->getDisplayName() . " <" . $user->getEmail() . ">";
        $from = $channel->getEmail();

        $userChannelMail =  $this->dataController->getMemberEmailForChannel($channel->getId(), $user->getId());
        $parts = explode('@', $userChannelMail);
        $replyTo = $parts[0]; // Reply too cannot contain domain


        $channelName = $channel->getName();
        $channelId = $channel->getId();
        $uiBaseUrl = $this->registry->get('/app/ui/baseurl');
        $channelLink = "$uiBaseUrl/channel/{$channelId}";
        $settingsLink = "$uiBaseUrl/email-settings";
        $reportAbuseLink = "$uiBaseUrl/report?channel_name=" . urlencode($channelName) . "&reporter_email=" . urlencode($to);

        $convMsg = $this->convertMessageForMail($user, $event->getPayloadArray());
        $convMsgs = [$convMsg];

        $subject =  "You have a new message in the channel {$channelName}";
        $fromName = "AirSend - $channelName";
        $emailMessage = $this->mailer->createMessage($to)
            ->subject($subject)
            ->from($from, $fromName)
            ->replyTo($replyTo, $fromName, $this->registry->get('/mailer/response_domain'))
            ->body('digest_notification', [
                'subject' => $subject,
                'messages' => $convMsgs,
                'channelName' => $channelName,
                'channelUrl' => $channelLink,
                'manageSettingsUrl' => $settingsLink,
                'reportAbuseUrl' => $reportAbuseLink,
                'additionalMessagesCount' => false,

            ]);

        // send it
        $this->mailer->send($emailMessage);
    }

    /**
     *
     * Scoped token to allow access to the avatar
     *
     * @param User $user
     * @return string
     */
    private function getImageAccessToken(User $user): string
    {
        $scope = "/api/v1/user.image.get";
        return $this->jwt->issueToken($user->getId(), "", "",
            false, false, $scope);
    }

    /**
     *
     * Map the message to template keynames
     *
     * @param User $user
     * @param array $message
     * @return array
     */
    private function convertMessageForMail(User $user, array $message): array
    {

        $token = $this->getImageAccessToken($user);

        $uiBaseUrl = $this->registry->get('/app/ui/baseurl');
        $attachments = [];
        foreach ($message['attachments'] ?? [] as $item) {
            $attachment = json_decode($item['content'] ?? '', true);
            if (!empty($attachment['path'])) {
                $attachments[$attachment['file']] = "$uiBaseUrl/channel/{$message['channel_id']}?preview={$attachment['path']}";
            }
        }

        $serverBaseUrl = $this->registry->get('/app/server/baseurl');
        $out_message = [];
        $out_message['text'] = Markdown::parseMessageForEmail($message['content_text']);
        $out_message['user_name'] = $message['display_name'];
        $out_message['time'] = \DateTime::createFromFormat('Y-m-d H:i:s', $message['created_on'])->format('l, g:iA');
        $out_message['avatar'] = $serverBaseUrl . "/user.image.get?user_id={$message['user_id']}&image_class=medium&round=1&return_fallback_image=1&token=$token";
        $out_message['truncated'] = false;
        $out_message['attachments'] = $attachments;

        //$this->logger->info(print_r($out_message,true));
        return $out_message;
    }

    /**
     *
     * Detect and create @mention
     *
     * @param Message $message
     * @param string|null $text
     */
    private function recordMentions(Message $message, ?string $text = null): void
    {
        // Ignore all bot message
        if ($message->getUserId() == $this->config->get('/app/airsend_bot_id') || empty($message->getText())) {
            return;
        }

        $text = $text ?? $message->getText();

        $matches = [];
        if (preg_match_all('/\[([^]]*?)]\(([^:]+):\/\/([^)]+)\)/', $text, $matches)) {

            if (count($matches) > 0) {

                $this->logger->debug("Found " . count($matches[0]) . " mentions");

                // analyse each mention
                foreach (array_keys($matches[0]) as $key) {

                    $fullMention = $matches[0][$key];
                    $mentionTitle = $matches[1][$key];
                    $mentionType = $matches[2][$key];
                    $mentionId = $matches[3][$key];

                    // first find the proper mention manager class
                    $handlerClass = __NAMESPACE__ . '\\MentionHandlers\\' . ucwords(strtolower($mentionType)) . 'MentionHandler';
                    if (!class_exists($handlerClass)) {
                        // mention handler not found, so this is not a valid mention
                        $this->logger->debug("Invalid mention: $fullMention");
                        continue;
                    }

                    $mentionHandler = ContainerFacade::get($handlerClass);

                    $this->handleMention($mentionHandler, $message, $fullMention, $mentionTitle, $mentionId);

                }
            }

        }
    }

    protected function handleMention(MentionHandlerInterface $handler, Message $message, string $fullMention, string $mentionTitle, string $mentionId): void {
        $handler->handle($message, $fullMention, $mentionTitle, $mentionId);
    }

    /**
     * @param Message $message
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    private function recordQuote(Message $message): void
    {

        if (empty($parentMessages = $message->getParentMessages())) {
            return;
        }

        $messageParent = $parentMessages[0];

        if ((int)$message->getUserId() !== (int)$messageParent->getUserId()) {

            $quotedMessageId = (int)$messageParent->getMessageId();
            $quotedText = "has quoted your message in";
            if (!empty($quotedMessage = $this->dataController->getMessageById($quotedMessageId))) {
                $quotedMessageText = $quotedMessage->getText();
                $quotedMessageText = preg_replace('/\[(.*?)\]\((.*?)\)/', '${1}', $quotedMessageText);


                if (!empty($quotedMessageText)) {
                    if (strlen($quotedMessageText) > 60) {
                        $quotedText =  "has quoted your message: " . substr($quotedMessageText, 0, 60) . "... - in";
                    }
                    else {
                        $quotedText = "has quoted your message: " . $quotedMessageText . " - in";
                    }
                }
            }

            $channel = $this->dataController->getChannelById($message->getChannelId());

            $alertText = "[" . $message->getDisplayName() ."](user://" .$message->getUserId(). ") $quotedText " .
                         "[" . $channel->getName() . "](channel://" . $channel->getId() . ")";

            $alert = Alert::create((int)$messageParent->getUserId(), $message->getId(),
                Alert::CONTEXT_TYPE_MESSAGE, $alertText,
                AlertIssuer::create((int)$message->getUserId(), $message->getDisplayName()), Alert::ALERT_TYPE_QUOTE);
            $this->dataController->upsertAlert($alert);
            $this->notificationFilter->notifyAlert($alert);
        }
    }

    /**
     *
     * Process Chat updated event
     *
     * @param ChatUpdatedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onChatUpdated(ChatUpdatedEvent $event): void
    {
        //$this->logger->debug(__FUNCTION__ ." : Received Chat edit \"" .$event->getMessage()->getText() . "\" by user [" . $event->getMessage()->getUserId() .
        //                     "] in channel " . $event->getMessage()->getChannelId());
        $message = $event->getMessage();
        if ($event->didMessageChange()) {
            $this->logger->debug(__FUNCTION__ . " : Message changed!");
            // ... Check for unfurling and start the process
            // ... Updates cannot have files additions. Check for links
            $this->unfurlLink($message);

            /**
             *Detect quote / mentions and add it to alerts
             */
            $this->recordMentions($message);
        }

        /** Send to clients */
        $this->routeToClients($event);

        if (!$message->getIsDeleted() && $message->getMessageType() !== Message::MESSAGE_TYPE_BOT) {
            $this->messageIndexer->indexMessage($message->getId(), $message->getChannelId(), $message->getUserId(), $message->getText());
        }
    }

    /**
     * @param UserOnlineEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onUserOnline(UserOnlineEvent $event): void
    {
        $this->routeToClients($event);
    }

    /**
     *
     * Process chat deleted event
     *
     * @param ChatDeletedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onChatDeleted(ChatDeletedEvent $event): void
    {
        /** Send to clients */
        $this->routeToClients($event);

        $this->messageIndexer->remove((string)$event->getMessageId());
    }


    /**
     * @param UserProfileUpdateEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onUserProfileUpdated(UserProfileUpdateEvent $event): void
    {
        /** Send to clients */
        $this->routeToClients($event);
    }

    /**
     *
     * Send request to utility node instance to unfurl all links found in a message
     *
     * @param Message $message
     */
    private function unfurlMessage(Message $message): void
    {
        $this->unfurlLink($message);

        // Unfurl attachments if any
        $this->unfurlAttachment($message);
    }

    /**
     * @param Message $message
     */
    private function unfurlLink(Message $message):void
    {
        if (empty($message->getText())) {
            return;
        }
        $matches = [];

        $reg_exUrl = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";

        preg_match_all($reg_exUrl, $message->getText(), $matches);
        //$this->logger->info((__FUNCTION__ . " : " . print_r($matches[0],true)));
        // Check if this url has already been unfurled.
        $requireUnfurlUrls = $this->getUnfurlUrlList($message, $matches[0]);
        foreach($requireUnfurlUrls as $url) {


            $command = "unfurl_link";
            $payload = [
                'url' => $url,
                'message_id' =>  $message->getId()
            ];

            $msg = new NodeUtilMessage($command, $payload);
            //$this->logger->info(__FUNCTION__ . " : Sending UNFURL COMMAND With" . print_r($msg->jsonSerialize(),true));

            $this->mqProducer->utilNodeProduce($msg);
        }
    }

    /**
     * @param Message $message
     * @param array $urls
     * @return array
     */
    private function getUnfurlUrlList(Message $message, array $urls) : array
    {
        //$this->logger->info(__FUNCTION__ . " URLS to check for dups " . print_r($urls, true));
        $requireUnfurlUrls = [];
        if (count($urls) > 0) {
            $unfurledUrls = [];
            foreach ($message->getAttachments() as $attachment) {
                if (MessageAttachment::ATTACHMENT_TYPE_UNFURL == $attachment->getContentType()) {
                    $content = $attachment->getContent();
                    $unfurledUrls[] = $content['url'];
                }
            }
            $requireUnfurlUrls = array_diff($urls, $unfurledUrls);
        }

        //$this->logger->info("URLS to unfurl = " . print_r($requireUnfurlUrls, true));
        return $requireUnfurlUrls;
    }

    /**
     * @param Message $message
     */
    private function unfurlAttachment(Message $message): void
    {
        $maxThumbMb = (int)$this->config['/thumbnail/max_size_mb'];

        if (empty($maxThumbMb)) {
            $maxThumbMb = 20;
        }

        foreach ($this->dataController->findAttachmentsForMessage($message->getId()) as $attachment) {
            if ($attachment->getContentType() == MessageAttachment::ATTACHMENT_TYPE_FILE) {
                $command = "create_file_preview";
                $arr = $attachment->getContent();
                //$this->logger->info("UNFURL ATTACHMENT " . print_r($arr,true));
                if (is_array($arr)) {
                    if ($arr['size'] > $maxThumbMb * 1000 * 1000) {
                        $this->logger->info("File " . $arr['file'] .  " is larger than the preview limit. ". $maxThumbMb ." MB. Skipping");
                        return;
                    }
                    
                    $name = $arr['file'];
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    if (Utility::isFilePreviewSupported($ext)) {

                        $url = "http://web/api/v1/internal/file.download?fspath=" . urlencode($arr['path']);
                        $payload = [
                            'url' => $url,
                            'name' => $name,
                            'path' => $arr['path'],
                            'message_id' => $message->getId()
                        ];

                        $msg = new NodeUtilMessage($command, $payload);
                        $this->logger->debug(__FUNCTION__ . " : Sending File Preview generation request: " . print_r($msg->jsonSerialize(), true));
                        $this->mqProducer->utilNodeProduce($msg);
                    }
                }
            }
        }
    }

    /**
     * Channel has been renamed
     *
     * @param ChannelRemovedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onChannelRemoved(ChannelRemovedEvent $event): void
    {
        $this->routeToClients($event);

        // Delete after reporting to clients
        if (!$this->fOps->onDeleteChannel($event->channel())) {
            $this->logger->error(__FUNCTION__ . " Failed file operation during delete for channel " . $event->channel()->getId());
        }

        if (!$this->dataController->deleteChannel((int)$event->channel()->getId())) {
            $this->logger->error(__FUNCTION__ . " Failed  during delete for channel " . $event->channel()->getId());
        }
    }


    /**
     * @param UserRemovedFromChannelEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onUserRemovedFromChannel(UserRemovedFromChannelEvent $event): void
    {
        // So.. The user has been removed from channel.. so the user who has been
        // kicked will not receive this event.. (all others will)

        $this->channelEventHandler->channelCacheUpdate($event);

        $this->routeToClients($event, 0, $event->userRemovedId());
    }

    /**
     * @param ChannelUpdateEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onChannelUpdated(ChannelUpdateEvent $event): void
    {
        // Update cache here before sending
        $this->channelEventHandler->channelCacheUpdate($event);
        $ignoreUserId = 0;
        $additionalUserId = 0;
        $onlyAdditionalUserId = false;

        if ($event->selfOnly()) {
            $additionalUserId = (int)$event->user()->getId();
            $onlyAdditionalUserId = true;
        }

        $this->routeToClients($event, $ignoreUserId, $additionalUserId, $onlyAdditionalUserId);

        $channel = $event->channel();
        $this->channelIndexer->indexChannel($channel->getId(), $channel->getName(), $channel->getBlurb());

    }

    /**
     * @param FSEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onFsEvent(FSEvent $event): void
    {
        /** Send to clients */
        $this->routeToClients($event);
    }


    /**
     * @param ActionCreatedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onActionCreated(ActionCreatedEvent $event): void
    {
        $action = $this->dataController->getActionWithUsers($event->getActionId());
        if (!empty($action)) {
            /** Send to clients */
            $this->routeToClients($event);

            $this->actionOps->notify($event);

            // save the action history
            // creation
            $history = ActionHistory::create($action->getId(), (int)$action->getCreatedBy(), 'created', [
                'action_name' => $action->getName()
            ]);
            $this->dataController->createActionHistory($history);
            // assignees
            foreach ($action->getUsers() as $user) {
                $history = ActionHistory::create($action->getId(), (int)$action->getCreatedBy(), 'user_added', [
                    'user_id' => $user['user_id'],
                ]);
                $this->dataController->createActionHistory($history);
            }
            // due date
            if (!empty($action->getDueOn())) {
                $history = ActionHistory::create($action->getId(), (int)$action->getCreatedBy(), 'due_date_updated', [
                    'from' => '',
                    'to' => $action->getDueOn(),
                ]);
                $this->dataController->createActionHistory($history);
            }

            if ($action->getUserObjects())

            if ($action->getParentId() !== null) {
                $history = ActionHistory::create($action->getId(), (int)$action->getCreatedBy(), 'moved_under', [
                    'action_id' => $action->getParentId(),
                ]);
                $this->dataController->createActionHistory($history);
                $history = ActionHistory::create($action->getParentId(), (int)$action->getCreatedBy(), 'subtask_added', [
                    'action_id' => $action->getId(),
                ]);
                $this->dataController->createActionHistory($history);
            }

            // includes the action on the index
            $this->actionIndexer->indexAction($action->getId(), $action->getName(), $action->getDesc(), $action->getChannelId());
        }
    }

    /**
     * @param ActionUpdatedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onActionUpdated(ActionUpdatedEvent $event): void
    {

        $action = $this->dataController->getActionWithUsers($event->getActionId());
        if (!empty($action)) {

            /** Send to clients */
            $this->routeToClients($event);

            $this->actionOps->notify($event);

            // save the action history
            $oldAction = $event->getOldAction();
            $newAction = $event->getNewAction();
            $entries = [];

            // name updated
            if ($oldAction->getName() !== $newAction->getName()) {
                $entries[] = ['name_updated', [
                    'from' => $oldAction->getName(),
                    'to' => $newAction->getName(),
                ]];
            }

            // action status updated
            if ($oldAction->getActionStatus() !== $newAction->getActionStatus()) {
                $entries[] = [$newAction->getActionStatus() === Action::ACTION_STATUS_COMPLETE ? 'completed' : 'uncompleted'];
            }

            // assignees changed
            $oldUsers = array_map(function ($item) {
                return $item['user_id'];
            }, $oldAction->getUsers());
            $newUsers = array_map(function ($item) {
                return $item['user_id'];
            }, $newAction->getUsers());
            foreach (array_diff($newUsers, $oldUsers) as $addedUserId) {
                $entries[] = ['user_added', ['user_id' => $addedUserId]];
            }
            foreach (array_diff($oldUsers, $newUsers) as $removedUserId) {
                $entries[] = ['user_removed', ['user_id' => $removedUserId]];
            }

            // due date changed
            if ($oldAction->getDueOn() !== $newAction->getDueOn()) {
                $entries[] = ['due_date_updated', [
                    'from' => $oldAction->getDueOn(),
                    'to' => $newAction->getDueOn(),
                ]];
            }

            // save the changes
            foreach ($entries as $entry) {
                [$historyType, $attachments] = $entry;
                $history = ActionHistory::create($action->getId(), (int)$action->getUpdatedBy(), $historyType, $attachments);
                $this->dataController->createActionHistory($history);
            }

            // update the action on the search index
            $this->actionIndexer->indexAction($action->getId(), $action->getName(), $action->getDesc(), $action->getChannelId());
        }
    }

    /**
     * @param ActionDeletedEvent $event
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function onActionDeleted(ActionDeletedEvent $event): void
    {
        $action = $event->getAction();
        if (!empty($action)) {
            /** Send to clients */
            $this->routeToClients($event);

            $this->actionOps->notify($event);
        }

        // save the action history
        if ($action->getParentId() !== null) {
            $history = ActionHistory::create($action->getParentId(), (int)$action->getCreatedBy(), 'subtask_removed', [
                'action_id' => $action->getId(),
            ]);
            $this->dataController->createActionHistory($history);
        }

        // delete the action from the search index
        // update the action on the search index
        $this->actionIndexer->remove((string) $action->getId());

    }

    /**
     * @param LockAcquireEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onLockAcquire(LockAcquireEvent $event): void
    {
        /** Send to clients */
        $this->routeToClients($event);
    }

    /**
     * @param LockReleaseEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onLockRelease(LockReleaseEvent $event): void
    {
        /** Send to clients */
        $this->routeToClients($event);
    }

    /**
     * @param NotifyAlert $event
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onNotifyAlert(NotifyAlert $event): void
    {
        $this->notificationFilter->notifyAlert($event->getAlert(), $event->getAlert()->getIsRead());
    }

    public function onAdminReportRequest(AdminReportRequestedEvent $event): void
    {
        $dbs = ContainerFacade::get(RootDatabaseService::class);
        $payload = $event->getPayloadArray();
        $data = $dbs->select($payload['sql']);

        // create the email message
        $emailMessage = $this->mailer->createMessage($payload['email'])
            ->subject("Report sent through admin: {$payload['reportName']}")
            ->from('admin')
            ->html('The requested report is attached.')
            ->plain('The requested report is attached.')
            ->attachment($payload['reportName'].'.csv', 'text/csv', CSV::array2csv($data));


        // send it
        $this->mailer->send($emailMessage);

    }

    public function onChannelJoinUpdateEvent(ChannelJoinUpdateEvent $event): void
    {
        $channelId = $event->getAssociatedChannelId();
        $managers = $this->dataController->getChannelMembersByRoleLevel($channelId, ChannelUser::CHANNEL_USER_ROLE_MANAGER);

        // send event and alert to all the managers on the channel
        foreach ($managers as $manager) {

            // first send the event to udpate the "waiting for approval list"
            $this->routeToClients($event, 0, $manager->getId(), true);

            // then create an alert to all the managers, if the event is a join request
            if ($event->shouldNotify()) {
                $alert = Alert::create(
                    $manager->getId(),
                    $channelId,
                    Alert::CONTEXT_TYPE_CHANNEL,
                    "{$event->user()->getDisplayName()} is asking to join {$event->channel()->getName()}.",
                    AlertIssuer::create($event->user()->getId(), $event->user()->getDisplayName()), Alert::ALERT_TYPE_REACTION);
                $this->dataController->upsertAlert($alert);
                $this->notificationFilter->notifyAlert($alert);
            }
        }
    }

    public function onActionMoved(ActionMovedEvent $event): void
    {
        $action = $event->getAction();

        // save the action history...
        if ($event->getParentId() !== $event->getPreviousParentId()) {

            // subtask removed
            if ($event->getPreviousParentId() !== null) {

                $history = ActionHistory::create($event->getPreviousParentId(), $event->getMovedBy(), 'subtask_removed', [
                    'action_id' => $action->getId(),
                ]);
                $this->dataController->createActionHistory($history);

                $history = ActionHistory::create($action->getId(), $event->getMovedBy(), 'parent_removed', [
                    'action_id' => $event->getPreviousParentId(),
                ]);
                $this->dataController->createActionHistory($history);

            }

            // subtask added
            if ($event->getParentId() !== null) {

                $history = ActionHistory::create($event->getParentId(), $event->getMovedBy(), 'subtask_added', [
                    'action_id' => $action->getId(),
                ]);
                $this->dataController->createActionHistory($history);

                // new parent
                $history = ActionHistory::create($action->getId(), $event->getMovedBy(), 'moved_under', [
                    'action_id' => $event->getParentId(),
                ]);
                $this->dataController->createActionHistory($history);

            }

        }

        $this->routeToClients($event);
    }

    /**
     * @param GroupCreatedEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onGroupCreated(GroupCreatedEvent $event): void
    {
        $this->routeToClients($event, 0, $event->userId(), true);
    }

    /**
     * @param GroupCreatedEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onGroupUpdated(GroupUpdatedEvent $event): void
    {
        $this->routeToClients($event, 0, $event->userId(), true);
    }

    /**
     * @param GroupCreatedEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onGroupDeleted(GroupDeletedEvent $event): void
    {
        $this->routeToClients($event, 0, $event->userId(), true);
    }

    /**
     * @param GroupReorderEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onGroupReorder(GroupReorderEvent $event): void
    {
        $this->routeToClients($event, 0, $event->userId(), true);
    }


    /**
     * @param PingEvent $event
     */
    public function onPing (PingEvent $event): void
    {
        // Create a RTMMessage and send it directly to that specific client device that generated the ping
        $nEvent = new NormalizedEvent($event::NAME, ['ping_token' => $event->pingToken()]);
        $rtmMessage =  new RtmMessage($nEvent, $event->rtmToken());
        $this->mqProducer->rtmMQProduce($rtmMessage);
    }

    /**
     * @param MeetingInviteEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onMeetingInvite (MeetingInviteEvent $event): void
    {
        // Create a RTMMessage and send it directly to that specific client device that generated the ping
        $this->routeToClients($event, 0, $event->getToUserId(), true);

        $this->notificationFilter->handleMobilePushCall($event->getCallHash(), $event->getToUserId());
    }

    /**
     * @param MeetingInviteAcceptEvent $event
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function onMeetingInviteAccept (MeetingInviteAcceptEvent $event): void
    {
        // Create a RTMMessage and send it directly to that specific client device that generated the ping
        $this->routeToClients($event, 0, $event->getToUserId(), true);
    }

    protected function handleTeamMemberEvent(AbstractTeamMemberEvent $event): void
    {
        $members = $this->dataController->getTeamMembers($event->getTeam()->getId());

        // send the event to all team members (not for the user that was added/removed/updated)
        foreach ($members as $member) {
            $this->routeToClients($event, 0, $member->getId(), true);
        }
    }

    public function onTeamAddMember(TeamAddMemberEvent $event): void
    {
        $this->handleTeamMemberEvent($event);
    }

    public function onTeamUpdateMember(TeamUpdateMemberEvent $event): void
    {
        $this->handleTeamMemberEvent($event);
    }

    public function onTeamRemoveMember(TeamRemoveMemberEvent $event): void
    {
        $members = $this->dataController->getTeamMembers($event->getTeam()->getId());

        // add the removed user to the recipients list
        $members[] = $event->getMember();

        // send the event to all team members (not for the user that was added/removed/updated)
        foreach ($members as $member) {
            $this->routeToClients($event, 0, $member->getId(), true);
        }
    }

    protected function handleTeamChannelEvent(AbstractTeamChannelEvent $event): void
    {
        $recipients = $this->dataController->getTeamMembers($event->getTeam()->getId());

        // if the channel is not open for team join, only notify managers
        $channel = $event->getChannel();
        $team = $event->getTeam();
        if (!$channel->isOpenForTeamJoin()) {
            $recipients = array_filter($recipients, function (User $user) use ($team) {
                $teamUser = $this->dataController->getTeamUser($user->getId(), $team->getId());
                return $teamUser->getUserRole() >= TeamUser::TEAM_USER_ROLE_MANAGER;
            });
        }

        // notify the recipients
        foreach ($recipients as $recipient) {
            $this->routeToClients($event, 0, $recipient->getId(), true);
        }
    }

    public function onTeamAddChannel(TeamAddChannelEvent $event): void
    {
        $this->handleTeamChannelEvent($event);
    }

    public function onTeamUpdateChannel(TeamUpdateChannelEvent $event): void
    {
        $this->handleTeamChannelEvent($event);
    }

    public function onTeamRemoveChannel(TeamRemoveChannelEvent $event): void
    {
        $this->handleTeamChannelEvent($event);
    }

    public function onTeamUpdate(TeamUpdateEvent $event): void
    {
        $members = $this->dataController->getTeamMembers($event->getTeam()->getId());

        // send the event to all team members
        foreach ($members as $member) {
            $this->routeToClients($event, 0, $member->getId(), true);
        }
    }



}
