<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Log\LoggerInterface;

class ChannelUserDataStore
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
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     * @param ServiceRegistryInterface $config
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger, ServiceRegistryInterface $config)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Query to get add user to a channel
     *
     * @param ChannelUser $channelUser
     * @return bool
     * @throws DatabaseException
     */
    public function addChannelUser(ChannelUser $channelUser): bool
    {
        try {
            $sql = "INSERT INTO channel_users
              SET
                channel_id        = :channel_id,
                user_id           = :user_id,
                user_role         = :user_role,  
                is_favorite       = :is_favorite,
                muted             = false,
                blocked_on        = null,
                email_tag         = :email_tag,
                created_on        = :created_on,
                created_by        = :created_by";
            $count = $this->dbs->insert($sql, $channelUser->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to get update user to a channel
     *
     * @param ChannelUser $channelUser
     * @return bool
     * @throws DatabaseException
     */
    public function updateChannelUser(ChannelUser $channelUser): bool
    {
        try {
            $sql = "UPDATE channel_users
              SET                
                user_role         = :user_role,
                is_favorite       = :is_favorite,  
                muted             = :muted,
                blocked_on        = :blocked_on,
                created_on        = :created_on,
                created_by        = :created_by
               WHERE 
                channel_id        = :channel_id AND
                user_id           = :user_id";
            $keys = [
                'channel_id',
                'user_id',
                'user_role',
                'is_favorite',
                'muted',
                'blocked_on',
                'created_on',
                'created_by',
            ];
            $bindings = array_intersect_key($channelUser->getArray(), array_flip($keys));
            $count = $this->dbs->update($sql, $bindings);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to delete a user in a channel
     *
     * @param int $channel_id
     * @param int $user_id
     * @return int
     * @throws DatabaseException
     */
    public function dropChannelUser(int $channel_id, int $user_id): int
    {
        try {
            $sql = "DELETE FROM channel_users where channel_id = :channel_id AND user_id = :user_id";
            $count = $this->dbs->delete($sql, ['channel_id' => $channel_id, 'user_id' => $user_id]);
            return $count;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to remove all users in a channel
     *
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function dropAllUsersFromChannel(int $channelId): int
    {
        try {
            $sql = "DELETE FROM channel_users where channel_id = :channel_id;";
            $count = $this->dbs->delete($sql, ['channel_id' => $channelId]);
            return $count;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get Users For Channel
     *
     * @param int $channelId
     * @return iterable
     * @throws DatabaseException
     */
    public function getUsersForChannel(int $channelId) : iterable
    {
        try {
            $sql = "select u.*, cu.user_role 'channel_user_role' , cu.is_favorite 'is_favorite'
                from users u 
                  join channel_users cu on u.id = cu.user_id
                where channel_id = :channel_id
                ORDER BY last_active_on DESC;";
            return $this->dbs->cursor($sql, ['channel_id' => $channelId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get Channels for User
     *
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getChannelsForUser(int $userId) : iterable
    {
        try {
            $sql = "SELECT c.*,cu.user_role 'channel_user_role', cu.is_favorite 'is_favorite'
                FROM channels c 
                  JOIN channel_users cu on c.id = cu.channel_id
                WHERE user_id = :user_id
                ORDER BY last_active_on DESC;";
            return $this->dbs->cursor($sql, ['user_id' => $userId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @return ChannelUser[]
     * @throws DatabaseException
     */
    public function getBlockedChannelsForUser(int $userId): array
    {
        try {
            $sql = "SELECT *
                FROM channel_users cu
                WHERE user_id = :userId
                    AND blocked_on IS NOT NULL;";
            $rows = $this->dbs->select($sql, compact('userId'));
            return array_map(function ($row) {
                return ChannelUser::withDBData($row);
            }, $rows);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get the channel user information
     *
     * @param int $channelId
     * @param int $userId
     * @return ChannelUser|null
     * @throws DatabaseException
     */
    public function getUserChannel(int $channelId, int $userId) : ?ChannelUser
    {
        try {
            $sql = "select * from channel_users where user_id = :user_id and channel_id = :channel_id;";
            $record = $this->dbs->selectOne($sql, ['user_id' => $userId, 'channel_id' => $channelId]);
            return (empty($record) ? null : ChannelUser::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get user channels by user Id
     *
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getUserChannelsByUserId(int $userId) : iterable
    {
        try {
            $sql = "select * from channel_users where user_id = :user_id;";
            return $this->dbs->cursor($sql, ['user_id' => $userId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @return ChannelUser[]
     * @throws DatabaseException
     */
    public function getChannelUsersByChannelId(int $channelId): array
    {
        try {
            $sql = "SELECT * FROM channel_users WHERE channel_id = :channelId;";
            $rows = $this->dbs->select($sql, compact('channelId'));
            return array_map(function ($row) {
                return ChannelUser::withDBData($row);
            }, $rows);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Set Favorite Channel
     *
     * @param int $userId
     * @param int $channelId
     * @param bool $isFavorite
     * @return bool
     * @throws DatabaseException
     */
    public function setFavoriteChannel(int $userId, int $channelId, bool $isFavorite)
    {
        try {
            $sql = "UPDATE channel_users
              SET              
                is_favorite       = :is_favorite              
               WHERE 
                channel_id        = :channel_id AND
                user_id           = :user_id";
            $count = $this->dbs->update($sql, [
                'channel_id' => $channelId,
                'user_id' => $userId,
                'is_favorite' => ($isFavorite ? 1 : 0)
            ]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function setNotificationsConfig(int $channelId, int $userId, int $configValue)
    {
        $sql = "UPDATE channel_users SET notifications_config = :config WHERE user_id = :user_id AND channel_id = :channel_id";
        $count = $this->dbs->update($sql, [
            ':config' => $configValue,
            ':user_id' => $userId,
            ':channel_id' => $channelId,
        ]);
        return $count !== -1;
    }

    /**
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function getChannelUserCount(int $channelId) : int
    {
        try {
            $sql = "SELECT count(1) as 'total' 
                FROM channel_users
                where channel_id = :channel_id;";
            $criteria = ['channel_id' => $channelId];
            $record = $this->dbs->selectOne($sql, $criteria);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return string|null
     * @throws DatabaseException
     */
    public function getMemberEmail(int $channelId, int $userId): ?string
    {
        try {
            $sql = <<<sql
                SELECT c.channel_email as email, cu.email_tag as tag
                FROM channel_users cu
                INNER JOIN channels c ON cu.channel_id = c.id
                WHERE channel_id = :channel_id AND user_id = :user_id;
sql;
            $bindings = ['channel_id' => $channelId, 'user_id' => $userId];
            $record = $this->dbs->selectOne($sql, $bindings);
            $emailDomain = $this->config->get('/mailer/response_domain');
            return empty($record) ? null : "{$record['email']}+usr-{$record['tag']}@{$emailDomain}";
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return bool
     * @throws DatabaseException
     */
    public function isChannelMember(int $channelId, int $userId): bool
    {
        try {
            $sql = <<<sql
                SELECT count(*) AS count
                FROM channel_users 
                WHERE channel_id = :channel_id
                AND user_id = :user_id
sql;
            $bindings = ['channel_id' => $channelId, 'user_id' => $userId];
            $record = $this->dbs->selectOne($sql, $bindings);
            return $record['count'] > 0;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function setMuted(int $userId, int $channelId, bool $muted): void
    {
        try {
            $sql = <<<sql
                UPDATE channel_users
                SET muted = :muted 
                WHERE channel_id = :channel_id
                AND user_id = :user_id
sql;
            $bindings = ['muted' => $muted, 'channel_id' => $channelId, 'user_id' => $userId];
            $this->dbs->executeStatement($sql, $bindings);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int|null $messageId - When null, set to the last message posted on the channel
     * @return int
     * @throws DatabaseException
     */
    public function updateReadWatermark(int $channelId, int $userId, ?int $messageId = null): int
    {

        if ($messageId !== null) {

            // check if the message exists
            $sql = <<<sql
                SELECT 1
                FROM messages
                WHERE id = :message_id
sql;
            if ($this->dbs->selectOne($sql, ['message_id' => $messageId]) === null) {
                throw new DatabaseException('The message_id is not valid');
            }
        } else {

            // get the last posted message on the channel
            $sql = <<<sql
                SELECT id
                FROM messages
                WHERE channel_id = :channel_id
                ORDER BY id desc
                LIMIT 1
sql;
            $row = $this->dbs->selectOne($sql, ['channel_id' => $channelId]);
            $messageId = (int) ($row['id'] ?? 0);
        }

        try {

            $sql = <<<sql
                UPDATE channel_users
                SET read_watermark_id = :read_watermark 
                WHERE channel_id = :channel_id
                AND user_id = :user_id
                AND (read_watermark_id IS NULL OR read_watermark_id < :read_watermark) -- never set the read watermark to a value smaller than the current 
sql;
            $bindings = ['read_watermark' => $messageId, 'channel_id' => $channelId, 'user_id' => $userId];
            $this->dbs->executeStatement($sql, $bindings);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
        return $messageId;
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int|null $readWatermark
     * @return int
     * @throws DatabaseException
     */
    public function getUnReadMessageCount(int $channelId, int $userId, ?int $readWatermark = null): int
    {
        try {

            if ($readWatermark === null) {
                $sql = <<<sql
                    SELECT read_watermark_id
                    FROM channel_users cu 
                    WHERE cu.channel_id = :channel_id
                    AND cu.user_id = :user_id
sql;
                $result = $this->dbs->selectOne($sql, ['user_id' => $userId, 'channel_id', $channelId]);
                $readWatermark = $result['read_watermark_id'] ?? 0;
            }

            $sql = <<<sql
                SELECT count(*) as count
                FROM messages m 
                WHERE m.channel_id = :channel_id
                AND m.id > :read_watermark
sql;
            $result = $this->dbs->selectOne($sql, ['channel_id' => $channelId, 'read_watermark' => $readWatermark]);

            if ($result === null) {
                throw new DatabaseException("Failed to get the unread count for channel: $channelId and user:$userId");
            }

            return (int) $result['count'];
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $minRoleLevel
     * @return \Generator|User[]
     * @throws DatabaseException
     */
    public function getChannelMembersByRoleLevel(int $channelId, int $minRoleLevel): \Generator
    {
        try {

            $sql = <<<sql
                SELECT DISTINCT u.*
                FROM channel_users cu
                INNER JOIN users u ON cu.user_id = u.id 
                WHERE cu.channel_id = :channel_id
                AND cu.user_role >= :min_role_level
sql;
            $result = $this->dbs->cursor($sql, ['channel_id' => $channelId, 'min_role_level' => $minRoleLevel]);

            foreach ($result as $row) {
                yield User::withDBData($row);
            }

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getChannelsCountForUser(int $userId): int
    {
        try {
            $sql = <<<sql
                SELECT count(DISTINCT channel_id) as count
                FROM channel_users cu
                WHERE cu.user_id = :user_id
sql;
            $result = $this->dbs->selectOne($sql, ['user_id' => $userId]);
            return (int) $result['count'] ?? 0;

        } catch (\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo, true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getChannelsOwnedCountForUser(int $userId): int
    {
        try {
            $sql = <<<sql
                SELECT count(DISTINCT id) as count
                FROM channels c
                WHERE c.owned_by = :user_id
sql;
            $result = $this->dbs->selectOne($sql, ['user_id' => $userId]);
            return (int) $result['count'] ?? 0;

        } catch (\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo, true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @return ChannelUser[]
     * @throws DatabaseException
     */
    public function getBlockerUsersForChannel(int $channelId): array
    {
        try {
            $sql = "SELECT *
                FROM channel_users cu
                WHERE channel_id = :channelId
                    AND blocked_on IS NOT NULL;";
            $rows = $this->dbs->select($sql, compact('channelId'));
            return array_map(function ($row) {
                return ChannelUser::withDBData($row);
            }, $rows);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}