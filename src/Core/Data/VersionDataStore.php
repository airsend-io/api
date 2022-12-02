<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Version;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class VersionDataStore
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
     * @param Version $version
     * @return bool
     * @throws DatabaseException
     */
    public function createVersion(Version $version)
    {
        try {
            $sql = "INSERT INTO versions
                SET 
                  notes         = :notes,
                  created_on    = :created_on;";
            $count = $this->dbs->insert($sql, $version->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return Version
     * @throws DatabaseException
     */
    public function getLatestVersion() : Version
    {
        try {
            $sql = "SELECT * FROM versions ORDER BY id DESC LIMIT 1;";
            $record = $this->dbs->selectOne($sql, []);
            return (empty($record) ? null : Version::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @return array
     * @throws DatabaseException
     */
    public function getVersions() : array
    {
        try {
            $sql = "SELECT * FROM versions ORDER BY id DESC;";
            return $this->dbs->select($sql, []);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}