<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Objects\Alert;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class AlertDataStore
{

    protected const ALERTS_HISTORY_SIZE = 30;

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
     * Asset DataStore constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Create alert
     *
     * @param Alert $alert
     * @return bool
     * @throws DatabaseException
     */
    protected function createAlert(Alert $alert): bool
    {
        try {
            $sql = "INSERT INTO alerts 
                SET                  
                  user_id       = :user_id,
                  context_id    = :context_id,
                  context_type  = :context_type,
                  alert_text    = :alert_text,
                  alert_type    = :alert_type,
                  is_read       = :is_read,
                  issuers       = :issuers,
                  created_on    = :created_on;";
            $count = $this->dbs->insert($sql, $alert->getArrayWithJsonString());
            $alert->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param Alert $alert
     * @return bool
     * @throws DatabaseException
     */
    protected function updateAlert(Alert $alert): bool
    {
        try {
            $sql = "UPDATE alerts 
                SET                  
                  user_id       = :user_id,
                  context_id    = :context_id,
                  context_type  = :context_type,
                  alert_text    = :alert_text,
                  alert_type    = :alert_type,
                  is_read       = :is_read,
                  issuers       = :issuers,
                  created_on    = :created_on
                WHERE
                  id            = :id;";
            $count = $this->dbs->update($sql, $alert->getArrayWithJsonString());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param Alert $alert
     * @return bool
     * @throws DatabaseException
     */
    public function upsertAlert(Alert $alert) : bool
    {
        if (!empty($alert->getId())) {
            if (!$this->updateAlert($alert)) {
                return false;
            }
        } else {
            $currentAlert = $this->getAlert($alert->getUserId(), $alert->getContextId(), $alert->getContextType(), $alert->getAlertText());
            if (empty($currentAlert)) {
                if (!$this->createAlert($alert)) {
                    return false;
                }
            } else {
                $alert->setId($currentAlert->getId());
                foreach($currentAlert->getIssuers() as $issuer){
                    $alert->addIssuer($issuer);
                }
                if (!$this->updateAlert($alert)) {
                    return false;
                }
            }
        }

        // after any upsert to alert, ensure that the alerts are truncated
        $truncated = $this->truncateAlerts($alert->getUserId());
        $this->logger->debug("Alerts rotate: $truncated alerts was removed.");
        return true;
    }

    /**
     * @param int|null $userId
     * @param int $contextId
     * @param int $contextType
     * @param string $alertText
     * @return Alert|null
     * @throws DatabaseException
     */
    public function getAlert(?int $userId, int $contextId, int $contextType, string $alertText) : ?Alert
    {
        try {
            if ($userId == null) {
                $sql = "SELECT * FROM alerts 
                    where user_id is null 
                        AND context_id = :context_id AND context_type = :context_type AND alert_text = :alert_text;";
                $criteria = ['context_id' => $contextId, 'context_type' => $contextType, 'alert_text' => $alertText];
                $record = $this->dbs->selectOne($sql, $criteria);
                return (empty($record) ? null : Alert::withDBData($record));
            } else {
                $sql = "SELECT * FROM alerts 
                    where user_id = :user_id
                        AND context_id = :context_id AND context_type = :context_type AND alert_text = :alert_text;";
                $criteria = [
                    'context_id' => $contextId,
                    'context_type' => $contextType,
                    'alert_text' => $alertText,
                    'user_id' => $userId
                ];
                $record = $this->dbs->selectOne($sql, $criteria);
                return (empty($record) ? null : Alert::withDBData($record));
            }
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }

    }

    /**
     * @param int $alertId
     * @return Alert|null
     * @throws DatabaseException
     */
    public function getAlertById(int $alertId) : ?Alert
    {
        try {
            $sql = "SELECT * FROM alerts where id = :alert_id;";
            $record = $this->dbs->selectOne($sql, ['alert_id' => $alertId]);
            return (empty($record) ? null : Alert::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }



    /**
     * Get Alerts
     *
     * @param int $userId
     * @return array
     * @throws DatabaseException
     */
    public function getAlerts(int $userId) : iterable
    {
        try {
            $sql = "SELECT * 
                FROM alerts a                        
                where user_id = :user_id or user_id is null
                ORDER BY created_on desc
                LIMIT 15;";
            return $this->dbs->cursor($sql, ['user_id' => $userId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Truncate alerts
     *
     * @param $userId
     * @return int
     * @throws DatabaseException
     */
    protected function truncateAlerts(?int $userId): int
    {
        try {
            $sql = <<<sql
                DELETE FROM alerts 
                WHERE user_id = :user_id
                AND id < (
                    SELECT MIN(t.id) FROM (
                        SELECT id 
                        FROM alerts
                        WHERE user_id = :user_id 
                        ORDER BY id DESC 
                        LIMIT :limit
                    ) t
                );
sql;

            return $this->dbs->delete($sql, ['user_id' => $userId, 'limit' => static::ALERTS_HISTORY_SIZE]);

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $contextId
     * @param int $contextType
     * @return int
     * @throws DatabaseException
     */
    public function deleteAlerts(int $contextId, int $contextType) : int
    {
        try {
            $sql = "DELETE FROM alerts where context_id = :context_id AND context_type = :context_type;";
            return $this->dbs->delete($sql, ['context_id' => $contextId, 'context_type' => $contextType]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int $watermark
     * @return Alert[]
     * @throws DatabaseException
     */
    public function findAllBeforeWatermark(int $channelId, int $userId, int $watermark): array
    {
        try {
            $sql = <<<sql
                SELECT a.*
                FROM alerts a 
                INNER JOIN messages m ON a.context_id = m.id AND a.context_type = :message_context_type
                WHERE a.user_id = :user_id
                AND m.channel_id = :channel_id -- only alerts related to messages of the given channel
                AND a.context_id <= :watermark -- only below watermark
                AND NOT a.is_read
sql;

            $rows = $this->dbs->select($sql, [
                'message_context_type' => Alert::CONTEXT_TYPE_MESSAGE,
                'user_id' => $userId,
                'channel_id' => $channelId,
                'watermark' => $watermark
            ]);

            return array_map(function($row) {
                return Alert::withDBData($row);
            }, $rows);

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }

    }

}