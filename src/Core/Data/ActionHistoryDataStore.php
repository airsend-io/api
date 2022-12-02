<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\ActionHistory;
use CodeLathe\Core\Objects\Mention;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Service\Database\DatabaseService;
use phpDocumentor\Reflection\Types\Iterable_;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ActionHistoryDataStore
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
     * MentionDataStore constructor.
     *
     * @param DatabaseService $databaseService
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseService $databaseService, LoggerInterface $logger)
    {
        $this->dbs = $databaseService;
        $this->logger = $logger;
    }

    /**
     * @param ActionHistory $actionHistory
     * @return bool
     * @throws DatabaseException
     */
    public function create(ActionHistory $actionHistory) : bool
    {
        try {
            $sql = "INSERT INTO action_history
              SET                
                action_id = :action_id,   
                user_id = :user_id,   
                history_type = :history_type,
                attachments = :attachments";

            $bindings = $actionHistory->getArray();
            $bindings['attachments'] = \GuzzleHttp\json_encode($bindings['attachments']);
            $count = $this->dbs->insert($sql, $bindings);
            $actionHistory->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $actionId
     * @return ActionHistory[]
     * @throws DatabaseException
     */
    public function getHistoryForAction(int $actionId): array
    {
        try {
            $sql = <<<sql
                SELECT *
                FROM action_history
                WHERE action_id = :action_id
                ORDER BY created_on ASC, id ASC
sql;

            $rows = $this->dbs->select($sql, ['action_id' => $actionId]);
            return array_map(function ($row) {
                return ActionHistory::withDBData($row);
            }, $rows);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function isActionMentioned(int $actionId, int $messageId): bool
    {
        try {
            $sql = <<<sql
                SELECT 1
                FROM action_history
                WHERE action_id = :action_id
                AND history_type = 'mentioned'
                AND attachments->'$.message_id' = :message_id;
sql;

            return $this->dbs->selectOne($sql, ['action_id' => $actionId, 'message_id' => $messageId]) !== null;

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}