<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Objects\ExternalIdentity;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class ExternalIdentityDataStore
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
     * create external identity record
     *
     * @param ExternalIdentity $identity
     * @return bool
     * @throws DatabaseException
     */
    public function createExternalIdentity(ExternalIdentity $identity) : bool
    {
        try {
            $sql = "INSERT INTO external_identities
              SET
                external_id         = :external_id,
                provider            = :provider,                
                email               = :email,   
                phone               = :phone,                                             
                display_name        = :display_name,
                user_id             = :user_id,                                    
                created_on          = :created_on";
            $count = $this->dbs->insert($sql, $identity->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get external identity record
     *
     * @param int $userId
     * @param string $email
     * @param int $provider
     * @return ExternalIdentity|null
     * @throws DatabaseException
     */
    public function getExternalIdentity(int $userId, string $email, int $provider) : ?ExternalIdentity
    {
        try {
            $sql = "SELECT * FROM external_identities 
                WHERE user_id = :user_id AND provider = :provider AND email = :email;";
            $record = $this->dbs->selectOne($sql,
                ['user_id' => $userId, 'provider' => $provider, 'email' => $email]);
            return (empty($record) ? null : ExternalIdentity::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get external identities for a user
     *
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getExternalIdentities(int $userId) : iterable
    {
        try {
            $sql = "SELECT * FROM external_identities 
                WHERE user_id = :user_id;";
            return $this->dbs->cursor($sql, ['user_id' => $userId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get total external identities for a user
     *
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    public function getExternalIdentityCount(int $userId) : int
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM external_identities 
                WHERE user_id = :user_id;";
            $record = $this->dbs->selectOne($sql, ['user_id' => $userId]);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getExternalIdentityByEmail(string $email, int $provider): ?ExternalIdentity
    {
        try {
            $sql = "SELECT * FROM external_identities 
                WHERE provider = :provider AND email = :email;";
            $record = $this->dbs->selectOne($sql,
                ['provider' => $provider, 'email' => $email]);
            return (empty($record) ? null : ExternalIdentity::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function updateExternalIdentity(ExternalIdentity $identity)
    {
        try {
            $sql = <<<sql
                UPDATE external_identities
                SET
                    provider            = :provider,                
                    email               = :email,   
                    phone               = :phone,                                             
                    display_name        = :display_name,
                    user_id             = :user_id,                                    
                    created_on          = :created_on
                WHERE external_id = :external_id
sql;
            $count = $this->dbs->update($sql, $identity->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getExternalIdentityById(string $externalIdentifier, int $provider): ?ExternalIdentity
    {
        try {
            $sql = "SELECT * FROM external_identities 
                WHERE external_id = :externalIdentifier AND provider = :provider;";
            $record = $this->dbs->selectOne($sql, compact('externalIdentifier', 'provider'));
            return (empty($record) ? null : ExternalIdentity::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}