<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class PolicyDataStore
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
     * Create Policy
     *
     * @param Policy $policy
     * @return bool
     * @throws DatabaseException
     */
    public function createPolicy(Policy $policy) : bool
    {
        try {
            $sql = "INSERT INTO policies
                SET
                  context_id      = :context_id,
                  context_type    = :context_type,
                  policy_name     = :policy_name,
                  policy_value    = :policy_value,
                  updated_on      = :updated_on;";
            $count = $this->dbs->insert($sql, $policy->getArray());
            $policy->setId($this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Update Policy
     *
     * @param Policy $policy
     * @return bool
     * @throws DatabaseException
     */
    public function updatePolicy(Policy $policy) : bool
    {
        try {
            $sql = "UPDATE policies
                SET
                  context_id      = :context_id,
                  context_type    = :context_type,
                  policy_name     = :policy_name,
                  policy_value    = :policy_value,
                  updated_on      = :updated_on
                WHERE
                  id              = :id;";
            $count = $this->dbs->update($sql, $policy->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Delete Policy
     *
     * @param Policy $policy
     * @return bool
     * @throws DatabaseException
     */
    public function deletePolicy(Policy $policy) : bool
    {
        try {
            $sql = "DELETE FROM policies
                WHERE id = :id;";
            $count = $this->dbs->delete($sql, ['id' => $policy->getId()]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get Policy
     *
     * @param int $contextId
     * @param int $contextType
     * @param string $policyName
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getPolicy(int $contextId, int $contextType, string $policyName): ?Policy
    {
        try {
            $sql = "SELECT * FROM policies
                WHERE context_id = :context_id AND context_type = :context_type AND policy_name = :policy_name;";
            $record = $this->dbs->selectOne($sql, [
                'context_id' => $contextId,
                'context_type' => $contextType,
                'policy_name' => $policyName
            ]);
            return empty($record) ? null : Policy::withDBData($record);
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
    public function deletePolicies(int $contextId, int $contextType) : int
    {
        try {
            $sql = "DELETE FROM policies where context_id = :context_id AND context_type = :context_type;";
            return $this->dbs->delete($sql, ['context_id' => $contextId, 'context_type' => $contextType]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}