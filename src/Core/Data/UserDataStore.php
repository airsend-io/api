<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Database\DatabaseService;
use Generator;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class UserDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

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
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Query Statement to create user
     *
     * @param User $user
     * @return bool
     * @throws DatabaseException
     */
    public function createUser(User $user) : bool
    {

        $user->setUpdatedOn(date('Y-m-d H:i:s'));

        try {
            $sql = "INSERT INTO users
              SET                
                email               = :email,
                phone               = :phone,      
                password            = :password,                
                display_name        = :display_name,
                has_avatar          = :has_avatar,
                user_role           = :user_role,             
                account_status      = :account_status,
                approval_status     = :approval_status,
                trust_level         = :trust_level,               
                online_status       = :online_status,
                is_auto_pwd         = :is_auto_pwd,
                is_terms_agreed     = :is_terms_agreed,
                is_tour_complete    = :is_tour_complete,
                is_email_verified   = :is_email_verified,
                is_phone_verified   = :is_phone_verified,
                timezone            = :timezone,  
                locale              = :locale,  
                date_format         = :date_format,
                time_format         = :time_format,
                lang_code           = :lang_code,
                user_status         = :user_status,
                user_status_message = :user_status_message,
                invited_by          = :invited_by,
                is_pwd_reset        = :is_pwd_reset,
                is_locked           = :is_locked,              
                last_active_on      = :last_active_on,
                created_on          = :created_on,                
                updated_on          = :updated_on,
                updated_by          = :updated_by,
                notifications_config = :notifications_config";
            $count = $this->dbs->insert($sql, $user->getArray());
            $user->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to update user
     *
     * @param User $user
     * @return bool
     * @throws DatabaseException
     */
    public function updateUser(User $user) : bool
    {

        $user->setUpdatedOn(date('Y-m-d H:i:s'));

        try {
            $sql = "UPDATE users
              SET
                email               = :email,
                phone               = :phone,      
                password            = :password,                
                display_name        = :display_name,
                has_avatar          = :has_avatar,
                user_role           = :user_role,             
                account_status      = :account_status,
                approval_status     = :approval_status,
                trust_level         = :trust_level,               
                online_status       = :online_status,
                is_auto_pwd         = :is_auto_pwd,
                is_terms_agreed     = :is_terms_agreed,
                is_tour_complete    = :is_tour_complete,
                is_email_verified   = :is_email_verified,
                is_phone_verified   = :is_phone_verified,
                timezone            = :timezone,
                locale              = :locale,
                date_format         = :date_format,
                time_format         = :time_format,
                lang_code           = :lang_code,
                user_status         = :user_status,
                user_status_message = :user_status_message,
                invited_by          = :invited_by,
                is_pwd_reset        = :is_pwd_reset,
                is_locked           = :is_locked,              
                last_active_on      = :last_active_on,                
                created_on          = :created_on,                
                updated_on          = :updated_on,
                updated_by          = :updated_by,
                notifications_config = :notifications_config
               WHERE
                id                  = :id";
            $count = $this->dbs->update($sql, $user->getArray());
            return $count !== -1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to return a user from user id
     *
     * @param $id
     * @return User
     * @throws DatabaseException
     */
    public function getUserById(int $id) : ?User
    {
        try {
            $sql = "SELECT * FROM users where id = :id";
            $record = $this->dbs->selectOne($sql, ['id' => $id]);
            return (empty($record) ? null : User::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to return a user from email
     *
     * @param $email
     * @return User
     * @throws DatabaseException
     */
    public function getUserByEmail(string $email) : ?User
    {
        try {
            $sql = "SELECT * FROM users where email = :email";
            $record = $this->dbs->selectOne($sql, ['email' => $email]);
            return (empty($record) ? null : User::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query Statement to return a user from phone
     *
     * @param $phone
     * @return User
     * @throws DatabaseException
     */
    public function getUserByPhone(string $phone) : ?User
    {
        try {
            $sql = "SELECT * FROM users where phone = :phone";
            $record = $this->dbs->selectOne($sql, ['phone' => $phone]);
            return (empty($record) ? null : User::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query statement to delete a user
     *
     * @param $id
     * @return bool
     * @throws DatabaseException
     */
    public function deleteUser(int $id) : bool
    {
        try {
            $sql = "DELETE FROM users where id = :id";
            $count = $this->dbs->delete($sql, ['id' => $id]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to get all users
     *
     * @return array
     * @throws DatabaseException
     */
    public function getAllUsers() : iterable
    {
        try {
            $sql = "SELECT * FROM users";
            return $this->dbs->cursor($sql, []);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    public function getInvitationSentCount(int $userId) : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM users where invited_by = :invited_by;";
            $record = $this->dbs->selectOne($sql, ['invited_by' => $userId]);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param string|null $keyword
     * @param int|null $accountStatus
     * @param int|null $approvalStatus
     * @param int|null $offSet
     * @param int|null $rowCount
     * @param string|null $sortBy
     * @param string|null $sortDirection
     * @return iterable
     * @throws DatabaseException
     */
    public function searchUsers(?string $keyword, ?int $accountStatus, ?int $approvalStatus, ?int $offSet, ?int $rowCount, ?string $sortBy = null, ?string $sortDirection = null, ?int $userType = 0) : iterable
    {
        if (!in_array($sortBy, ['id', 'last_active_on', 'created_on'])) {
            throw new DatabaseException('Bad `sort_by` field');
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            throw new DatabaseException("$sortDirection is not a valid sorting direction.");
        }

        // User Type == 0 (No special user type)
        // User Type == 1 (Engaged Users)
        // User Type == 2 (Active Users)

        try {
            if (empty($rowCount)) {
                $sql = "SELECT * 
                FROM users
                WHERE (:keyword is null or :keyword = '%%' OR (email LIKE :keyword OR display_name LIKE :keyword OR phone LIKE :keyword)) 
                AND (:account_status = -1 OR account_status = :account_status)
                AND (:approval_status = -1 OR approval_status = :approval_status)
                AND (:user_type = 0 
                                OR (:user_type = 1 AND datediff(last_active_on, created_on) > 7 AND last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com')
                                OR (:user_type = 2 AND last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com'))
                ORDER BY {$sortBy} {$sortDirection};";
                $criteria = [
                    'keyword' => "%$keyword%",
                    'account_status' => $accountStatus,
                    'approval_status' => $approvalStatus,
                    'user_type' => $userType
                ];
            } else {
                $sql = "SELECT * 
                FROM users
                WHERE (:keyword is null or :keyword = '%%' OR (email LIKE :keyword OR display_name LIKE :keyword OR phone LIKE :keyword)) 
                AND (:account_status = -1 OR account_status = :account_status)
                AND (:approval_status = -1 OR approval_status = :approval_status)
                AND (:user_type = 0 
                                OR (:user_type = 1 AND datediff(last_active_on, created_on) > 7 AND last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com')
                                OR (:user_type = 2 AND last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com'))
                ORDER BY {$sortBy} {$sortDirection}
                LIMIT :offset, :row_count;";
                $criteria = [
                    'keyword' => "%$keyword%",
                    'account_status' => $accountStatus,
                    'approval_status' => $approvalStatus,
                    'offset' => empty($offSet) ? 0 : $offSet,
                    'row_count' => $rowCount,
                    'user_type' => $userType
                ];
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
     * @param int|null $accountStatus
     * @param int|null $approvalStatus
     * @return int
     * @throws DatabaseException
     */
    public function countUsers(?string $keyword, ?int $accountStatus, ?int $approvalStatus, ?int $userType) : int
    {
        // User Type == 0 (No special user type)
        // User Type == 1 (Engaged Users)
        // User Type == 2 (Active Users)

        try {
            $sql = "SELECT count(1)  as total
                FROM users
                WHERE (:keyword is null or :keyword = '%%' OR (email LIKE :keyword OR display_name LIKE :keyword OR phone LIKE :keyword)) 
                AND (:account_status = -1 OR account_status = :account_status)
                AND (:approval_status = -1 OR approval_status = :approval_status)
                AND (:user_type = 0 
                                OR (:user_type = 1 AND datediff(last_active_on, created_on) > 7 AND last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com')
                                OR (:user_type = 2 AND last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com'))
                ORDER BY LAST_ACTIVE_ON DESC;";
            $criteria = [
                'keyword' => "%$keyword%",
                'account_status' => $accountStatus,
                'approval_status' => $approvalStatus,
                'user_type' => $userType
            ];
            $record = $this->dbs->selectOne($sql, $criteria);
            return (empty($record) ? 0 : (int)$record['total']);
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
    public function getNewUsers(int $seconds): array
    {
        try {
            $sql = "SELECT * FROM users WHERE created_on > DATE_SUB(NOW(), INTERVAL :seconds SECOND);";
            return $this->dbs->select($sql, ['seconds' => $seconds]);
        }
        catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return int
     * @throws DatabaseException
     */
    public function getAllUserCount() : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM users;";
            $record = $this->dbs->selectOne($sql, []);
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
    public function getEngagedUserCount() : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM users u WHERE datediff(u.last_active_on, u.created_on) > 7 AND u.last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY) AND email NOT LIKE '%codelathe.com' AND email NOT LIKE '%airsend.io';";
            $record = $this->dbs->selectOne($sql, []);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getActiveUserCount() : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM users u WHERE u.last_active_on > DATE_SUB(NOW(), INTERVAL 14  DAY);";
            $record = $this->dbs->selectOne($sql, []);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param string $tag
     * @return User|null
     * @throws DatabaseException
     */
    public function getUserByEmailTag(int $channelId, string $tag): ?User
    {
        try {
            $sql = <<<sql
                SELECT u.*
                FROM users u 
                INNER JOIN channel_users cu on u.id = cu.user_id
                WHERE cu.channel_id = :channel_id AND cu.email_tag = :email_tag 
sql;

            $record = $this->dbs->selectOne($sql, ['channel_id' => $channelId, 'email_tag' => $tag]);
            return empty($record) ? null : User::withDBData($record);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return Generator|int[]
     * @throws DatabaseException
     */
    public function getAllIds(): Generator
    {
        try {
            $sql = <<<sql
                SELECT id
                FROM users
sql;
            foreach ($this->dbs->cursor($sql) as $row) {
                yield (int) $row['id'];
            }
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function hasRelation(int $userId1, int $userId2): bool
    {
        try {

            $bindings = ['id1' => $userId1, 'id2' => $userId2];

            // first check if the users are members of the same team
            $sql = <<<sql
                SELECT 1
                FROM team_users tu1 INNER JOIN team_users tu2 ON tu1.team_id = tu2.team_id 
                WHERE tu1.user_id = :id1 AND tu2.user_id = :id2;
sql;
            if ($this->dbs->selectOne($sql, $bindings) !== null) {
                return true;
            }

            // then check if the users share at least 1 channel
            $sql = <<<sql
                SELECT 1
                FROM channel_users cu1 INNER JOIN channel_users cu2 ON cu1.channel_id = cu2.channel_id 
                WHERE cu1.user_id = :id1 AND cu2.user_id = :id2;
sql;
            return $this->dbs->selectOne($sql, $bindings) !== null;

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function newUsersWithChannels(int $seconds)
    {
        try {
            $sql = <<<sql
                SELECT u.*, count(*) as channels_count
                FROM users u
                INNER JOIN channel_users cu ON cu.user_id = u.id
                WHERE u.created_on > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
                GROUP BY u.id;
sql;
            return $this->dbs->select($sql, ['seconds' => $seconds]);
        }
        catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function usedTeamSeats(int $userId): int
    {
        try {
            $sql = <<<sql
                SELECT count(*) as count
                FROM teams t
                INNER JOIN team_users tu ON tu.team_id = t.id 
                INNER JOIN team_users tu2 ON tu2.team_id = t.id
                WHERE tu.user_id = :userId
                AND tu.user_role = :userRole
                AND t.team_type = :teamType;
sql;
            $userRole = TeamUser::TEAM_USER_ROLE_OWNER;
            $teamType = Team::TEAM_TYPE_STANDARD;
            $row = $this->dbs->selectOne($sql, compact('userId', 'userRole', 'teamType'));
            return (int) ($row['count'] ?? 0);
        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}

