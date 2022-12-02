<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use Carbon\Carbon;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;


class TeamDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    private $logger;

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
     * Query to create a team
     *
     * @param Team $team
     * @param int $ownerId
     */
    public function createTeam(Team $team): void
    {
        try {
            $this->dbs->transaction(function() use ($team) {

                // first create the team
                $sql = <<<sql
                    INSERT INTO teams
                    SET
                        team_name       = :team_name,
                        team_type       = :team_type,                                             
                        created_on      = :created_on,
                        created_by      = :created_by,
                        updated_on      = :updated_on,
                        updated_by      = :updated_by
sql;
                $this->dbs->insert($sql, $team->getArray());
                $team->setId((int)$this->dbs->lastInsertId());

                // then link it to the user (as owner/admin). We do it in the same transaction, because couldn't exist
                // a team without an owner
                $sql = <<<sql
                    INSERT INTO team_users
                    SET
                        team_id = :team_id,
                        user_id = :user_id,
                        user_role = :user_role,
                        created_on = :created_on,
                        created_by = :created_by
sql;
                $this->dbs->insert($sql, [
                    'team_id' => $team->getId(),
                    'user_id' => $team->getCreatedBy(),
                    'user_role' => TeamUser::TEAM_USER_ROLE_OWNER,
                    'created_on' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $team->getCreatedBy(),
                ]);

            });
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to update a team
     *
     * @param Team $team
     * @return bool
     * @throws DatabaseException
     */
    public function updateTeam(Team $team): bool
    {
        try {
            $sql = "UPDATE teams
              SET
                team_name      = :team_name,
                team_type       = :team_type,                                           
                created_on      = :created_on,
                created_by      = :created_by,
                updated_on      = :updated_on,
                updated_by      = :updated_by
               WHERE
                id              = :id;";
            $count = $this->dbs->insert($sql, $team->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to delete a team
     *
     * @param int $teamId
     * @return int
     * @throws DatabaseException
     */
    public function deleteTeam(int $teamId): int
    {
        try {
            $sql = "DELETE FROM teams where id = :id;";
            $count = $this->dbs->delete($sql, ['id' => $teamId]);
            return $count;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get team by team id
     *
     * @param int $teamId
     * @return Team|null
     * @throws DatabaseException
     */
    public function getTeamByTeamId(int $teamId) : ?Team
    {
        try {
            $sql = "SELECT * from teams where id = :id";
            $record = $this->dbs->selectOne($sql, ['id' => $teamId]);
            return (empty($record) ? null : Team::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get teams that users belong to
     *
     * @param int $userId
     * @return TeamUser[]
     * @throws DatabaseException
     */
    public function getTeamUsersForUser(int $userId): array
    {
        try {
            $sql = "SELECT * from team_users where user_id = :user_id";
            $rows = $this->dbs->select($sql, ['user_id' => $userId]);
            return array_map(function ($row) {
                return TeamUser::withDBData($row);
            }, $rows);
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
    public function getAllTeamCount() : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM teams;";
            $record = $this->dbs->selectOne($sql, []);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Team Search
     *
     * @param string|null $keyword
     * @param int|null $teamType
     * @param int|null $offset
     * @param int|null $rowCount
     * @return iterable
     * @throws DatabaseException
     */
    public function search(?string $keyword, ?int $teamType, ?int $offset, ?int $rowCount): iterable
    {
        try {
            $sql = "SELECT DISTINCT t.*
                FROM teams t
                  LEFT JOIN team_users tu on t.id = tu.team_id
                  LEFT JOIN users u on tu.team_id = u.id
                WHERE (:team_type is null OR t.team_type = :team_type)
                AND (:keyword is null OR :keyword = '%%' OR
                              (t.team_name LIKE :keyword                               
                               OR u.email LIKE :keyword
                               OR u.display_name LIKE :keyword))";
            $criteria = ['keyword' => "%$keyword%", 'team_type' => $teamType];

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
     * Team Search
     *
     * @param string|null $keyword
     * @param int|null $teamType
     * @return int
     * @throws DatabaseException
     */
    public function searchCount(?string $keyword, ?int $teamType): int
    {
        try {
            $sql = "SELECT count(DISTINCT t.id) as 'total'
                FROM teams t
                  LEFT JOIN team_users tu on t.id = tu.team_id
                  LEFT JOIN users u on tu.team_id = u.id
                WHERE (:team_type is null OR t.team_type = :team_type)
                AND (:keyword is null OR :keyword = '%%' OR
                              (t.team_name LIKE :keyword                               
                               OR u.email LIKE :keyword
                               OR u.display_name LIKE :keyword))";
            $criteria = ['keyword' => "%$keyword%", 'team_type' => $teamType];
            $record = $this->dbs->selectOne($sql, $criteria);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @return Team|null
     * @throws DatabaseException
     */
    public function getSelfTeam(int $userId): ?Team
    {
        try {
            $sql = <<<sql
                SELECT t.* 
                FROM teams t
                INNER JOIN team_users tu on t.id = tu.team_id
                WHERE tu.user_id = :user_id
                AND t.team_type = :team_type
sql;
            $record = $this->dbs->selectOne($sql, ['user_id' => $userId, 'team_type' => Team::TEAM_TYPE_SELF]);
            return (empty($record) ? null : Team::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getByName(string $name): ?Team
    {
        try {
            $sql = <<<sql
                SELECT *
                FROM teams
                WHERE team_name = :name
sql;
            $record = $this->dbs->selectOne($sql, ['name' => $name]);
            return (empty($record) ? null : Team::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getTeamsForUser(int $userId, ?int $userRole = null)
    {
        try {
            $sql = <<<sql
                SELECT t.*
                FROM team_users tu
                INNER JOIN teams t ON tu.team_id = t.id
                WHERE tu.user_id = :userId
sql;
            $bindings = compact('userId');

            if ($userRole !== null) {
                $sql .= ' AND tu.user_role = :userRole';
                $bindings['userRole'] = $userRole;
            }
            $rows = $this->dbs->select($sql, $bindings);
            return array_map(function ($row) {
                return Team::withDBData($row);
            }, $rows);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


}