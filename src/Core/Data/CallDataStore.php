<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Call;
use CodeLathe\Core\Objects\Lock;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class CallDataStore
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
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Create Call
     *
     * @param Call $call
     * @return bool
     * @throws DatabaseException
     */
    public function createCall(Call $call) : bool
    {
        try {
            $sql = "INSERT INTO calls
                SET
                  user_id    = :user_id,
                  channel_id     = :channel_id,
                  is_public    = :is_public,
                  call_hash  = :call_hash,
                  server_address = :server_address,
                  allowed_users = :allowed_users;";
            $count = $this->dbs->insert($sql, $call->getArray());
            $call->setId($this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Update Call
     *
     * @param Call $call
     * @return bool
     * @throws DatabaseException
     */
    public function updateCall(Call $call) : bool
    {
        try {
            $sql = "UPDATE calls
                SET
                  user_id    = :user_id,
                  channel_id     = :channel_id,
                  is_public    = :is_public,
                  call_hash  = :call_hash,
                  server_address = :server_address,
                  allowed_users      = :allowed_users
                WHERE
                  id              = :id;";
            $count = $this->dbs->update($sql, $call->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


    /**
     * Get Lock
     *
     * @param string $callHash
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getCallForHash(string $callHash): ?Call
    {
        try {
            $sql = "SELECT * FROM calls
                WHERE call_hash = :call_hash;";
            $record = $this->dbs->selectOne($sql, [
                'call_hash' => $callHash
            ]);
            return empty($record) ? null : Call::withDBData($record);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


    /**
     * @param int $id
     * @return Lock|null
     * @throws DatabaseException
     */
    public function getCallForId(int $id): ?Call
    {
        try {
            $sql = "SELECT * FROM calls
                WHERE id = :id;";
            $record = $this->dbs->selectOne($sql, [
                'id' => $id
            ]);
            return empty($record) ? null : Call::withDBData($record);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


    /**
     * @param int $id
     * @return int
     * @throws DatabaseException
     */
    public function deleteCall(int $id) : void
    {
        try {
            $sql = "DELETE FROM calls where id = :id;";
            $this->dbs->delete($sql, ['id' => $id]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $id
     * @return int
     * @throws DatabaseException
     */
    public function deleteCallUsingHash(string $callHash) : void
    {
        try {
            $sql = "DELETE FROM calls where call_hash = :call_hash;";
            $this->dbs->delete($sql, ['call_hash' => $callHash]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}
