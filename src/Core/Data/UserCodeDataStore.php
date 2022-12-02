<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\UserCode;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class UserCodeDataStore
{
    /**
     * Declare database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    private $logger;

    /**
     * UserCode DataStore constructor.
     *
     * @param ContainerInterface $container     *
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Query to insert user code
     *
     * @param UserCode $code
     * @return bool
     * @throws DatabaseException
     */
    public function createUserCode(UserCode $code) : bool
    {
        try {
            $sql = "INSERT INTO user_codes
              SET
                user_id                  = :user_id,                
                code_type                = :code_type,
                code                     = :code,
                expires                  = :expires,                                
                created_on               = :created_on;";
            $count = $this->dbs->insert($sql, $code->getArray());
            $code->setId((int)$this->dbs->lastInsertId());
            return $count == 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to update UserCode
     *
     * @param UserCode $code
     * @return bool
     * @throws DatabaseException
     */
    public function updateUserCode(UserCode $code) : bool
    {
        try {
            $sql = "UPDATE user_codes
              SET
                user_id                  = :user_id,                
                code_type                = :code_type,
                code                     = :code,
                expires                  = :expires,                                
                created_on               = :created_on
              WHERE id                   = :id;";
            $count = $this->dbs->update($sql, $code->getArray());
            return $count == 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Query to get the user code
     *
     * @param int $userId
     * @param int $codeType
     * @return UserCode|null
     * @throws DatabaseException
     */
    public function getUserCode(int $userId, int $codeType) : ?UserCode
    {
        try {
            $sql = "SELECT * FROM user_codes where user_id = :user_id AND code_type = :code_type";
            $record = $this->dbs->selectOne($sql, ['user_id' => $userId, 'code_type' => $codeType]);
            return (empty($record) ? null : UserCode::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @param int $codeType
     * @return bool
     * @throws DatabaseException
     */
    public function deleteUserCode(int $userId, int $codeType) : bool
    {
        try {
            $sql = "DELETE FROM user_codes where user_id = :user_id AND code_type = :code_type";
            $count = $this->dbs->delete($sql, ['user_id' => $userId, 'code_type' => $codeType]);
            return $count == 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}