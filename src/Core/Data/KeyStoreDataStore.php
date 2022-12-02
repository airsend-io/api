<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Objects\KeyStore;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class KeyStoreDataStore
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
     * KeyStore constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * create key store
     *
     * @param KeyStore $keystore
     * @return bool
     * @throws DatabaseException
     */
    public function createKeyStore(KeyStore $keystore) : bool
    {
        try {
            $sql = "INSERT INTO keystore
              SET                
                `key`     = :key,
                `value`   = :value;";
            $count = $this->dbs->insert($sql, $keystore->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * delete key store
     *
     * @param string $key
     * @return bool
     * @throws DatabaseException
     */
    public function deleteKeyStore(string $key) : bool
    {
        try {
            $sql = "DELETE FROM keystore where `key` = :key;";
            $count = $this->dbs->delete($sql, ['key' => $key]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * update key store
     *
     * @param KeyStore $keystore
     * @return bool
     * @throws DatabaseException
     */
    public function updateKeyStore(KeyStore $keystore) : bool
    {
        try {
            $sql = "update keystore
              SET               
                `value`   = :value
              WHERE 
                `key`     = :key;";
            $count = $this->dbs->update($sql, $keystore->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get key store
     *
     * @param string $key
     * @return KeyStore|null
     * @throws DatabaseException
     */
    public function getKeyStore(string $key) : ?KeyStore
    {
        try {
            $sql = "SELECT * FROM keystore where `key` = :key;";
            $record = $this->dbs->selectOne($sql, ['key' => $key]);
            return (empty($record) ? null : KeyStore::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}