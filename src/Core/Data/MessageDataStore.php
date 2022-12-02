<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\Timeline;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Database\DatabaseService;
use Generator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class MessageDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    protected $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * UserDataStore constructor.
     *
     * @param ContainerInterface $container
     * @param ConfigRegistry $config
     */
    public function __construct(ContainerInterface $container, ConfigRegistry $config)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->config = $config;
    }

    /**
     * create message
     *
     * @param Message $message
     * @return bool
     * @throws DatabaseException
     */
    public function createMessage(Message $message) : bool
    {
        try {
            $sql = "INSERT INTO messages
              SET                
                user_id             = :user_id,
                channel_id          = :channel_id,                
                display_name        = :display_name,
                content_text        = :content_text,
                message_type        = :message_type,
                attachments         = :attachments,
                is_edited           = :is_edited,
                is_deleted           = :is_deleted,
                parent_message      = :parent_message,                                
                emoticons           = :emoticons,
                source              = :source,
                created_on          = :created_on,
                send_email          = :send_email";
            $count = $this->dbs->insert($sql, $message->getArrayWithJsonString());
            $message->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Update message
     *
     * @param Message $message
     * @return bool
     * @throws DatabaseException
     */
    public function updateMessage(Message $message) : bool
    {
        try {
            $sql = "UPDATE messages
              SET                    
                user_id             = :user_id,
                channel_id          = :channel_id,                
                display_name        = :display_name,
                content_text        = :content_text,
                message_type        = :message_type,
                attachments         = :attachments,
                is_edited           = :is_edited,
                is_deleted          = :is_deleted,
                parent_message      = :parent_message,                                
                emoticons           = :emoticons,
                source              = :source,
                created_on          = :created_on,
                send_email          = :send_email
               WHERE
                id                  = :id";
            $count = $this->dbs->update($sql, $message->getArrayForUpdate());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Delete message
     *
     * @param $messageId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteMessage(int $messageId) : bool
    {
        try {
            $sql = "DELETE FROM messages where id = :id";
            $count = $this->dbs->delete($sql, ['id' => $messageId]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get messages by channel Id
     *
     * @param int $channelId
     * @param int|null $limit
     * @param int|null $offsetMessageId
     * @param bool $asc
     * @param string|null $before
     * @return iterable
     * @throws DatabaseException
     */
    public function getMessagesByChannelId(int $channelId,
                                           ?int $limit,
                                           ?int $offsetMessageId,
                                           ?string $before = null,
                                           bool $asc = false) : iterable
    {
        $dir = "DESC";
        $comp = "<";
        if ($asc) {
            $dir = "ASC";
            $comp = ">=";
        }
        try {
            $sql = "SELECT * FROM messages WHERE channel_id = :channel_id ";
            if ($before !== null) {
                $sql .= " AND created_on < :before ";
            }
            $criteria = [];
            if (!empty($limit) && !empty($offsetMessageId)) {
                $sql .= " AND id $comp :message_id ORDER BY id $dir LIMIT $limit;";
                $criteria = ['channel_id' => $channelId, 'message_id' => $offsetMessageId];
            } else {
                if (!empty($limit) && empty($offsetMessageId)) {
                    $sql .= " ORDER BY id $dir LIMIT $limit;";
                    $criteria = ['channel_id' => $channelId];
                } else {
                    if (empty($limit) && !empty($offsetMessageId)) {
                        $sql .= " AND id $comp :message_id ORDER BY id $dir;";
                        $criteria = ['channel_id' => $channelId, 'message_id' => $offsetMessageId];
                    } else {
                        $sql .= " ORDER BY id $dir;";
                        $criteria = ['channel_id' => $channelId];
                    }
                }
            }

            if ($before !== null) {
                $criteria['before'] = $before;
            }

            //ContainerFacade::get(LoggerInterface::class)->info($sql ." . " . print_r($criteria,true));
            return $this->dbs->cursor($sql, $criteria);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }



    /**
     * @param int $channelId
     * @param string $search
     * @return iterable
     * @throws DatabaseException
     */
    public function searchMessages(string $search): iterable
    {
        try {
            $sql = "SELECT * FROM messages WHERE MATCH(content_text) AGAINST(:search IN NATURAL LANGUAGE MODE)";
            return $this->dbs->cursor($sql, ['search' => $search]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get message by message id
     *
     * @param $messageId
     * @return Message|null
     * @throws DatabaseException
     */
    public function getMessageById(int $messageId) : ?Message
    {
        try {
            $sql = "SELECT * FROM messages where id = :message_id;";
            $record = $this->dbs->selectOne($sql, ['message_id' => $messageId]);
            return (empty($record) ? null : Message::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $messageId
     * @return int
     * @throws DatabaseException
     */
    public function getNextMessageId(int $channelId, int $messageId) : int
    {
        try {
            $sql = "SELECT MIN(id) 
                FROM messages 
                where channel_id = :channel_id AND $messageId > :message_id 
                ORDERY BY id ASC;";
            $record = $this->dbs->selectOne($sql, ['channel_id' => $channelId, 'message_id' => $messageId]);
            return (empty($record) ? 0 : (int)$record[0]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function getFirstChannelMessageId(int $channelId) : int
    {
        try {
            $sql = "SELECT MIN(id) 
                FROM messages
                WHERE channel_id = :channel_id;";
            $record = $this->dbs->selectOne($sql, ['channel_id' => $channelId]);
            return (empty($record) ? 0 : (int)$record['MIN(id)']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return int
     * @throws DatabaseException
     */
    public function getAllMessageCount() : int
    {
        try {
            $sql = "SELECT COUNT(1) AS total FROM messages;";
            $record = $this->dbs->selectOne($sql, []);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $seconds Number of seconds-back to consider a message new
     * @return int
     * @throws DatabaseException
     */
    public function getNewMessageCount(int $seconds) : int
    {
        try {
            $sql = "SELECT COUNT(1) AS total FROM messages WHERE created_on > DATE_SUB(NOW(), INTERVAL :seconds SECOND);";
            $record = $this->dbs->selectOne($sql, ['seconds' => $seconds]);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int[] $messageIds
     * @return iterable
     * @throws DatabaseException
     */
    public function getMessagesByIds(array $messageIds): iterable
    {
        try {
            $ids = implode(',', $messageIds);
            $sql = "SELECT * FROM messages WHERE id in ({$ids})";
            return $this->dbs->cursor($sql);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getMessagesWithUserEmotions(int $userId) : iterable
    {
        try {
            $sql = "SELECT * FROM messages 
                    WHERE JSON_CONTAINS(emoticons, JSON_OBJECT('uid', :user_id));";
            return $this->dbs->cursor($sql,['user_id' => $userId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param $userId
     * @param $displayName
     * @return bool
     * @throws DatabaseException
     */
    public function updateUserDisplayNameInMessages($userId, $displayName) : bool
    {
        try {
            $sql = "UPDATE messages
                    SET display_name  = :display_name
                    WHERE user_id     = :user_id;";
            $count = $this->dbs->update($sql, ['user_id' => $userId, 'display_name' => $displayName]);
            return ($count >= 0);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }




    /**
     * This method holds a complex query that finds all messages that should be included on a digest for users that
     * never logged to the system (unregistered). There rules are:
     *  * All messages in channels that the user is invited to, are send on digests
     *  * First message on the day should go out within a few minutes (always included)
     *  * Subsequent messages should accumulate and wait for more and more time. Say:
     *    * Email 2 - After 10 minutes
     *    * Email 3 - After 30 minutes
     *    * Email 4 - After 3 hours
     *    * Email 5 - After 6 hours
     *    * Email 6 - After 12 hours
     *  * This frequency should be reset after 24 hours. So the next day the first message goes out immediately and it starts fresh.
     *
     * IMPORTANT: This method was build considering that it would be run using a cron job, with a high frequency
     * (not less than 10 minutes).
     *
     * @return Generator
     * @throws DatabaseException
     */
    public function getMessageDigestDataForPendingSignUpUsers(): Generator
    {
        try {
            $pendingSignUpStatus = User::ACCOUNT_STATUS_PENDING_FINALIZE;
            $botMessageType = Message::MESSAGE_TYPE_BOT;
            $mask = User::NOTIFICATIONS_CONFIG_ALL_UNREAD_MESSAGES_MASK;

            $dailyLimitPerChannel = $this->config->get('/notifications/dailyLimit/perChannel');
            $dailyLimitGlobal = $this->config->get('/notifications/dailyLimit/global');

            // run the query to get the messages that should be on digests now
            $sql = <<<SQL
            SELECT -- message digest for unregistered users
                   c.id AS channel_id, -- we need the channel id to generate the channel link
                   c.channel_name AS channel_name, -- we need the channel name for the digest
                   c.channel_email AS channel_email, -- we need the channel email for the email sender
                   u.id AS recipient_id, -- we need the recipient id to save the notification
                   u.email AS recipient_email, -- we need the address to send the notification to
                   m.id AS message_id, -- we need the message id to save it on the notification_timeline table
                   m.user_id AS sender_id, -- we need the sender id for the avatar link
                   m.display_name AS sender_name, -- we need the sender name for each message
                   m.content_text AS text, -- we need to include the messages itself on the digest
                   m.created_on AS created_on -- we need to include the creation date of the message on the digest
            FROM users u -- all users (there are conditions to reduce this scope)
                INNER JOIN channel_users cu ON cu.user_id = u.id -- linking each user to the channels that he's member off
                INNER JOIN channels c ON c.id = cu.channel_id -- including channel info (we need it for the digest)
                INNER JOIN messages m ON m.channel_id = c.id -- getting all messages on each channel (there are conditions to reduce this scope)
                LEFT JOIN notifications n ON n.user_id = u.id AND n.created_on > CAST(CURRENT_DATE AS DATETIME) -- including the notifications sent today
            WHERE u.account_status = {$pendingSignUpStatus} -- only users that never signed in to the system
                AND NOT cu.muted
                AND (u.notifications_config & {$mask}) AND (cu.notifications_config & {$mask}) -- check the user subscribing status
                AND m.message_type <> {$botMessageType} -- remove messages from the bot
                AND m.created_on > cu.created_on -- only messages sent after the user was added to the channel
                AND m.user_id <> u.id -- users cannot be notified about his own messages
                AND m.content_text <> '' -- exclude empty messages
                AND NOT m.send_email -- exclude messages already sent by email 
                AND NOT EXISTS ( -- excluding messages that was already send in a previous digest
                    SELECT 1 
                    FROM notifications_timeline nt 
                        INNER JOIN notifications n ON n.id = nt.notification_id
                    WHERE nt.message_id = m.id AND n.user_id = u.id
                )
                AND ( -- daily limit of messages per channel
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.context_type = 2
                    AND n.context_id = c.id
                    AND n.created_on > CAST(CURRENT_DATE as DATETIME)
                ) < {$dailyLimitPerChannel}
                AND ( -- daily limit of messages global
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.created_on > CAST(CURRENT_DATE as DATETIME)
                ) < {$dailyLimitGlobal}
            GROUP BY c.id, u.id, m.id
            HAVING 
                MAX(n.created_on) OR -- no notifications sent today, so include it
                MAX(n.created_on) < DATE_SUB(current_timestamp, INTERVAL (
                    -- here we find the X value, based on the count of notifications sent to the user today.
                    -- To get a better understanding, replace on your mind the line above, with the line bellow:
                    -- WHERE max(n.created_on) < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL X MINUTE)
                    -- we're replacing the X, with the query bellow:
                    SELECT minutes_to_wait
                    FROM lookup_notification_frequency_map -- this table links the number of notifications already sent to the number of minutes to wait before sending another one
                    WHERE notifications_sent_count = count(n.id)
                ) MINUTE);
SQL;

            return $this->dbs->cursor($sql);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * This method holds a complex query that finds all mentions that should be included on a digest for users that
     * never logged to the system (unregistered). There rules are:
     *  * When an unregistered user is @mentioned, it should be sent out immediately as an email.
     *
     * IMPORTANT: This method was build considering that it would be run using a cron job, with a high frequency
     * (not less than 10 minutes). It means that "immediately" can have some delay depending on the cron frequency.
     *
     * @return Generator
     * @throws DatabaseException
     */
    public function getMentionDigestDataForPendingSignUpUsers(): Generator
    {

        $pendingSignUpStatus = User::ACCOUNT_STATUS_PENDING_FINALIZE;
        $botMessageType = Message::MESSAGE_TYPE_BOT;
        $alertMessageType = Alert::CONTEXT_TYPE_MESSAGE;
        $mask = User::NOTIFICATIONS_CONFIG_MENTIONS_MASK;

        $dailyLimitPerChannel = $this->config->get('/notifications/dailyLimit/perChannel');
        $dailyLimitGlobal = $this->config->get('/notifications/dailyLimit/global');

        try {

            // run the query to get the mentions that should be on digests now
            $sql = <<<SQL
            SELECT -- mention digest for unregistered users
                   c.id AS channel_id, -- we need the channel id to generate the channel link
                   c.channel_name AS channel_name, -- we need the channel name for the digest
                   c.channel_email AS channel_email, -- we need the channel email for the email sender
                   u.id AS recipient_id, -- we need the recipient id to save the notification
                   u.email AS recipient_email, -- we need the address to send the notification to
                   m.id AS message_id, -- we need the message id to save it on the notification_timeline table
                   m.user_id AS sender_id, -- we need the sender id for the avatar link
                   m.display_name AS sender_name, -- we need the sender name for each message
                   m.content_text AS text, -- we need to include the messages itself on the digest
                   m.created_on AS created_on -- we need to include the creation date of the message on the digest
            FROM alerts a -- we use alerts as the base to find mentions (it's filtered later)
                INNER JOIN messages m ON m.id = a.context_id -- link it to the message
                INNER JOIN users u ON a.user_id = u.id -- link it to the mentioned user
                INNER JOIN channels c ON c.id = m.channel_id -- link the message to the channel where it was sent
                INNER JOIN channel_users cu ON (c.id = cu.channel_id AND a.user_id = cu.user_id) -- include the relation between channel and user
            WHERE u.account_status = {$pendingSignUpStatus} -- only users that never signed in to the system
                AND NOT cu.muted
                AND (u.notifications_config & {$mask}) AND (cu.notifications_config & {$mask}) -- the user subscribing status
	            AND m.message_type <> {$botMessageType} -- remove messages from the bot
	            AND a.context_type = {$alertMessageType} -- only include alerts related to messages (mentions)
                AND m.content_text <> '' -- exclude empty messages
                AND NOT m.send_email -- exclude messages already sent by email
                AND NOT EXISTS ( -- excluding messages that was already send in a previous digest
                    SELECT 1 
                    FROM notifications_timeline nt 
                        INNER JOIN notifications n ON n.id = nt.notification_id
                    WHERE nt.message_id = m.id AND n.user_id = u.id
                )
                AND ( -- daily limit of messages per channel
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.context_type = 2
                    AND n.context_id = c.id
                    AND DATE(n.created_on) = CURRENT_DATE
                ) < {$dailyLimitPerChannel}
                AND ( -- daily limit of messages global
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND DATE(n.created_on) = CURRENT_DATE
                ) < {$dailyLimitGlobal};
SQL;
            return $this->dbs->cursor($sql);
        } catch (\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo, true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * This method holds a complex query that finds all MESSAGES that should be included on a digest for users that
     * has finalized an accound and logged in to the system at least one time (registered). There rules are:
     *  * If the message is marked as unread for more than 24 hours, then a daily summary email must be sent via Cron
     *
     * IMPORTANT: This method was build considering that it would be run using a cron job, with a high frequency
     * (not less than 10 minutes).
     *
     * @return Generator
     */
    public function getMessageDigestDataForRegisteredUsers(): Generator
    {

        $limit = $this->config->get('/notifications/registered/messages_ttw'); // how much time the message must be unread before been included on a digest
        $readActivity = Timeline::ACTIVITY_READ;
        $accountStatusActive = User::ACCOUNT_STATUS_ACTIVE;
        $botMessageType = Message::MESSAGE_TYPE_BOT;
        $mask = User::NOTIFICATIONS_CONFIG_ALL_UNREAD_MESSAGES_MASK;

        $dailyLimitPerChannel = $this->config->get('/notifications/dailyLimit/perChannel');
        $dailyLimitGlobal = $this->config->get('/notifications/dailyLimit/global');

        $sql = <<<SQL
            SELECT -- messages digest for registered users
                c.id AS channel_id, -- we need the channel id to generate the channel link
                c.channel_name AS channel_name, -- we need the channel name for the digest
                c.channel_email AS channel_email, -- we need the channel email for the email sender
                u.id AS recipient_id, -- we need the recipient id to save the notification
                u.email AS recipient_email, -- we need the address to send the notification to
                m.id AS message_id, -- we need the message id to save it on the notification_timeline table
                m.user_id AS sender_id, -- we need the sender id for the avatar link
                m.display_name AS sender_name, -- we need the sender name for each message
                m.content_text AS text, -- we need to include the messages itself on the digest
                m.created_on AS created_on -- we need to include the creation date of the message on the digest
            FROM users u -- all users (there are conditions to reduce this scope)
                INNER JOIN channel_users cu ON cu.user_id = u.id -- linking each user to the channels that he's member off
                INNER JOIN channels c ON c.id = cu.channel_id -- including channel info (we need it for the digest)
                INNER JOIN messages m ON m.channel_id = c.id -- getting all messages on each channel (there are conditions to reduce this scope)
				LEFT JOIN notifications n2 ON n2.user_id = u.id AND n2.created_on > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL {$limit} MINUTE) -- linking to the notifications sent to this user on the last $limit minutes
            WHERE u.account_status IN ({$accountStatusActive})
                AND NOT cu.muted
                AND (u.notifications_config & {$mask}) AND (cu.notifications_config & {$mask}) -- the user subscribing status
                AND m.id > cu.read_watermark_id -- excluding messages that was already read
                AND m.created_on > date_sub(CURRENT_TIMESTAMP, INTERVAL 24 HOUR) -- we can ignore messages older than 24 hours (just reducing the scope to ensure performance)
                AND m.message_type <> {$botMessageType} -- remove messages from the bot
                AND m.created_on > cu.created_on -- only messages sent after the user was added to the channel
                AND m.user_id <> u.id -- users cannot be notified about his own messages
                AND u.last_active_on < date_sub(CURRENT_TIMESTAMP, INTERVAL {$limit} MINUTE) -- just include users that was not seen on the system for the last $limit time
                AND m.content_text <> '' -- exclude empty messages
                AND NOT m.send_email -- exclude messages already sent by email
                AND NOT EXISTS ( -- excluding messages that was already send in a previous digest
                    SELECT 1
                    FROM notifications n
                    INNER JOIN notifications_timeline nt ON nt.notification_id = n.id
                    WHERE n.user_id = u.id AND nt.message_id = m.id
                ) 
                AND n2.id IS NULL -- excluding users that had received notifications less than $limit minutes ago...
                AND ( -- daily limit of messages per channel
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.context_type = 2
                    AND n.context_id = c.id
                    AND n.created_on > CAST(CURRENT_DATE AS DATETIME)
                ) < {$dailyLimitPerChannel}
                AND ( -- daily limit of messages global
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.created_on > CAST(CURRENT_DATE AS DATETIME)
                ) < {$dailyLimitGlobal};
SQL;

        return $this->dbs->cursor($sql);

    }

    /**
     * This method holds a complex query that finds all MENTIONS that should be included on a digest for users that
     * has finalized an accound and logged in to the system at least one time (registered). There rules are:
     *  * If the user is @mentioned unread for more than over 10 mins (configurable)
     *
     * IMPORTANT: This method was build considering that it would be run using a cron job, with a high frequency
     * (not less than 10 minutes). It means that it can have some delay on the email delivering (no more than the cron
     * frequency)
     *
     * @return Generator
     * @throws DatabaseException
     */
    public function getMentionDigestDataForRegisteredUsers(): Generator
    {
        $limit = $this->config->get('/notifications/registered/mentions_ttw'); // How much time the mention must be unread before been included on a digest
        $readActivity = Timeline::ACTIVITY_READ;
        $accountStatusPendingVerification = User::ACCOUNT_STATUS_PENDING_VERIFICATION;
        $accountStatusActive = User::ACCOUNT_STATUS_ACTIVE;
        $botMessageType = Message::MESSAGE_TYPE_BOT;
        $alertMessageType = Alert::CONTEXT_TYPE_MESSAGE;
        $alertTypeMention = Alert::ALERT_TYPE_MENTION;
        $mask = User::NOTIFICATIONS_CONFIG_MENTIONS_MASK;

        $dailyLimitPerChannel = $this->config->get('/notifications/dailyLimit/perChannel');
        $dailyLimitGlobal = $this->config->get('/notifications/dailyLimit/global');

        $sql = <<<SQL
            SELECT -- mention digest for registered users
                   c.id AS channel_id, -- we need the channel id to generate the channel link
                   c.channel_name AS channel_name, -- we need the channel name for the digest
                   c.channel_email AS channel_email, -- we need the channel email for the email sender
                   u.id AS recipient_id, -- we need the recipient id to save the notification
                   u.email AS recipient_email, -- we need the address to send the notification to
                   m.id AS message_id, -- we need the message id to save it on the notification_timeline table
                   m.user_id AS sender_id, -- we need the sender id for the avatar link
                   m.display_name AS sender_name, -- we need the sender name for each message
                   m.content_text AS text, -- we need to include the messages itself on the digest
                   m.created_on AS created_on -- we need to include the creation date of the message on the digest
            FROM alerts a -- we use alerts as the base to find mentions (it's filtered later)
                INNER JOIN messages m ON m.id = a.context_id -- link it to the message
                INNER JOIN users u ON a.user_id = u.id -- link it to the mentioned user
                INNER JOIN channels c ON c.id = m.channel_id -- link the message to the channel where it was sent
                INNER JOIN channel_users cu ON (c.id = cu.channel_id AND a.user_id = cu.user_id) -- include the relation between channel and user
            WHERE u.account_status IN ({$accountStatusPendingVerification},{$accountStatusActive}) -- only pending verification and active users
                AND NOT cu.muted
                AND a.created_on > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR) -- only include alerts generated on the last 24 hours / reduce scope
                AND (u.notifications_config & {$mask}) AND (cu.notifications_config & {$mask}) -- the user subscribing status
                AND m.message_type <> {$botMessageType} -- remove messages from the bot
                AND a.alert_type = {$alertTypeMention} -- including only alerts for mentions
                AND a.context_type = {$alertMessageType} -- only include alerts related to messages
                AND u.last_active_on < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL {$limit} MINUTE) -- just include users that was not seen on the system for the last $limit time
                AND m.content_text <> '' -- exclude empty messages
                AND NOT m.send_email -- exclude messages already sent by email
                AND NOT EXISTS ( -- excluding messages that was already send in a previous digest
                    SELECT 1 
                    FROM notifications_timeline nt 
                        INNER JOIN notifications n ON n.id = nt.notification_id
                    WHERE nt.message_id = m.id AND n.user_id = u.id
                )
                AND m.id > cu.read_watermark_id -- excluding messages that was already read
                AND ( -- daily limit of messages per channel
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.context_type = 2
                    AND n.context_id = c.id
                    AND n.created_on > CAST(CURRENT_DATE AS DATETIME)
                ) < {$dailyLimitPerChannel}
                AND ( -- daily limit of messages global
                    SELECT count(*)
                    FROM notifications n
                    WHERE n.user_id = u.id
                    AND n.created_on > CAST(CURRENT_DATE AS DATETIME)
                ) < {$dailyLimitGlobal};
SQL;

        try {
            return $this->dbs->cursor($sql);
        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }

    }

    public function getUnreadMessagesForUser(int $userId): int
    {
        try {
            $sql = <<<sql
                SELECT (count(m.id)) as unread
                FROM messages m
                INNER JOIN channels c ON m.channel_id = c.id
                INNER JOIN channel_users cu ON cu.channel_id = c.id AND cu.user_id = :user_id -- only include channels that the user is member of
                WHERE m.message_type in (1,2,3,4) -- all regular messages
                AND m.user_id <> :user_id -- remove messages written by the user
                AND (cu.read_watermark_id IS NULL OR m.id > cu.read_watermark_id);
sql;

            $record = $this->dbs->selectOne($sql, ['user_id' => $userId]);
            return empty($record) ? 0 : (int)$record['unread'];
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getMessageSentCountForUser(int $userId): int
    {
        try {
            $sql = <<<sql
                SELECT COUNT(id) as count
                FROM messages m
                WHERE m.user_id = :user_id
sql;

            $record = $this->dbs->selectOne($sql, ['user_id' => $userId]);
            return (int) $record['count'] ?? 0;
        } catch (\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo, true));
            throw new DatabaseException($e->getMessage());
        }
    }
}
