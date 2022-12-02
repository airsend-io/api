<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Chat;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChatAuthorizationException;
use CodeLathe\Core\Exception\ChatInvalidAttachmentException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UnknownUserException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChatUpdatedEvent;
use CodeLathe\Core\Messaging\Events\ChatPostedEvent;
use CodeLathe\Core\Messaging\Events\NotifyAlert;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\AlertIssuer;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Objects\MessageEmoticon;
use CodeLathe\Core\Objects\MessageParent;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Unicode;
use CodeLathe\Core\Utility\Utility;
use Psr\Log\LoggerInterface;

class ChatOperations
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
     * @var ConfigRegistry
     */
    protected $config;


    /**
     * ChannelManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param ConfigRegistry $config
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                FileOperations $fOps,
                                EventManager $eventManager,
                                ConfigRegistry $config
                                )
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->config = $config;
    }

    /**
     * @param int $channelId
     * @param string $text
     * @param string $oauthClientId
     * @throws ChatOpException
     * @throws ChatAuthorizationException|DatabaseException
     */
    public function postBotMessage(int $channelId, string $text, string $oauthClientId)
    {
        // check permissions
        if (!$this->dataController->isOauthClientActivatedOnChannel($channelId, $oauthClientId)) {
            throw new ChatAuthorizationException("Client $oauthClientId does not have write access to this channel ");
        }

        $botEvent = MessageBot::create(2, $text);
        $this->raiseBotNotification($botEvent, $channelId);

    }


    /**
     * Get default team associated with this user
     *
     * @param int $userId
     * @return Team|null
     * @throws DatabaseException
     */
    private function   getDefaultTeamId(int $userId) : ?Team
    {
        $teams = array();
        foreach($this->dataController->getDefaultTeamForUser($userId) as $teamRec) {
            $teams[] = Team::withDBData($teamRec);
        }

        if (count($teams) > 0) {
            return $teams[0];
        }

        return NULL;
    }

    /**
     *
     * Check if user is allowed to do chat operation
     *
     * @param User $user
     * @param int $channelId
     * @return bool
     */
    private function isChatOpsAllowed(User $user, int $channelId): bool
    {
        if ($user->getUserRole() >= User::USER_ROLE_SUB_ADMIN) {
            return true;
        }

        // If this user is not a part of this user
        if (empty($channelUser = $this->dataController->getUserChannel($channelId, $user->getId()))) { return false; }

        // If the user role is viewer, then we cant allow any chat ops
        if (ChannelUser::CHANNEL_USER_ROLE_VIEWER == $channelUser->getUserRole()) { return false; }

        return true;
    }

    /**
     *
     * Add a message to a channel
     *
     * @param User $user is the user adding this
     * @param int $channelId Id of the channel to add
     * @param string $text
     * @param string|null $fileAttachments
     * @param int|null $quoteMessageId
     * @param string|null $source
     * @param bool $sendEmail
     * @return int The message id
     * @throws ChatAuthorizationException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function postMessage(User $user,
                                int $channelId,
                                string $text,
                                ?string $fileAttachments = '',
                                ?int $quoteMessageId = 0,
                                ?string $source = 'chat',
                                bool $sendEmail = false): int
    {

        // TODO - replace with the new authorization system
        if (!$this->isChatOpsAllowed($user, $channelId)) {
            throw new ChatAuthorizationException("User " . $user->getEmail() . " does not have write access to this channel ");
        }

        // sanitize the message (remove html tags)
        $text = htmlentities($text);

        $channelUser = $this->dataController->getUserChannel($channelId, $user->getId());
        if ($channelUser instanceof ChannelUser && $channelUser->getBlockedOn() !== null) {
            throw new ChatAuthorizationException("Cannot post to blocked channel.");
        }

        $messageType = Message::MESSAGE_TYPE_NEW;

        $message = Message::create($user->getId(), (int)$channelId, $user->getDisplayName(), $text, $messageType, $source);
        if ($quoteMessageId != 0) {
            $parentMessage = $this->dataController->getMessageById((int)$quoteMessageId);
            if (!empty($parentMessage)) {

                if ($parentMessage->getChannelId() !== $channelId) {
                    throw new ChatAuthorizationException(I18n::get('messages.chat_cant_reply_another_channel'));
                }

                $messageText = "";
                if (!empty($parentMessage->getText())) {
                    $messageText = $parentMessage->getText();
                }

                $message->setMessageType(Message::MESSAGE_TYPE_QUOTE);
                $messageParent = MessageParent::create((int)$parentMessage->getId(),
                    $parentMessage->getUserId(), $messageText, $parentMessage->getCreatedOn());
                $message->addParentMessage($messageParent);
            }
        }

        $messageAttachments = [];
        if (!empty($fileAttachments)) {
            try {
                // This must be an array of JSON objects
                $fileAttachments = json_decode($fileAttachments, true);
                if(!is_array($fileAttachments)) {
                    $this->logger->error(__FUNCTION__ . " Invalid JSON attachments");
                    throw new ChatOpException ("Invalid JSON attachments");
                }
                // Create an attachment object for each of the JSON entries
                foreach ($fileAttachments as $fileAttachment) {

                    $attachment = ['path' => $fileAttachment];
                    $fObj = $this->fOps->info($fileAttachment, $user);
                    if (empty($fObj)) {
                        throw new ChatInvalidAttachmentException("Attachment not found: " . $fileAttachment );
                    }
                    $attachment['file'] = $fObj->getName();
                    $attachment['size'] = $fObj->getSize();
                    $messageAttachment = MessageAttachment::create($attachment, MessageAttachment::ATTACHMENT_TYPE_FILE, $attachment['path']);

                    // save it to the message attachments array, to be saved to DB later
                    $messageAttachments[] = $messageAttachment;

                    // TODO - The attachment field on the message object/table is deprecated, and must be removed ASAP
                    // it was replaced with the message_attachment object/table
                    $message->addAttachment($messageAttachment);
                }
            }
            catch (\Exception $ex) {
                $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
                throw new ChatOpException ("Invalid attachments");
            }
        }
        $message->setSendEmail($sendEmail);

        try {
//            $this->dataController->beginTransaction();
            $this->dataController->createMessage($message);
            foreach ($messageAttachments as $messageAttachment) {
                $messageAttachment->setMessageId($message->getId());
                $this->dataController->createMessageAttachment($messageAttachment);
            }
//            $this->dataController->commit();
        }
        catch(\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error updating message");
        }

        // when a user posts a message to a channel, set's it's read watermark to this message
        $this->dataController->channelUserUpdateReadWatermark($channelId, $user->getId(), $message->getId());

        // Raise event
        $event = new ChatPostedEvent($message);
        $this->eventManager->publishEvent($event);

        return $message->getId();
    }

    /**
     * @param array $params
     * @return array
     */
    public function transformChatText(array $params): array
    {
        if (!empty($params['text'])) {
            $text = html_entity_decode($params['text']);
            $text = preg_replace('/\xc2\xa0/', ' ', $text);
            // Ignore emoji replacement for markdowns
            if (!StringUtility::startsWith($text, '```')) {
                $text = StringUtility::replaceWithEmojis($text);
            }


            $params['text'] = $text;
        }

        return $params;

    }

    /**
     * Edit a message. Only owner can edit a message
     *
     * @param User $user
     * @param int $messageId
     * @param string $text
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function updateMessageText(User $user, int $messageId, string $text): void
    {
        // Strip out trash

        // sanitize the message (remove html tags)
        $text = htmlentities($text);

        $message = $this->dataController->getMessageById($messageId);
        if (empty($message)) {
            throw new ChatOpException("Message $messageId does not exist");
        }
        if ($message->getUserId() != $user->getId()) {
            $this->logger->error("User " . $user->getEmail() . "(" . $user->getId() . ") is not allowed to edit " . $message->getId());
            throw new ChatOpException ("User is not allowed to edit this message");
        }

        $message->setIsEdited(true);
        $message->setText($text);

        /** Save it to DB */
        try {
            $this->dataController->updateMessage($message);
        }
        catch(\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error updating message");
        }

        // Raise event
        $event = new ChatUpdatedEvent($message);
        $this->eventManager->publishEvent($event);
    }

    /**
     * Edit a message. Only owner can edit a message
     *
     * @param User $user
     * @param int $messageId
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function deleteMessage(User $user, int $messageId): void
    {
        $message = $this->dataController->getMessageById($messageId);
        if (empty($message)) {
            throw new ChatOpException("Message $messageId does not exist");
        }
        if ($message->getUserId() != $user->getId()) {
            $this->logger->error("User " . $user->getEmail() . "(" . $user->getId() . ") is not allowed to edit " . $message->getId());
            throw new ChatOpException ("User is not allowed to delete this message");
        }

        /** Save it to DB */
        try {
            $this->deleteAttachments($user, $message);
            $message->removeAllParentMessages();
            $message->setText("This message is deleted");
            $message->setIsDeleted(true);
            $this->dataController->updateMessage($message);
        }
        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error deleting message");
        }

        // Raise event
        $event = new ChatUpdatedEvent($message);
        $this->eventManager->publishEvent($event);
    }

    /**
     *
     * Delete any associated file
     *
     * @param User $user
     * @param Message $message
     * @throws UnknownResourceException
     * @throws UnknownUserException
     */
    private function deleteAttachments(User $user, Message $message): void
    {
        foreach ($this->dataController->findAttachmentsForMessage($message->getId()) as $attachment) {
            if (MessageAttachment::ATTACHMENT_TYPE_FILE == $attachment->getContentType()) {

                // Delete attachments from filesystem
                $jsonObj = $attachment->getContent();

                $this->logger->info("Deleting " . $jsonObj['path']);
                $this->fOps->delete( $jsonObj['path'], $user);

            }
        }

        // clear the attachments
        $this->dataController->removeAttachmentFromMessage($message->getId());
    }
    /**
     * This is an internal ONLY method to send a bot notification to all users of a channel.
     *
     * @param MessageBot $botMsg
     * @param int $channelId
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function raiseBotNotification(MessageBot $botMsg, int $channelId): void
    {

        // Create message with Bot id:
        $message = Message::create($this->config->get('/app/airsend_bot_id'),
            (int)$channelId, 'AirBot', json_encode($botMsg->jsonSerialize()) , Message::MESSAGE_TYPE_BOT);
        try {
            $this->dataController->createMessage($message);
        }
        catch(\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error updating message");
        }

        // Raise event
        $event = new ChatPostedEvent($message);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param int $userId
     * @throws ChatOpException
     */
    public function deleteEmoticonByUserId(int $userId)
    {
        try {
            foreach ($this->dataController->getMessagesWithUserEmotions($userId) as $record) {
                $message = Message::withDBData($record);
                $emoticons = [];
                foreach ($message->getEmoticons() as $emoticon) {
                    if ($emoticon->getUserId() == $userId) {
                        $emoticons[] = $emoticon;
                    }
                }
                foreach ($emoticons as $emoticon) {
                    $message->deleteEmoticon($emoticon);
                }
                $this->dataController->updateMessage($message);
            }
        }
        catch(ASException $ex){
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error deleting emoticons for user");
        }
    }

    /**
     * @param int $userId
     * @param string $newDisplayName
     * @throws ChatOpException
     */
    public function updateEmoticonDisplayName(int $userId, string $newDisplayName)
    {
        try {
            foreach ($this->dataController->getMessagesWithUserEmotions($userId) as $record) {
                $message = Message::withDBData($record);
                $emoticons = [];
                foreach ($message->getEmoticons() as $emoticon) {
                    if ($emoticon->getUserId() == $userId) {
                        $emoticons[] = $emoticon;
                    }
                }
                foreach ($emoticons as $emoticon) {
                    $message->deleteEmoticon($emoticon);
                    $newEmoticon = MessageEmoticon::create($userId, $newDisplayName, $emoticon->getEmojiValue());
                    $message->addEmoticon($newEmoticon);
                }

                $this->dataController->updateMessage($message);
            }
        }
        catch(ASException $ex){
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error deleting emoticons for user");
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return int The message id set
     * @throws DatabaseException
     */
    public function setReadWatermarkToLastMessage(int $channelId, int $userId): int
    {

        // Update the watermark for this channel/user
        return $this->dataController->channelUserUpdateReadWatermark($channelId, $userId);

    }

    /**
     * @param User $user
     * @param int $messageId
     * @param string $emojiValue
     * @param bool $remove
     * @throws DatabaseException
     * @throws ChatAuthorizationException
     * @throws ChatOpException
     */
    public function handleEmoticon(User $user, int $messageId, string $emojiValue, bool $remove=false) : void
    {

        $supportedReactions = ['👍', '😀', '❤️','❤', '🤣', '😲', '😭', '🤔', '🙏',];

        if (!Unicode::isEmoji($emojiValue, $supportedReactions)) {
            throw new ChatOpException('Invalid emoji.');
        }

        $message = $this->dataController->getMessageById($messageId);
        if (empty($message)) {
            // Unknown message id.
            $this->logger->error(__FUNCTION__ . " $messageId is invalid. ignoring");
        }

        $channelId = $message->getChannelId();
        if (!$this->isChatOpsAllowed($user, $channelId)) {
            throw new ChatAuthorizationException("User " . $user->getEmail() . " does not have write access to this channel ");
        }

        $emoticon = MessageEmoticon::create((int)$user->getId(),
                                            $user->getDisplayName(), $emojiValue);

        if ($remove) {
            $message->deleteEmoticon($emoticon);
        }
        else {
            $message->addEmoticon($emoticon);
            if ((int)$message->getUserId() !== (int)$user->getId()) {
                // Create an alert
                $this->generateAlert($user, $message);
            }
        }

        try {
            $this->dataController->updateMessage($message);
        }
        catch(\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error updating message");
        }
        catch(\Exception $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new ChatOpException ("Error updating message");
        }

        // Message update notification
        $event = new ChatUpdatedEvent($message, false);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param User $user
     * @param Message $message
     * @throws DatabaseException
     */
    private function generateAlert(User $user, Message $message) : void
    {
        $channel = $this->dataController->getChannelById($message->getChannelId());

        $msgText = " reacted to your message ";
        if (!empty($message->getText())) {
            $msg = $message->getText();
            $msg = preg_replace('/\[(.*?)\]\((.*?)\)/', '${1}', $msg);

            if (strlen($msg) > 60 ) {
                $msg = substr($msg, 0, 60) . '...';
            }
            $msgText = " reacted to your message: ".$msg;
        }

        $alertText = "[" . $user->getDisplayName() ."](user://" . $user->getId() .") $msgText ".
                     " - in [" . $channel->getName() . "](channel://" . $channel->getId() . ")";

        $alert = Alert::create((int)$message->getUserId(), (int)$message->getId(), Alert::CONTEXT_TYPE_MESSAGE,
                               $alertText, AlertIssuer::create((int)$user->getId(), $user->getDisplayName()),Alert::ALERT_TYPE_REACTION);
        $this->dataController->upsertAlert($alert);

        $event = new NotifyAlert($alert);
        $this->eventManager->publishEvent($event);

    }
}
