<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Service\Database\DatabaseService;
use phpDocumentor\Reflection\Types\Iterable_;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class SpamReportDataStore
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
     * @var ConfigRegistry
     */
    private $config;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     * @param ConfigRegistry $config
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger, ConfigRegistry $config)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function createReport(int $channelId, int $reportedUserId, bool $isDM, int $reporterId, string $message): void
    {
        try {
            $sql = <<<sql
                INSERT INTO spam_reports (
                    channel_id,
                    reported_user_id,
                    is_dm,
                    reporter_id,
                    reported_at,
                    report_message
                )
                VALUES (
                    :channelId,
                    :reportedUserId,
                    :isDM,
                    :reporterId,
                    now(),
                    :message
                );
sql;

            $this->dbs->insert($sql, compact('channelId', 'reportedUserId', 'isDM', 'reporterId', 'message'));

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param Action $action
     * @return bool
     * @throws DatabaseException
     */
    public function updateAction(Action $action) : bool
    {
        try {
            $sql = "UPDATE actions
              SET                    
                channel_id          = :channel_id,      
                parent_id           = :parent_id,
                action_name         = :action_name,
                action_desc         = :action_desc,
                action_type         = :action_type,
                action_status       = :action_status,
                order_position      = :order_position,
                due_on              = :due_on,                
                created_on          = :created_on,
                created_by          = :created_by,                
                updated_on          = :updated_on,
                updated_by          = :updated_by
               WHERE
                id                  = :id";
            $count = $this->dbs->update($sql, $action->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $actionId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteAction(int $actionId) : bool
    {
        try {
            $sql = "DELETE FROM actions where id = :id";
            $count = $this->dbs->delete($sql, ['id' => $actionId]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $actionId
     * @return Action|null
     * @throws DatabaseException
     */
    public function getActionById(int $actionId) : ?Action
    {
        try {
            $sql = "SELECT * FROM actions where id = :id";
            $record = $this->dbs->selectOne($sql, ['id' => $actionId]);
            return (empty($record) ? null : Action::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @return iterable
     * @throws DatabaseException
     */
    public function getActionsForChannel(int $channelId) : iterable
    {
        try {
            $sql = "SELECT * FROM actions where channel_id = :channel_id ORDER BY order_position";
            return $this->dbs->cursor($sql, ['channel_id' => $channelId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param UserAction $userAction
     * @return bool
     * @throws DatabaseException
     */
    public function addUserAction(UserAction $userAction) : bool
    {
        try {
            $sql = "INSERT INTO user_actions
              SET                
                action_id           = :action_id,
                user_id             = :user_id,                
                created_on          = :created_on,
                created_by          = :created_by";
            $count = $this->dbs->insert($sql, $userAction->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $actionId
     * @return int
     * @throws DatabaseException
     */
    public function deleteUserActions(int $actionId) : int
    {
        try {
            $sql = "DELETE FROM user_actions where action_id = :action_id;";
            return $this->dbs->delete($sql, ['action_id' => $actionId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }

        return 0;
    }

    /**
     * Delete action from user
     *
     * @param int $actionId
     * @param $userId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteUserAction(int $actionId, $userId) : bool
    {
        try {
            $sql = "DELETE FROM user_actions where user_id = :user_id and action_id = :action_id";
            $count = $this->dbs->delete($sql, ['user_id' => $userId, 'action_id' => $actionId]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $actionId
     * @return Action|null
     * @throws DatabaseException
     */
    public function getActionWithUsers(int $actionId) : ?Action
    {
        try {
            $sql = <<<SQL
                SELECT a.*, CASE WHEN ua.user_id IS NOT NULL THEN JSON_OBJECT('user_id',ua.user_id,'action_id',ua.action_id, 'created_on',ua.created_on,'created_by',ua.created_by) ELSE null END AS users
                FROM actions a
		            LEFT JOIN user_actions ua ON a.id = ua.action_id
                WHERE a.id = :action_id;
SQL;
            $records = $this->dbs->select($sql, ['action_id' => $actionId]);
            $users = implode(',', array_column($records, 'users'));
            $record = array_shift($records);
            $record['users'] = "[$users]";

            return (empty($record) ? null : Action::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int|null $channelId
     * @param int|null $userId
     * @param int|null $parentId
     * @param bool $ignoreParentId
     * @return iterable
     * @throws DatabaseException
     */
    public function getActions(?int $channelId, ?int $userId, ?int $parentId = null, bool $ignoreParentId = false) : iterable
    {
        try {
            $sql = "SELECT a.* FROM actions a";
            $criteria = [];
            if ($channelId !== null && $userId !== null) {
                $sql .= " WHERE a.channel_id = :channel_id AND a.id in (SELECT action_id FROM user_actions WHERE user_id = :user_id)";
                $criteria = ['channel_id' => $channelId, 'user_id' => $userId];
            } else {
                if ($channelId === null && $userId !== null) {
                    $sql .= " WHERE a.id in (select action_id from user_actions where user_id = :user_id)";
                    $criteria = ['user_id' => $userId];
                } else {
                    if ($channelId !== null && $userId === null) {
                        $sql .= " WHERE a.channel_id = :channel_id";
                        $criteria = ['channel_id' => $channelId];
                    } else {
                        $criteria = [];
                    }
                }
            }
            if (!$ignoreParentId) {
                if ($parentId === null) {
                    $sql .= ' AND parent_id IS NULL';
                } else {
                    $sql .= ' AND parent_id = :parent_id';
                    $criteria['parent_id'] = $parentId;
                }
            }
            $sql .= " ORDER BY order_position DESC;";

            return $this->dbs->cursor($sql, $criteria);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @return iterable
     * @throws DatabaseException
     */
    public function getRemindersDueBetween(string $from , string $to) : iterable
    {

        try {
            $sql = "SELECT a.* FROM actions a WHERE a.due_on BETWEEN :from AND :to";
            $criteria = ['from' => $from, 'to' =>  $to];

            return $this->dbs->cursor($sql, $criteria);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }

    }
        /**
     * @param int|null $channelId
     * @return int
     * @throws DatabaseException
     */
    public function getAllActionCount(?int $channelId= null) : int
    {
        try {
            $sql = "SELECT COUNT(1) as total FROM actions";
            $criteria = [];

            if (!empty($channelId)) {
                $sql .= " WHERE channel_id = :channel_id;";
                $criteria = ['channel_id' => $channelId];
            } else {
                $sql .= ";";
            }

            $record = $this->dbs->selectOne($sql, $criteria);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function findLastPositionIndex(int $channelId, int $parentId = null): int
    {

        $criteria = [
            'channel_id' => $channelId
        ];

        if ($parentId !== null) {
            $parentIdSql = "= :parent_id";
            $criteria['parent_id'] = $parentId;
        } else {
            $parentIdSql = "IS NULL";
        }

        try {
            $sql = <<<sql
                SELECT order_position
                FROM actions
                WHERE channel_id = :channel_id
                AND parent_id {$parentIdSql}
                ORDER BY order_position DESC
                LIMIT 1
sql;

            $record = $this->dbs->selectOne($sql, $criteria);

            $lastPosition = empty($record) ? 0 : (int)$record['order_position'];

            return $lastPosition + 100;

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getKids(int $actionId): array
    {
        try {
            $sql = "SELECT * FROM actions WHERE parent_id = :action_id";
            return $this->dbs->select($sql, ['action_id' => $actionId]);
        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function findPositionIndex(int $channelId, ?int $afterActionId, ?int $parentActionId): int
    {
        try {

            if ($afterActionId !== null) {
                $sql = "SELECT order_position FROM actions WHERE id = :id";
                $result = $this->dbs->selectOne($sql, ['id' => $afterActionId]);
                $superiorLimit = (int) $result['order_position'];
            } else {
                return $this->findLastPositionIndex($channelId, $parentActionId);
            }

            if ($parentActionId === null) {
                $sql = "SELECT order_position FROM actions WHERE parent_id IS NULL AND order_position < :superior_limit ORDER BY order_position DESC LIMIT 1";
                $bindings = ['superior_limit' => $superiorLimit];
            } else {
                $sql = "SELECT order_position FROM actions WHERE parent_id = :parent_id AND order_position < :superior_limit ORDER BY order_position DESC LIMIT 1";
                $bindings = ['parent_id' => $parentActionId, 'superior_limit' => $superiorLimit];
            }
            $result = $this->dbs->selectOne($sql, $bindings);

            if ($result === null) {
                return $inferiorLimit = 0;
            }
            $inferiorLimit = (int) $result['order_position'];

            $position = (int) ($inferiorLimit + ceil(($superiorLimit - $inferiorLimit) / 2));

            // conflict - reorganize the positions from this point
            if ($position >= $superiorLimit) {
                if ($parentActionId === null) {
                    $sql = 'SELECT id FROM actions WHERE order_position >= :position AND parent_id IS NULL';
                    $bindings = ['position' => $position];
                } else {
                    $sql = 'SELECT id FROM actions WHERE order_position >= :position AND parent_id = :parent_id';
                    $bindings = ['position' => $position, 'parent_id' => $parentActionId];
                }
                $result = $this->dbs->select($sql, $bindings);
                $ids = array_map(function ($item) {
                    return (int) $item['id'];
                }, $result);

                $position += 100;
                $inc = $position;
                foreach ($ids as $id) {
                    $inc += 100;
                    $sql = 'UPDATE actions SET order_position = :order_position, updated_on = updated_on WHERE id = :id';
                    $bindings = ['order_position' => $inc, 'id' => $id];
                    $this->dbs->executeStatement($sql, $bindings);
                }
            }

            return $position;

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function move(int $actionId, ?int $parentActionId, int $positionIndex): void
    {
        try {
            $sql = <<<sql
                UPDATE actions 
                SET parent_id = :parent_id, 
                    order_position = :order_position,
                    updated_on = updated_on
                WHERE id = :id;
sql;

            $bindings = [
                'parent_id' => $parentActionId,
                'order_position' => $positionIndex,
                'id' => $actionId,
            ];
            $this->dbs->executeStatement($sql, $bindings);
        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * When a actions query is returned with the users assigned to the action, we need to
     * normalize it, to convert the multiple rows returned (one per user) into one action
     * with multiple users attached to it.
     *
     * @param array $records
     * @return array
     */
    protected function normalizeUsersForActions(array $records): array
    {
        $output = [];
        $currentId = 0;
        foreach ($records as $record) {

            $recordId = (int) $record['id'];

            // if the id changed, means new action
            if ($recordId !== $currentId) {

                // remove the channel_name
                unset($record['channel_name']);

                // save the record to the output array
                $output[$recordId] = $record;

                // convert the users value to an array
                $output[$recordId]['users'] = empty($record['users']) ? [] : [$record['users']];

                // update the currentId
                $currentId = $recordId;

            } else {
                // not a new action, on update users on the current output entry
                $output[$recordId]['users'][] = $record['users'];
            }

        }

        return array_map(function ($item) {
            $users = implode(',', $item['users']);
            $item['users'] = "[$users]";
            return $item;
        }, array_values($output));

    }

    /**
     * @param int $parentId
     * @return Action[]
     */
    public function findChildren(int $parentId): array
    {
        $sql = <<<sql
            SELECT a.*, c.channel_name, CASE WHEN ua.user_id IS NOT NULL THEN JSON_OBJECT('user_id',ua.user_id,'action_id',ua.action_id, 'created_on',ua.created_on,'created_by',ua.created_by) ELSE null END AS users
            FROM actions a 
            INNER JOIN channels c ON a.channel_id = c.id
            LEFT JOIN user_actions ua ON ua.action_id = a.id
            WHERE a.parent_id = :id
            ORDER BY a.order_position DESC
sql;

        $rows = $this->normalizeUsersForActions($this->dbs->select($sql, ['id' => $parentId]) ?? []);

        return array_map(function ($row) {
            return Action::withDBData($row);
        }, $rows);

    }

    /**
     * @param int $requestedById
     * @param string $sortBy
     * @param bool $sortDesc
     * @param int|null $channelId
     * @param int|null $userId
     * @param int|null $status
     * @param array|null $searchFilter
     * @param Action|null $cursor
     * @param int|null $limitAfter
     * @param int|null $limitBefore
     * @return Action[]
     */
    public function getActionsPaginated(int $requestedById,
                                         string $sortBy,
                                         bool $sortDesc,
                                         ?int $channelId = null,
                                         ?int $userId = null,
                                         ?int $status = null,
                                         ?array $searchFilter = null,
                                         ?Action $cursor = null,
                                         ?int $limitAfter = null,
                                         ?int $limitBefore = null): array
    {
        // filtering
        $filtersSql = $channelId !== null ? " AND a.channel_id = $channelId" : '';
        if ($userId !== null) {
            $filtersSql.= <<<sql
                AND (
                    ua.user_id = {$userId}
                    OR EXISTS (
                        SELECT 1
                        FROM actions a2
                        INNER JOIN user_actions ua2 ON ua2.action_id = a2.id
                        WHERE a2.parent_id = a.id
                        AND ua2.user_id = {$userId}
                    ) 
                )
sql;
        }

        // for now we only support status filter for incomplete and complete actions
        if ($status === 0) {
            $filtersSql.= " AND (a.action_status = 0 OR k.action_status = 0)";
        }
        if ($status === 1) {
            $filtersSql.= <<<sql
                AND (a.action_status = 1 AND NOT EXISTS (
                    SELECT 1
                    FROM actions
                    WHERE parent_id = a.id
                    AND action_status <> 1
                ))
sql;
        }

        if ($searchFilter !== null) {
            if (empty($searchFilter)) { // no ids on search, so return empty
                return [];
            }
            $searchFilter = implode(',', $searchFilter);
            $filtersSql.= " AND (a.id IN ($searchFilter) OR k.id IN($searchFilter))";
        }

        $mainSql = <<<sql
            SELECT DISTINCT a.*, 
                            c.channel_name, 
                            CASE WHEN ua.user_id IS NOT NULL THEN JSON_OBJECT('user_id',ua.user_id,'action_id',ua.action_id, 'created_on',ua.created_on,'created_by',ua.created_by) ELSE null END AS users,
                            greatest(a.due_on, max(k.due_on)) as due_order_desc,
                            least(a.due_on, min(k.due_on)) as due_order_asc
            FROM actions a 
            INNER JOIN channels c ON a.channel_id = c.id
            INNER JOIN channel_users cu ON cu.channel_id = c.id
            LEFT JOIN user_actions ua ON ua.action_id = a.id
            LEFT JOIN actions k ON k.parent_id = a.id
            WHERE a.parent_id IS NULL
sql;

        // if the logged user is a public user, the public hash logic is already done,
        // and we can trust that ha have access to this channel
        // in any other case, we have to include the requested_by condition on the query
        $bindings = [];
        if ($requestedById !== $this->config->get('/auth/public_user_id') || $channelId === null) {

            $mainSql .= <<<sql
                AND cu.user_id = :requested_by
sql;
            $bindings['requested_by'] = $requestedById;

        }

        $groupSql = " GROUP BY a.id, c.channel_name, ua.user_id";

        if ($cursor === null) {

            $limitAfter = $limitAfter ?? 30; // if the limit is not defined, return 30 items

            // if the cursor is not set, just return the first results, based on limit
            $orderSql = $this->paginationOrderSql($sortBy, $sortDesc);
            $sql = "$mainSql $filtersSql $groupSql $orderSql";
            $output = $this->findPageBySql($sql, $bindings, $limitAfter);

        } else {

            // cursor is set, so we need to handle the infinite pagination

            $baseSql = "$mainSql $filtersSql";

            // find the records before the cursor, if $limitBefore is set
            if ($limitBefore !== null) {
                $beforeOutput = $this->findPageByCursor($cursor, $sortBy, !$sortDesc, $baseSql, $groupSql, $bindings, $limitBefore);
                $beforeOutput = array_reverse($beforeOutput);
            }

            // include the cursor on the output if both limits are set
            if ($limitBefore !== null && $limitAfter !== null) {
                $rows = $this->dbs->select("$baseSql AND a.id = :id", array_merge($bindings, ['id' => $cursor->getId()]));
                $cursorOutput = $this->normalizeUsersForActions($rows);
            }

            // if no limits are defined, set the after limit as 30 by default
            if ($limitBefore === null && $limitAfter == null) {
                $limitAfter = 30;
            }

            if ($limitAfter !== null) {
                $afterOutput = $this->findPageByCursor($cursor, $sortBy, $sortDesc, $baseSql, $groupSql, $bindings, $limitAfter);
            }

            $output = array_merge($beforeOutput ?? [], $cursorOutput ?? [], $afterOutput ?? []);

        }

        return array_map(function ($item) {

            $action = Action::withDBData($item);
            $children = $this->findChildren($action->getId());
            foreach ($children as $child) {
                $action->addChild($child);
            }
            return $action;
        }, array_values($output));

    }

    public function unassignAllActionsOnChannel(int $userId, int $channelId): void
    {
        $sql = <<<sql
            DELETE FROM user_actions
            WHERE user_id = :userId 
              AND action_id IN (
                  SELECT id
                  FROM actions a 
                  WHERE a.channel_id = :channelId
              );
sql;
        $this->dbs->executeStatement($sql, compact('userId', 'channelId'));
    }


}