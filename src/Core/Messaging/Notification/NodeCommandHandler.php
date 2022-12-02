<?php declare(strict_types=1);


namespace CodeLathe\Core\Messaging\Notification;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChatAuthorizationException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Channel\ChannelEventHandler;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Managers\Realtime\RtmToken;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChannelReadStatusUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChatUpdatedEvent;
use CodeLathe\Core\Messaging\Events\UserOfflineEvent;
use CodeLathe\Core\Messaging\Events\UserOnlineEvent;
use CodeLathe\Core\Messaging\Events\UserTypingEvent;
use CodeLathe\Core\Messaging\MessageQueue\MQProducer;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class NodeCommandHandler This class is responsible for handling request from node services.
 *
 * @package CodeLathe\Core\Messaging\Notification
 */
class NodeCommandHandler
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

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
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var NotificationFilter
     */
    protected $notificationFilter;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    /**
     * @var EventManager Manager
     */
    protected $eventManager;
    /**
     * @var ChannelEventHandler
     */
    private $channelEventHandler;


    /**
     * NotificationFilter constructor.
     * @param LoggerInterface $logger
     * @param ChannelOperations $channelOps
     * @param RtmOperations $rtmOps
     * @param MQProducer $mqProducer
     * @param DataController $dataController
     * @param MailerServiceInterface $mailerService
     * @param NormalizedObjectFactory $objectFactory
     * @param CacheItemPoolInterface $cache
     * @param NotificationFilter $notificationFilter
     * @param ChatOperations $chatOps
     * @param EventManager $eventManager
     * @param ChannelEventHandler $channelEventHandler
     */
    public function __construct(LoggerInterface $logger,
                                ChannelOperations $channelOps,
                                RtmOperations $rtmOps,
                                MQProducer $mqProducer,
                                DataController $dataController,
                                MailerServiceInterface $mailerService,
                                NormalizedObjectFactory $objectFactory,
                                CacheItemPoolInterface $cache,
                                NotificationFilter $notificationFilter,
                                ChatOperations $chatOps,
                                EventManager $eventManager,
                                ChannelEventHandler $channelEventHandler)
    {
        $this->logger = $logger;
        $this->channelOps = $channelOps;
        $this->rtmOps = $rtmOps;
        $this->mqProducer = $mqProducer;
        $this->dataController = $dataController;
        $this->mailerService = $mailerService;
        $this->objectFactory = $objectFactory;
        $this->cache = $cache;
        $this->notificationFilter = $notificationFilter;
        $this->chatOps = $chatOps;
        $this->eventManager = $eventManager;
        $this->channelEventHandler = $channelEventHandler;
    }

    /**
     *
     * This is message from the realtime engine.
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function handleIncomingRTMMessage(array $jsonArray) : void
    {
        if (!empty($jsonArray['command'])) {
            if ($jsonArray['command'] === 'ws_ephemeral_typing') {
                $this->notifyTypingEvent($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'ws_read_notification') {
                $this->recordReadNotification($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'ws_add_reaction') {
                $this->handleEmoticonToMessage($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'ws_remove_reaction') {
                $this->handleEmoticonToMessage($jsonArray, true);
                return;
            }
            else if ($jsonArray['command'] === 'ws_purge_connection') {
                $this->purgeConnection($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'ws_cache_connection') {
                $this->cacheConnection($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'ws_stats') {
                $this->handleWsStats($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'ws_all_read_notification') {
                $this->recordAllReadNotification($jsonArray);
                return;
            }

        }
        $this->logger->error(__FUNCTION__ . ' : Unknown event received: ' .
                             print_r($jsonArray,true));
    }

    /**
     *
     * This message is from Utility Node server.
     *
     * @param array $jsonArray Payload
     * @throws ChannelMalformedException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws ChatAuthorizationException
     */
    public function handleIncomingNodeUtilMessage(array $jsonArray) : void
    {
        if (!empty($jsonArray['command'])) {
            if ($jsonArray['command']   === 'unfurl_response') {
                $this->handleUnfurlResponse($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'file_preview_ready') {
                $this->handleFilePreviewReady($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'post_email_response_message') {
                $this->handlePostEmailResponseMessage($jsonArray);
                return;
            }
            else if ($jsonArray['command'] === 'remove_invalid_fcm_device_id') {
                $this->handleRemoveInvalidFcmDeviceId($jsonArray);
                return;
            }
        }
        $this->logger->error(__FUNCTION__ . ' : Unknown event received: ' .
                             print_r($jsonArray,true));
    }

    /**
     *
     * This is a message from one RTM client and needs to be routed to all other RTM clients
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    private function notifyTypingEvent(array $jsonArray): void
    {
        if (!RequestValidator::validateArray(['user_signature', 'channel_id'], $jsonArray)) {
            return;
        }

        if (!$user=$this->validateUserSignature($jsonArray)) { return; }

        // Only need to send to RTM client
        $typingEvent = new UserTypingEvent($user, (int)$jsonArray['channel_id']);
        $this->notificationFilter->handleRtmNotification($typingEvent, (int)$user->getId());
    }

    /**
     * process a read notification  from a client.
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    private function recordReadNotification(array $jsonArray): void
    {
        if (empty($jsonArray['user_signature'])) {
            return;
        }

        if (!RequestValidator::validateArray(['user_signature', 'channel_id', 'message_id'], $jsonArray)) {
            $this->logger->error(__FUNCTION__ . " Invalid message for read notification received " .
                                 print_r($jsonArray, true));
            return;
        }

        if (!$user = $this->validateUserSignature($jsonArray)) {
            return;
        }

        $messageId = (int)$jsonArray['message_id'] ?? null;
        $channelId = (int) $jsonArray['channel_id'] ?? null;

        $this->processReadNotification($user, $channelId, $messageId);

    }

    /**
     * @param User $user
     * @param int $channelId
     * @param int $messageId
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function processReadNotification(User $user, int $channelId, int $messageId): void
    {
        try {
            $this->dataController->channelUserUpdateReadWatermark($channelId, $user->getId(), $messageId);
        } catch (DatabaseException $e) {
            return;
        }

        $channelUnreadCount = $this->dataController->getUnReadMessageCount($channelId, $user->getId(), $messageId);

        $userUnreadCount = $this->dataController->getUnreadMessagesForUser($user->getId());

        // Raise the read status update event for the channel
        $channelReadStatusUpdateEvent = new ChannelReadStatusUpdateEvent($channelId, $user->getId(), $messageId, $userUnreadCount, $channelUnreadCount);
        $this->notificationFilter->handleRtmNotification($channelReadStatusUpdateEvent);

        // Clear alerts for all messages smaller than this one
        $this->clearAlertsBelowReadWatermark($channelId, $user->getId(), $messageId);

    }

    /**
     *
     * Mark all messages for a channel as read for a given user.
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    private function recordAllReadNotification(array $jsonArray): void
    {
        if (!RequestValidator::validateArray(['user_signature', 'channel_id'], $jsonArray)) {
            $this->logger->error(__FUNCTION__ . " Invalid message for all read notification received " .
                                 print_r($jsonArray, true));
            return;
        }

        if (!$user=$this->validateUserSignature($jsonArray)) { return; }

        $channelId = (int)$jsonArray['channel_id'];
        $this->logger->info("Marking all messages of channel " . $jsonArray['channel_id'] . " marked as read for " . $user->getDisplayName());
        $this->processReadAllNotification($user, $channelId);

    }

    /**
     * @param User $user
     * @param int $channelId
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function processReadAllNotification(User $user, int $channelId): void
    {
        $messageId = $this->chatOps->setReadWatermarkToLastMessage($channelId, (int)$user->getId());

        $channelUnreadCount = $this->dataController->getUnReadMessageCount($channelId, $user->getId(), $messageId);
        $userUnreadCount = $this->dataController->getUnreadMessagesForUser($user->getId());

        // Raise the read status update event for the channel
        $channelReadStatusUpdateEvent = new ChannelReadStatusUpdateEvent($channelId, $user->getId(), $messageId, $userUnreadCount, $channelUnreadCount);
        $this->notificationFilter->handleRtmNotification($channelReadStatusUpdateEvent);

        // Clear alerts for all messages smaller than this one
        $this->clearAlertsBelowReadWatermark($channelId, $user->getId(), $messageId);

    }


    /**
     * process a read notification  from a client.
     *
     * @param array $jsonArray
     * @param bool $remove
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    private function handleEmoticonToMessage(array $jsonArray, bool $remove=false): void
    {
        if (!RequestValidator::validateArray(['user_signature', 'message_id', 'emoji_value'], $jsonArray)) {
            $this->logger->error(__FUNCTION__ . " Invalid message received " . print_r($jsonArray, true));
            return;
        }

        if (!$user=$this->validateUserSignature($jsonArray)) { return; }

        try {
            $this->chatOps->handleEmoticon($user, (int)$jsonArray['message_id'], $jsonArray['emoji_value'], $remove);
        }
        catch (ASException $ex) {
            $this->logger->debug(__FUNCTION__ . " : " . $ex->getMessage());
        }
    }

    /**
     *
     * Remove a token from our cache list of closed connection
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    private function purgeConnection(array $jsonArray)
    {
        if (!RequestValidator::validateArray(['user_signature'], $jsonArray)) {
            $this->logger->error(__FUNCTION__ . " Invalid message received " . print_r($jsonArray, true));
            return;
        }

        // Purge connection of this user
        [$userId, $fingerprint] = explode('_', $jsonArray['user_signature']);

        $this->logger->info(__FUNCTION__ . " Removing connections of $userId and $fingerprint");
        $token = RtmToken::create((int)$userId, "dontcare", 'dontcare', 'dontcare', 'dontcare', $fingerprint, 'dontcare');
        $this->rtmOps->removeRtmToken($token);

        if (!$this->rtmOps->isUserOnline((int)$userId)) {
            $user = $this->dataController->getUserById((int)$userId);
            $this->logger->info(__FUNCTION__ . " User " . $user->getDisplayName() . " is going offline.");

            // We can raise user is off line event to all channels of this user
            foreach ($this->dataController->getChannelsForUser((int)$userId) as $channelRec) {
                $channel = Channel::withDBData($channelRec);

                $event = new UserOfflineEvent($user, (int)$channel->getId());
                $this->notificationFilter->handleRtmNotification($event);
            }
        }
    }


    /**
     * @param array $jsonArray
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    private function cacheConnection(array $jsonArray)
    {
        if (!RequestValidator::validateArray(['user_signature'], $jsonArray)) {
            $this->logger->error(__FUNCTION__ . " Invalid message received " . print_r($jsonArray, true));
            return;
        }

        $userOfflineUntilNow = !$this->rtmOps->isUserOnline((int)$jsonArray['user_id']);

        // Cache connection of this user
        [$userId, $fingerprint] = explode('_', $jsonArray['user_signature']);

        $this->logger->info(__FUNCTION__ . " Registering connection of $userId and $fingerprint");
        $token = RtmToken::create((int)$jsonArray['user_id'],
            $jsonArray['user_name'],
            $jsonArray['client_ip'],
            $jsonArray['user_agent'],
            $jsonArray['expiry'],
            $jsonArray['finger_print'],
            $jsonArray['topic']);
        $this->rtmOps->cacheRtmToken($token);


        if ($userOfflineUntilNow) {
            $user = $this->dataController->getUserById((int)$jsonArray['user_id']);
            // This means, this user is coming online just now
            $this->logger->info(__FUNCTION__ . " User " . $jsonArray['user_name'] . " is coming online.");
            foreach ($this->dataController->getChannelsForUser((int)$jsonArray['user_id']) as $channelRec) {
                $channel = Channel::withDBData($channelRec);
                $event = new UserOnlineEvent($user, (int)$channel->getId());
                $this->eventManager->publishEvent($event);
            }
        }
    }
    
    /**
     * Validate the signature and determine the user object
     *
     * @param array $jsonArray
     * @return User|null
     * @throws DatabaseException
     */
    private function validateUserSignature(array $jsonArray): ?User
    {
        [$userId, $fingerprint] = explode('_', $jsonArray['user_signature']);

        if(empty($userId)) {
            // Invalid user. Ignore.
            $this->logger->error(__FUNCTION__ ." Unknown user signature " . print_r($jsonArray,true));
            return null;
        }

        if (empty($user = $this->dataController->getUserById((int)$userId))) {
            $this->logger->error(__FUNCTION__ ." Unknown user " . print_r($jsonArray,true));
            return null;
        }
        return $user;
    }

    /**
     * Add unfurl data and raise message update notification
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    private function handleUnfurlResponse(array $jsonArray): void
    {
        $messageId = $jsonArray['message_id'];

        // This is an internal message. We control both ends. No auth needed
        $message = $this->dataController->getMessageById($messageId);
        if (empty($message)) {
            $this->logger->error("Message $messageId does not exist. Ignoring unfurl response");
            return;
        }

        $messageAttachment = MessageAttachment::create($jsonArray['unfurl_result'],MessageAttachment::ATTACHMENT_TYPE_UNFURL, $jsonArray['unfurl_result']['url'], $message->getId());
        $message->addAttachment($messageAttachment);

        try {

            // TODO - Since the attachment field is deprecated, we can remove this update message in the future.
            $this->dataController->updateMessage($message);

            $this->dataController->createMessageAttachment($messageAttachment);

        }
        catch(\PDOException | Exception $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return;
        }

        // Message update notification
        $messageUpdateEvent = new ChatUpdatedEvent($message);
        $this->notificationFilter->handleRtmNotification($messageUpdateEvent);
    }

    /**
     * Add file preview data and raise message update notification
     *
     * @param array $jsonArray
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    private function handleFilePreviewReady(array $jsonArray): void
    {
        $messageId = $jsonArray['message_id'];

        // This is an internal message. We control both ends. No auth needed
        $message = $this->dataController->getMessageById($messageId);
        if (empty($message)) {
            $this->logger->error("Message $messageId does not exist. Ignoring file preview response");
            return;
        }

        // Message update notification (This will regenerate the thumb available flag)
        $messageUpdateEvent = new ChatUpdatedEvent($message);
        $this->notificationFilter->handleRtmNotification($messageUpdateEvent);
    }

    /**
     *
     * Stat reported from a node service
     *
     * @param array $jsonArray
     * @throws InvalidArgumentException
     */
    private function handleWsStats(array $jsonArray): void
    {
        // Cache this
        if (empty($jsonArray['os_hostname'] || empty($jsonArray['os_ip'] || empty($jsonArray['source'])))) {
            $this->logger->error("Invalid stats received " . print_r($jsonArray,true));
            return;
        }

        $key = 'airsend_ws_stats';
        $hostKey = $jsonArray['source'] . '_' . $jsonArray['os_hostname'];
        $item = $this->cache->getItem($key) ;

        if (!$item->isHit()) {
            $map = [$hostKey => $jsonArray];
            $item->set($map);
        } else {
            $map = $item->get();
            $map[$hostKey] = $jsonArray;
            $item->set($map);
        }

        //$this->logger->info(__FUNCTION__ . " Save " . print_r($item->get(), true));
        $this->cache->save($item);

        // Save the load separately
        $loadKey = 'airsend_ws_connections';
        $loadItem = $this->cache->getItem($loadKey);
        if (!$loadItem->isHit()) {
            $map = [$jsonArray['kafka_consumer_topic'] => $jsonArray['active_connections']];
            $loadItem->set($map);
        } else {
            $map = $loadItem->get();
            $map[$jsonArray['kafka_consumer_topic']] = $jsonArray['active_connections'];
            $loadItem->set($map);
        }
        $this->cache->save($loadItem);
    }

    /**
     * @param array $jsonArray
     * @return void
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws ChatAuthorizationException
     */
    protected function handlePostEmailResponseMessage(array $jsonArray): void
    {
        $userId = $jsonArray['user_id'];
        $channelId = $jsonArray['channel_id'];
        $message = trim($jsonArray['message']);
        $attachments = $jsonArray['attachments'];

        // ensure user_id is provided
        if(empty($userId)) {
            $this->logger->error(__FUNCTION__ ." User id not provided " . print_r($jsonArray,true));
            return;
        }

        // ensure user_id exists
        if (empty($user = $this->dataController->getUserById((int)$userId))) {
            $this->logger->error(__FUNCTION__ ." Unknown user " . print_r($jsonArray,true));
            return;
        }

        // ensure channel_id is provided
        if(empty($channelId)) {
            $this->logger->error(__FUNCTION__ ." Channel id not provided " . print_r($jsonArray,true));
            return;
        }

        // ensure message is not empty
        if(empty($message)) {
            $this->logger->error(__FUNCTION__ ." Message is empty " . print_r($jsonArray,true));
            return;
        }

        // post the message to the channel
        $this->chatOps->postMessage($user, $channelId, $message, $attachments, null, 'email');
    }

    /**
     * @param array $jsonArray
     */
    protected function handleRemoveInvalidFcmDeviceId(array $jsonArray): void
    {
        $deviceId = $jsonArray['device_id'];

        // ensure device_id is provided
        if(empty($deviceId)) {
            $this->logger->error(__FUNCTION__ ." Device id not provided " . print_r($jsonArray,true));
            return;
        }

        $this->dataController->disconnectFCMDevice($deviceId);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int $watermark
     * @throws DatabaseException
     */
    private function clearAlertsBelowReadWatermark(int $channelId, int $userId, int $watermark) : void
    {
        $alerts = $this->dataController->findAlertsBelowWatermark($channelId, $userId, $watermark);
        foreach ($alerts as $alert) {
            $alert->setIsRead(true);
            try {
                $this->dataController->upsertAlert($alert);
                $this->notificationFilter->notifyAlert($alert, true);
            } catch (DatabaseException | InvalidArgumentException | Exception $e) {
                $this->logger->error(__FUNCTION__ . " Exception : ". $e->getMessage());
            }
        }
    }

}