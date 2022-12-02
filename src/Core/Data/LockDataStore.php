<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Lock;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class LockDataStore
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
     * Create Lock
     *
     * @param Lock $lock
     * @return bool
     * @throws DatabaseException
     */
    public function createLock(Lock $lock) : bool
    {
        try {
            $sql = "INSERT INTO locks
                SET
                  path      = :path,
                  user_id    = :user_id,
                  context     = :context,
                  created_on    = :created_on,
                  expiry      = :expiry;";
            $count = $this->dbs->insert($sql, $lock->getArray());
            $lock->setId($this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Update Lock
     *
     * @param Lock $lock
     * @return bool
     * @throws DatabaseException
     */
    public function updateLock(Lock $lock) : bool
    {
        try {
            $sql = "UPDATE locks
                SET
                  path      = :path,
                  user_id    = :user_id,
                  context     = :context,
                  created_on  = :created_on,
                  expiry      = :expiry
                WHERE
                  id              = :id;";
            $count = $this->dbs->update($sql, $lock->getArray());
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
     * @param string $path
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getLock(string $path): ?Lock
    {
        try {
            $sql = "SELECT * FROM locks
                WHERE path = :path;";
            $record = $this->dbs->selectOne($sql, [
                'path' => $path,
            ]);
            return empty($record) ? null : Lock::withDBData($record);
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
    public function getLockForId(int $id): ?Lock
    {
        try {
            $sql = "SELECT * FROM locks
                WHERE id = :id;";
            $record = $this->dbs->selectOne($sql, [
                'id' => $id
            ]);
            return empty($record) ? null : Lock::withDBData($record);
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
    public function deleteLock(int $id) : void
    {
        try {
            $sql = "DELETE FROM locks where id = :id;";
            $this->dbs->delete($sql, ['id' => $id]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return void
     * @throws DatabaseException
     */
    public function clearExpiredLocks() : void
    {
        try {
            $sql = "DELETE FROM locks where expiry < NOW();";
            $this->dbs->delete($sql);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


}