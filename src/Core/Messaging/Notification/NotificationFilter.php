<?php


namespace CodeLathe\Core\Messaging\Notification;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Messaging\Events\AlertEvent;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\FSEvent;
use CodeLathe\Core\Messaging\Events\RtmInterface;
use CodeLathe\Core\Messaging\MessageQueue\MQProducer;
use CodeLathe\Core\Messaging\MessageQueue\NodeUtilMessage;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class NotificationFilter
 *
 * This class is responsible for filtering and routing events to clients automatically
 * When anything is required to be sent to various type of clients, this is the clearinghouse for those
 * messages
 *
 * @package CodeLathe\Core\Messaging\Notification
 */
class NotificationFilter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @var MQProducer
     */
    protected $mqProducer;

    /**
     * @var RtmOperations
     */
    protected $rtmOps;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var MailerServiceInterface
     */
    protected $mailerService;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * NotificationFilter constructor.
     * @param LoggerInterface $logger
     * @param RtmOperations $rtmOps
     * @param MQProducer $mqProducer
     * @param DataController $dataController
     * @param MailerServiceInterface $mailerService
     * @param NormalizedObjectFactory $objectFactory
     * @param CacheItemPoolInterface $cache
     * @param ServiceRegistryInterface $config
     */
    public function __construct(LoggerInterface $logger,
                                RtmOperations $rtmOps,
                                MQProducer $mqProducer,
                                DataController $dataController,
                                MailerServiceInterface $mailerService,
                                NormalizedObjectFactory $objectFactory,
                                CacheItemPoolInterface $cache,
                                ServiceRegistryInterface $config)
    {
        $this->logger = $logger;
        $this->rtmOps = $rtmOps;
        $this->mqProducer = $mqProducer;
        $this->dataController = $dataController;
        $this->mailerService = $mailerService;
        $this->objectFactory = $objectFactory;
        $this->cache = $cache;
        $this->config = $config;
    }

    /**
     * Central routing function
     *
     * @param ASEvent $event
     * @param int $ignoreId
     * @param int $addUserId
     * @param bool $onlyAdditionalUserId
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function notifyClients(ASEvent $event, int $ignoreId = 0 , int $addUserId = 0, bool $onlyAdditionalUserId = false)
    {
        //TODO: Determine if there is a policy that controls
        // the notification for the event/user/channel
        // Send to Websocket
        $this->handleRtmNotification($event, $ignoreId, $addUserId, $onlyAdditionalUserId);

    }

    /**
     * Send messages to various clients
     *
     * @param ASEvent $event
     * @param int $ignoreUserId Ignore sending message to this id. Ideal for preventing sending message to the originator
     * @param int $additionalUserId
     * @param bool $onlyAdditionalUserId - Send only to the additional User ID (Ignore all other users)
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function handleRtmNotification(ASEvent $event, int $ignoreUserId=0,
                                          int $additionalUserId = 0, bool $onlyAdditionalUserId = false)
    {
        //$this->logger->info(__FUNCTION__ . " : EVENT " . $event::eventName() .  " For Channel = " . $event->getAssociatedChannelId());
        if ($event instanceof RtmInterface) {

            // Normalize this event (if it's not dependent on the user that will receive the event)
            $nEvent = null;
            if (!$this->objectFactory->isEventNormalizedByUser($event)) {
                $nEvent = $this->objectFactory->normalizeEvent($event);
            }

            $userToNotify = [];
            if (!$onlyAdditionalUserId) { // If only the specified user id have to be notified, then ignore looking for users to notify
                $userToNotify = $this->getUsersToNotify($event);
            }

            if ($additionalUserId != 0) {
                $addUser = $this->dataController->getUserById($additionalUserId);
                if (!empty($addUser)) {
                    $userToNotify[] = $addUser;
                }
            }

            // Get the members of the channel associated with this event
            foreach ($userToNotify as $channelMember) {
                if ($ignoreUserId != 0 && $ignoreUserId === (int)$channelMember->getId()) {
                    continue;
                }

                // if the normalized event is not defined so far, we need to normalize it for each user recipient
                if ($nEvent === null){
                    $nEvent = $this->objectFactory->normalizeEventForUser($event, $channelMember);
                }

                // Produce one message for each of the connection for each member
                $rtmEvents = $this->rtmOps->getRtmMessages($channelMember->getId(), $nEvent);

                if (count($rtmEvents)>0) {
                    //$this->logger->debug(__FUNCTION__ . " Sending event " . $event::backgroundEventName() . " to " .
                    //                    count($rtmEvents) . " connections of " . $channelMember->getDisplayName());
                    foreach ($rtmEvents as $rtmEvent) {
                        try {
                            $this->mqProducer->rtmMQProduce($rtmEvent);
                        } catch (\Exception $e) {
                            $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
                        }
                    }
                }
            }
        } else {
            $this->logger->debug($event::backgroundEventName() .
                                 " does NOT implement RTM interface. Ignoring route request");
        }
    }

    /**
     *
     * Get list of users to notify this event. Typically each event is associated with
     * a channel and the channel members will be notified.
     *
     * @param ASEvent $event
     * @return User[]
     * @throws DatabaseException
     */
    private function getUsersToNotify(ASEvent $event): array
    {
        $userToNotify = [];
        // File system events may not have any channel association
        if ( ($event instanceof FSEvent) && (int)$event->getAssociatedChannelId() < 0) {
            //Just notify the owner if present
            $owner = $this->dataController->getUserById($event->getUserID());
            if (!empty($owner)) {
                $userToNotify[] = $owner;
            }
        }
        else {
            foreach ($this->dataController->getUsersForChannel($event->getAssociatedChannelId()) as $userRec) {
                $userToNotify[] = User::withDBData($userRec);
            }
        }

        return $userToNotify;
    }


    /**
     * @param Alert $alert
     * @param bool $isUpdate
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function notifyAlert(Alert $alert, bool $isUpdate=false)
    {
        $nEvent = $this->objectFactory->normalizeAlertToEvent($alert);
        if ($isUpdate) {
            $nEvent->setEventName('alert.update');
            //$this->logger->info(print_r($nEvent->getArray(), true));
        }

        // This alert will have to be sent to the target of this alert
        $rtmEvents = $this->rtmOps->getRtmMessages((int)$alert->getUserId(), $nEvent);
        if (count($rtmEvents)>0) {
            $this->logger->info(__FUNCTION__ . " Sending alert  to " .
                                count($rtmEvents) . " connections of " . $alert->getUserId());
            foreach ($rtmEvents as $rtmEvent) {
                try {
                    $this->mqProducer->rtmMQProduce($rtmEvent);
                } catch (\Exception $e) {
                    $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
                }
            }
        }

        // Send to Mobile push
        if (!$alert->isMutedAlert() &&
            !$isUpdate &&
            $this->config->get('/app/firebase_enabled')) {
            $this->handleMobilePushAlert($alert);
        }
    }

    /**
     *
     * Send a mobile push notification
     *
     * @param Alert $alert
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function handleMobilePushAlert(Alert $alert): void
    {

        //$this->logger->debug("Handling mobile push for alert `{$alert->getAlertText()}` for user {$alert->getUserId()}");

        // ignore muted users
        $channelId = null;

        switch ($alert->getContextType()) {
            case Alert::CONTEXT_TYPE_CHANNEL:
                $channelId = $alert->getContextId();
                break;
            case Alert::CONTEXT_TYPE_MESSAGE:
                $message = $this->dataController->getMessageById($alert->getContextId());
                $channelId = $message->getChannelId() ?? null;
                break;
            case Alert::CONTEXT_TYPE_ACTION:
                $action = $this->dataController->getActionById($alert->getContextId());
                $channelId = $action->getChannelId();
                break;
        }
        if ($channelId !== null) {
            $channelUser = $this->dataController->getUserChannel($channelId, $alert->getUserId());
            if ($channelUser !== null && ($channelUser->isMuted() || $this->dataController->getDMSentByInviterCount($channelId) > ChannelUser::FREE_MESSAGES_ON_DM)) {
                return;
            }
        }

        // Normalize this event
        $normalizedAlert = $this->objectFactory->normalizeAlertToObject($alert);

        // check if there is any device registered for this user on firebase
        // ignore iOS devices when the alert is a quote or mention
        if ($alert->getType() === Alert::ALERT_TYPE_MENTION || $alert->getType() === Alert::ALERT_TYPE_QUOTE) {
            $tokens = $this->dataController->findFCMDevicesForUser($alert->getUserId(), 'android');
        } else {
            $tokens = $this->dataController->findFCMDevicesForUser($alert->getUserId());
        }

        $this->logger->debug("Found " . count($tokens) . " device(s) for user `{$alert->getUserId()}`: " . print_r($tokens, true));

        // grab the alert payload
        $alertPayload = $normalizedAlert->getArray();

        // from the payload, parse the title and body (required by IOS)
        // first remove the mention markdowns
        $alertText = preg_replace('/\[([^]]+)\]\([^)]+\)/', '$1', $alertPayload['alert_text']);
        // remove double spaces (double spaces are ugly on push notifications
        $alertText = preg_replace('/\s+/', ' ', $alertText);
        // then split title and body (when both exists)
        if (preg_match('/^[^:]+:[^:]+$/', $alertText)) {
            [$title, $body] = explode(':', $alertText);
            $title = trim($title);
            $body = trim($body);
        } else {
            $body = trim($alertText);
        }

        $totalAlerts = $this->dataController->getUnreadMessagesForUser($alert->getUserId());

        $payload = [
            'fcm_tokens' => $tokens,
            'event' => 'alert.notification',
            'payload' => ['alert' => $alertPayload],
            'message_body' => $body,
            'badge' => $totalAlerts,
        ];
        if (isset($title)) {
            $payload['message_title'] = $title;
        }

        $msg = new NodeUtilMessage('fcm', $payload);

        try {
            $this->mqProducer->utilNodeProduce($msg);
        } catch (\Exception $e) {
            $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
        }

    }

    /**
     * @param Channel $channel
     * @param Message $message
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function notifyChat(Channel $channel, Message $message)
    {
        //$this->logger->debug("Handing notifications for new message on channel {$channel->getName()}: " . substr($message->getText(), 0, 64) . ' ...');

        // find all members for this channel
        $generator = $this->dataController->getUsersForChannel($channel->getId());

        // send the notification for all users
        foreach ($generator as $record) {
            $user = User::withDBData($record);
            $channelUser = $this->dataController->getUserChannel($channel->getId(), $user->getId());
            // Do not do FCM push for muted channels

            $dmMuted = false;
            if ($channel->getOneOnOne()) {
                $dmMuted = !$channel->getOneOnOneApproved() && $this->dataController->getDMSentByInviterCount($channel->getId()) > ChannelUser::FREE_MESSAGES_ON_DM;
            }

            if (!$channelUser->isMuted() && !$dmMuted) {
                $this->handleMobilePushMessage($message, $user);
            }
        }
    }

    /**
     * @param Message $message
     * @param User $user
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function handleMobilePushMessage(Message $message, User $user)
    {
        //$this->logger->debug("Handling new message mobile push for user: `{$user->getEmail()}`.");

        // Normalize the message
        $normalizedMessage = $this->objectFactory->normalizedObject($message);

        // Message title/body
        $messageBody = $message->getText();
        $messageTitleSize = 30;
        $messageTitle = strlen($messageBody) > $messageTitleSize ? substr($messageBody, $messageTitleSize) . '...' : $messageBody;

        // check if there is any device registered for this user on firebase
        if ($message->getUserId() === $user->getId()) {

            // if the destination user is the author of the message, we only send notifications to android devices
            $tokens = $this->dataController->findFCMDevicesForUser($user->getId(), 'android');

        } else {

            // if it's not the case, send to all devices
            $tokens = $this->dataController->findFCMDevicesForUser($user->getId());

        }

        //$this->logger->debug("Found " . count($tokens) . " device(s) for user `{$user->getEmail()}`: " . print_r($tokens, true));

        $totalAlerts = $this->dataController->getUnreadMessagesForUser($user->getId());

        if (!empty($tokens)) {
            $payload = [
                'fcm_tokens' => $tokens,
                'event' => 'message.notification',
                'payload' => [
                    'message' => $normalizedMessage->getArray(),
                ],
                'message_title' => $messageTitle,
                'message_body' => $messageBody,
                'badge' => $totalAlerts,
            ];

            $msg = new NodeUtilMessage('fcm', $payload);

            try {
                $this->mqProducer->utilNodeProduce($msg);
            } catch (\Exception $e) {
                $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
            }
        }
    }

    /**
     * This will send a unread message count update for user
     *
     * @param User $user
     */
    public function sendUnreadCountUpdate(User $user)
    {
        //$this->logger->debug("Handling unread count mobile push for user: `{$user->getEmail()}`.");

        // check if there is any device registered for this user on firebase
        $tokens = $this->dataController->findFCMDevicesForUser($user->getId());


        $totalAlerts = $this->dataController->getUnreadMessagesForUser($user->getId());
        //$this->logger->debug("Total alert = $totalAlerts : Found " . count($tokens) . " device(s) for user `{$user->getEmail()}`: " . print_r($tokens, true));
        //$this->logger->debug("Found " . count($tokens) . " device(s) for user `{$user->getEmail()}`: " . print_r($tokens, true));

        if (!empty($tokens)) {
            $payload = [
                'fcm_tokens' => $tokens,
                'event' => 'unreadcount.notification',
                'badge' => $totalAlerts,
            ];

            $msg = new NodeUtilMessage('fcm', $payload);

            try {
                $this->mqProducer->utilNodeProduce($msg);
            } catch (\Exception $e) {
                $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
            }
        }
    }

    public function handleMobilePushCall(string $callHash, int $userId)
    {

        // normalize the call data, and do the validations...
        $call = $this->dataController->getCallByHash($callHash);
        if ($call === null) {
            $this->logger->error("Impossible to send invalid call to mobile clients: $callHash");
            return;
        }

        $user = $this->dataController->getUserById($userId);
        if ($user === null) {
            $this->logger->error("Invalid call user: $userId");
            return;
        }

        $normalizedCall = $this->objectFactory->normalizedObject($call);


        // For now, we're using FCM for calls, both for android, and iOS,
        // because native calls aren't implemented in none of the systems.
        // the original comment is set as to-do bellow

        // TODO - handle Android devices first (through FCM)...
        // TODO - for calls, we use FCM for Android, and Apple push api for iOS

        // first check if there is any device registered for this user on FCM
        $tokens = $this->dataController->findFCMDevicesForUser($userId);

        $title = "{$user->getDisplayName()} has started a call";

        if (!empty($tokens)) {

            // send the request to nodeutil to contact FCM
            $payload = [
                'fcm_tokens' => $tokens,
                'event' => 'call_invite.notification',
                'payload' => [
                    'call' => $normalizedCall->getArray(),
                ],
                'message_title' => $title,
                'message_body' => $title
            ];

            $msg = new NodeUtilMessage('fcm', $payload);

            try {
                $this->mqProducer->utilNodeProduce($msg);
            } catch (\Exception $e) {
                $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
            }

        }


        // send the call notifications to iOS push api (if there are iOS devices connected)

        // first grab the iOS devices registered for the user
        $iosDevices = $this->dataController->findIOSDevicesForUser($userId);

        $cert = Directories::resources('apple/voip/VOIP.pem');

        foreach ($iosDevices as $iosDeviceToken) {

            $url = $this->config->get('/app/apple_push_url') . '/' . $iosDeviceToken;

            $body = [
                'aps' => [
                    'call' => $normalizedCall,
                ]
            ];

            // we use curl (not Guzzle i.e), because only native curl supports the http2 version
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_VERBOSE => false,
                CURLOPT_POSTFIELDS => Json::encode($body),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_SSLCERT => $cert,
            ]);
            $response = curl_exec($ch);

            if ($response === false) {
                // Log the curl error and ignore
                $this->logger->error(__FUNCTION__ . ' : CURL error: ' . curl_error($ch) . '(' . curl_error($ch) . ')');
            } elseif (!empty($response)) {

                $response = Json::decode($response, true);

                if (($response['reason'] ?? '') === 'BadDeviceToken') {
                    $this->dataController->disconnectIosDevice($iosDeviceToken);
                    $this->logger->warning(__FUNCTION__ . " : Invalid iOS device found: $iosDeviceToken");
                } else {
                    // Unhandled error..., log the response and ignore
                    $this->logger->error(__FUNCTION__ . " : CURL return (error): " . print_r($response, true));
                }
            }

            // empty response is the successful response, so we're done, just close the curl handler
            curl_close($ch);
        }

    }

}