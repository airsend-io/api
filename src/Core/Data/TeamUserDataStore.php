<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class TeamUserDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService
     */
    protected $dbs;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
    }

    /**
     * Query to get add user to a channel
     *
     * @param TeamUser $teamUser
     * @return bool
     * @throws DatabaseException
     */
    public function addTeamUser(TeamUser $teamUser): bool
    {
        try {
            $sql = "INSERT INTO team_users
              SET
                team_id         = :team_id,
                user_id         = :user_id,
                user_role       = :user_role,                
                created_on      = :created_on,
                created_by      = :created_by";
            $count = $this->dbs->insert($sql, $teamUser->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to delete a user in a team
     *
     * @param int $teamId
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    public function dropTeamUser(int $teamId, int $userId): int
    {
        try {
            $sql = "DELETE FROM team_users where team_id = :team_id AND user_id = :user_id";
            $count = $this->dbs->delete($sql, ['team_id' => $teamId, 'user_id' => $userId]);
            return $count;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to remove all users in a team
     *
     * @param int $team_id
     * @return int
     * @throws DatabaseException
     */
    public function dropAllUsersFromTeam(int $team_id): int
    {
        try {
            $sql = "DELETE FROM team_users where team_id = :team_id;";
            $count = $this->dbs->delete($sql, ['team_id' => $team_id]);
            return $count;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get default team for user
     *
     * @param int $user_id
     * @return Team|null
     * @throws DatabaseException
     */
    public function getDefaultTeamForUser(int $user_id) : ?Team
    {
        try {
            $sql = "SELECT teams.* 
                FROM teams
                    JOIN team_users ON teams.id = team_users.team_id
                where teams.team_type = 1 AND team_users.user_id = :user_id;";
            $record = $this->dbs->selectOne($sql, ['user_id' => $user_id]);
            return (empty($record) ? null : Team::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @param int $teamId
     * @return TeamUser|null
     * @throws DatabaseException
     */
    public function getTeamUser(int $userId, int $teamId) : ?TeamUser
    {
        try {
            $sql = "SELECT * FROM team_users where team_id = :team_id and user_id = :user_id;";
            $record = $this->dbs->selectOne($sql, ['team_id' => $teamId, 'user_id' => $userId]);
            return (empty($record) ? null : TeamUser::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $teamId
     * @return TeamUser[]
     * @throws DatabaseException
     */
    public function getTeamUsers(int $teamId) : array
    {
        try {
            $sql = "SELECT tu.*
                from team_users tu
                    JOIN users  u on tu.user_id = u.id                
                where tu.team_id = :team_id;";
            return array_map(function (array $row) {
                return TeamUser::withDBData($row);
            }, $this->dbs->select($sql, ['team_id' => $teamId]));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getTeamUsersCount(int $teamId): int
    {
        try {
            $sql = <<<sql
                SELECT COUNT(*) AS count
                FROM team_users tu               
                WHERE tu.team_id = :team_id;
sql;
            $row = $this->dbs->selectOne($sql, ['team_id' => $teamId]);
            return (int) $row['count'];

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getTeamOwner(int $teamId): ?User
    {

        $role = TeamUser::TEAM_USER_ROLE_OWNER;

        try {
            $sql = <<<sql
                SELECT u.*
                FROM team_users tu    
                    INNER JOIN users u ON u.id = tu.user_id
                WHERE tu.team_id = :teamId AND tu.user_role = :role;
sql;
            $row = $this->dbs->selectOne($sql, compact('teamId', 'role'));
            return $row === null ? null : User::withDBData($row);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function isMember(int $teamId, int $userId): bool
    {
        try {
            $sql = <<<sql
                SELECT 1
                FROM team_users tu    
                WHERE tu.team_id = :teamId AND tu.user_id = :userId;
sql;
            $row = $this->dbs->selectOne($sql, compact('teamId', 'userId'));
            return $row !== null;

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $teamId
     * @return Team[]
     * @throws DatabaseException
     */
    public function getTeamMembers(int $teamId): array
    {
        try {
            $sql = <<<sql
                SELECT u.*
                from team_users tu
                    JOIN users u on tu.user_id = u.id                
                WHERE tu.team_id = :teamId;
sql;
            $rows = $this->dbs->select($sql, compact('teamId'));
            return array_map(function($row) {
                return User::withDBData($row);
            }, $rows);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function setTeamUserRole(int $teamId, int $userId, int $role): void
    {
        try {
            $sql = <<<sql
                UPDATE team_users
                SET user_role = :role
                WHERE team_id = :teamId AND user_id = :userId
               
sql;
            $this->dbs->executeStatement($sql, compact('teamId', 'userId', 'role'));

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}