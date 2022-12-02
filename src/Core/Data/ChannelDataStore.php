<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class ChannelDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var mixed|LoggerInterface
     */
    private $logger;

    /**
     * UserDataStore constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Query Statement to create channel
     *
     * @param Channel $channel
     * @return bool
     * @throws DatabaseException
     */
    public function createChannel(Channel $channel): bool
    {
        $channel->setUpdatedOn(date('Y-m-d H:i:s'));
        try {
            $sql = "INSERT INTO channels
              SET                
                team_id             = :team_id,
                channel_name        = :channel_name,
                channel_email       = :channel_email,    
                blurb               = :blurb,
                locale              = :locale,
                default_joiner_role = :default_joiner_role,
                default_invitee_role = :default_invitee_role,
                channel_status      = :channel_status,
                is_auto_closed      = :is_auto_closed,                
                close_after_days    = :close_after_days,                                
                last_active_on      = :last_active_on, 
                has_logo            = :has_logo,
                has_background      = :has_background, 
                require_join_approval = :require_join_approval,
                allow_external_read = :allow_external_read,
                open_team_join      = :open_team_join,
                contact_form_id     = :contact_form_id,
                contact_form_filler_id     = :contact_form_filler_id,
                one_one             = :one_one,
                one_one_approved    = :one_one_approved,
                created_on          = :created_on,
                owned_by          = :owned_by,
                updated_on          = :updated_on,
                updated_by          = :updated_by";
            $count = $this->dbs->insert($sql, $channel->getArray());
            $channel->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to update channel
     *
     * @param Channel $channel
     * @return bool
     * @throws DatabaseException
     */
    public function updateChannel(Channel $channel): bool
    {
        $channel->setUpdatedOn(date('Y-m-d H:i:s'));
        try {
            $sql = "UPDATE channels
              SET
                id                 = :id,
                team_id             = :team_id,
                channel_name        = :channel_name,
                channel_email       = :channel_email,  
                blurb               = :blurb,
                locale              = :locale,
                default_joiner_role = :default_joiner_role,
                default_invitee_role = :default_invitee_role,
                channel_status      = :channel_status,
                is_auto_closed      = :is_auto_closed,                
                close_after_days    = :close_after_days,                              
                last_active_on      = :last_active_on,         
                has_logo            = :has_logo,
                has_background      = :has_background,
                require_join_approval = :require_join_approval,
                allow_external_read = :allow_external_read,
                open_team_join      = :open_team_join,
                contact_form_id     = :contact_form_id,
                contact_form_filler_id     = :contact_form_filler_id,
                one_one             = :one_one,
                one_one_approved    = :one_one_approved,
                created_on          = :created_on,
                owned_by          = :owned_by,
                updated_on          = :updated_on,
                updated_by          = :updated_by
               WHERE
                id                  = :id";
            $count = $this->dbs->update($sql, $channel->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to return a Channel from channel id
     *
     * @param $id
     * @return Channel
     * @throws DatabaseException
     */
    public function getChannelById(int $id): ?Channel
    {
        try {
            $sql = "SELECT * FROM channels where id = :id";
            $record = $this->dbs->selectOne($sql, ['id' => $id]);
            return (empty($record) ? null : Channel::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to return channels for a team
     *
     * @param $teamId
     * @return Iterable
     * @throws DatabaseException
     */
    public function getChannelsByTeamId(int $teamId): iterable
    {
        try {
            $sql = "SELECT * FROM channels where team_id = :team_id";
            return $this->dbs->cursor($sql, ['team_id' => $teamId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $seconds number of seconds-old that a user can be considered new
     * @return array
     * @throws DatabaseException
     */
    public function getNewChannels(int $seconds): array
    {
        try {
            $sql = "SELECT * FROM channels WHERE created_on > DATE_SUB(NOW(), INTERVAL :seconds SECOND);";
            return $this->dbs->select($sql, ['seconds' => $seconds]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query statement to delete a channel
     *
     * @param $id
     * @return bool
     * @throws DatabaseException
     */
    public function deleteChannel(int $id): bool
    {
        try {
            $sql = "DELETE FROM channels where id = :id";
            $count = $this->dbs->delete($sql, ['id' => $id]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get Channel by channel email
     *
     * @param string $email
     * @return Channel|null
     * @throws DatabaseException
     */
    public function getChannelByEmail(string $email): ?Channel
    {
        try {
            $sql = "SELECT * FROM channels where channel_email = :channel_email;";
            $record = $this->dbs->selectOne($sql, ['channel_email' => $email]);
            return (empty($record) ? null : Channel::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get Channel By Name and Team Id
     *
     * @param int $teamId
     * @param string $channelName
     * @return Channel|null
     * @throws DatabaseException
     */
    public function getChannelByName(int $teamId, string $channelName) : ?Channel
    {
        try {
            $sql = "SELECT * FROM channels where team_id = :team_id AND channel_name = :channel_name;";
            $record = $this->dbs->selectOne($sql, ['team_id' => $teamId, 'channel_name' => $channelName]);
            return (empty($record) ? null : Channel::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Channel Search
     *
     * @param string|null $keyword
     * @param int|null $channelStatus
     * @param int|null $offset
     * @param int|null $rowCount
     * @param bool $onlyChannelInfo
     * @param string $sortBy
     * @param string $sortDirection
     * @return iterable
     * @throws DatabaseException
     */
    public function search(?string $keyword, ?int $channelStatus, ?int $offset, ?int $rowCount, bool $onlyChannelInfo = false, string $sortBy = 'created_on', string $sortDirection = 'desc'): iterable
    {
        if (!in_array($sortBy, ['id', 'last_active_on', 'created_on'])) {
            throw new DatabaseException('Bad `sort_by` field');
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            throw new DatabaseException("$sortDirection is not a valid sorting direction.");
        }

        try {

            if ($onlyChannelInfo) {
                $sql = "SELECT DISTINCT c.*
                    FROM channels c                                           
                    WHERE (:channel_status is null OR c.channel_status = :channel_status)
                    AND (:keyword is null OR :keyword = '%%' OR
                                  (c.channel_name LIKE :keyword 
                                   OR c.channel_email LIKE :keyword))";
            }
            else {
                $sql = "SELECT DISTINCT c.*
                    FROM channels c
                      LEFT JOIN channel_users cu on c.id = cu.channel_id
                      LEFT JOIN users u on cu.user_id = u.id
                    WHERE (:channel_status is null OR c.channel_status = :channel_status)
                    AND (:keyword is null OR :keyword = '%%' OR
                                  (c.channel_name LIKE :keyword 
                                   OR c.channel_email LIKE :keyword
                                   OR u.email LIKE :keyword
                                   OR u.display_name LIKE :keyword))";
            }

            $criteria = ['keyword' => "%$keyword%", 'channel_status' => $channelStatus];

            // order
            $sql .= "ORDER BY c.{$sortBy} {$sortDirection} ";

            if (!empty($rowCount)) {
                $sql .= "LIMIT :offset, :row_count";
                $criteria['offset'] = empty($offset) ? 0 : $offset;
                $criteria['row_count'] = $rowCount;
            }
            return $this->dbs->cursor($sql, $criteria);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param string|null $keyword
     * @param int|null $channelStatus
     * @return int
     * @throws DatabaseException
     */
    public function searchCount(?string $keyword, ?int $channelStatus, bool $onlyChannelInfo = false): int
    {
        try {
            if ($onlyChannelInfo) {
                $sql = "SELECT count(DISTINCT c.id) as 'total'
                FROM channels c                                    
                WHERE (:channel_status is null OR c.channel_status = :channel_status)
                AND (:keyword is null OR :keyword = '%%' OR
                              (c.channel_name LIKE :keyword 
                               OR c.channel_email LIKE :keyword))";
            }
            else {
                $sql = "SELECT count(DISTINCT c.id) as 'total'
                FROM channels c
                  LEFT JOIN channel_users cu on c.id = cu.channel_id
                  LEFT JOIN users u on cu.user_id = u.id
                WHERE (:channel_status is null OR c.channel_status = :channel_status)
                AND (:keyword is null OR :keyword = '%%' OR
                              (c.channel_name LIKE :keyword 
                               OR c.channel_email LIKE :keyword
                               OR u.email LIKE :keyword
                               OR u.display_name LIKE :keyword))";
            }
            $criteria = ['keyword' => "%$keyword%", 'channel_status' => $channelStatus];
            $record = $this->dbs->selectOne($sql, $criteria);
            return (empty($record) ? 0 : (int)$record['total']);
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
    public function getAllChannelCount() : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM channels;";
            $record = $this->dbs->selectOne($sql, []);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return iterable
     * @throws DatabaseException
     */
    public function getAllChannels()
    {
        try {
            $sql = "SELECT * FROM channels";
            return $this->dbs->cursor($sql);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return iterable
     * @throws DatabaseException
     */
    public function getOpenChannelsExpired() : iterable
    {
        try {
            $sql = "SELECT * FROM channels c
                WHERE channel_status = 1 AND is_auto_closed = 1
                AND last_active_on < CURRENT_DATE - INTERVAL c.close_after_days DAY;";
            return $this->dbs->cursor($sql, []);
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
    public function getMessageCount(int $channelId): int
    {
        try {
            $sql = "SELECT COUNT(1) as count FROM channels c INNER JOIN messages m ON m.channel_id = c.id WHERE c.id = :channel_id;";
            $record = $this->dbs->selectOne($sql, ['channel_id' => $channelId]);
            return (empty($record) ? 0 : (int)$record['count']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $formId
     * @param int $fillerId
     * @return Channel|null
     * @throws DatabaseException
     */
    public function findByContactForm(int $formId, int $fillerId): ?Channel
    {
        try {
            $sql = <<<SQL
                SELECT *
                FROM channels c
                WHERE c.contact_form_id = :form_id
                AND c.contact_form_filler_id = :filler_id
SQL;

            $record = $this->dbs->selectOne($sql, ['form_id' => $formId, 'filler_id' => $fillerId]);
            return empty($record) ? null : Channel::withDBData($record);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId1
     * @param int $userId2
     * @return Channel|null
     * @throws DatabaseException
     */
    public function findOneOnOne(int $userId1, int $userId2): ?Channel
    {
        try {
            $sql = <<<SQL
                SELECT c.*
                FROM channels c
                WHERE c.one_one
                AND EXISTS (
                    SELECT 1 FROM channel_users WHERE user_id = :user1 AND channel_id = c.id
                )
                AND EXISTS (
                    SELECT 1 FROM channel_users WHERE user_id = :user2 AND channel_id = c.id
                )
SQL;

            $record = $this->dbs->selectOne($sql, ['user1' => $userId1, 'user2' => $userId2]);
            return empty($record) ? null : Channel::withDBData($record);
        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return array
     * @throws DatabaseException
     */
    public function getPublicChannelStats(): array
    {
        try {
            $sql = <<<SQL
                SELECT 	COUNT(DISTINCT(cu.user_id)) AS user_count, 
                        COUNT(DISTINCT(m.id)) AS message_count, 
                        MAX(m.created_on) AS latest_message_on
                FROM channels c
                LEFT JOIN messages m ON c.id = m.channel_id
                LEFT JOIN channel_users cu ON cu.channel_id = c.id
                WHERE public_hash IS NOT NULL
                GROUP BY c.id;
SQL;

            return $this->dbs->select($sql);
        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getReadStatus(int $channelId): array
    {
        try {
            $sql = <<<sql
                SELECT cu.user_id, cu.read_watermark_id AS read_watermark
                FROM channel_users cu
                WHERE cu.channel_id = :channel_id
sql;


            $result = $this->dbs->select($sql, ['channel_id' => $channelId]);

            return array_map(function ($row) {
                return [
                    'user_id' => (int) $row['user_id'],
                    'read_watermark' => (int) $row['read_watermark'],
                ];
            }, $result);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $teamId
     * @param int $userId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function findTeamChannelsForUser(int $teamId, int $userId): array
    {
        try {
            $sql = <<<sql
                SELECT c.* 
                FROM channels c 
                INNER JOIN channel_users cu
                    ON cu.channel_id = c.id
                WHERE c.team_id = :teamId
                    AND cu.user_id = :userId;
sql;

            $rows = $this->dbs->select($sql, compact('teamId', 'userId'));

            return array_map(function ($row) {
                return Channel::withDBData($row);
            }, $rows);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $teamId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function findTeamChannels(int $teamId): array
    {
        try {
            $sql = <<<sql
                SELECT c.* 
                FROM channels c 
                WHERE c.team_id = :teamId;
sql;

            $rows = $this->dbs->select($sql, compact('teamId'));

            return array_map(function ($row) {
                return Channel::withDBData($row);
            }, $rows);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $teamId
     * @param int $userId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function getTeamChannelsOwnedByUser(int $teamId, int $userId): array
    {
        try {
            $sql = <<<sql
                SELECT c.*
                FROM channels c
                WHERE c.team_id = :teamId
                    AND owned_by = :userId
sql;
            $rows = $this->dbs->select($sql, compact('teamId', 'userId'));
            return array_map(function ($row) {
                return Channel::withDBData($row);
            }, $rows);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $teamId
     * @param int $userId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function getTeamChannelsForUser(int $teamId, int $userId): array
    {
        try {
            $sql = <<<sql
                SELECT DISTINCT c.*
                FROM channels c
                    INNER JOIN channel_users cu ON cu.channel_id = c.id
                WHERE c.team_id = :teamId
                AND cu.user_id = :userId
sql;
            $rows = $this->dbs->select($sql, compact('teamId', 'userId'));
            return array_map(function ($row) {
                return Channel::withDBData($row);
            }, $rows);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getDMSentByInviterCount(int $channelId): int
    {
        try {
            $sql = <<<sql
                SELECT COUNT(*) AS count
                FROM channels c
                  INNER JOIN messages m ON m.channel_id = c.id AND m.user_id = c.owned_by
                WHERE c.id = :channelId 
                  AND c.one_one;      
sql;

            $row = $this->dbs->selectOne($sql, compact('channelId'));
            return (int)$row['count'];
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}

