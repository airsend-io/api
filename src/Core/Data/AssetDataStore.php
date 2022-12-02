<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\Asset;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class AssetDataStore
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
     * Create Asset
     *
     * @param Asset $asset
     * @return bool
     * @throws DatabaseException
     */
    public function createAsset(Asset $asset): bool
    {
        try {
            $sql = "INSERT INTO assets
              SET                
                context_id          = :context_id,                
                context_type        = :context_type,
                asset_type          = :asset_type,
                attribute           = :attribute,
                mime                = :mime,
                asset_data          = :asset_data,                                
                created_on          = :created_on,
                created_by          = :created_by;";
            $count = $this->dbs->insert($sql, $asset->getArray());
            $asset->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


    /**
     * Update Asset
     *
     * @param Asset $asset
     * @return bool
     * @throws DatabaseException
     */
    public function updateAsset(Asset $asset) : bool
    {
        try {
            $sql = "UPDATE assets
              SET                
                context_id          = :context_id,                
                context_type        = :context_type,
                asset_type          = :asset_type,
                attribute           = :attribute,
                mime                = :mime,
                asset_data          = :asset_data,                              
                created_on          = :created_on,
                created_by          = :created_by
              WHERE
                id                  = :id;";
            $count = $this->dbs->update($sql, $asset->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Delete Asset
     *
     * @param int $assetId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteAsset(int $assetId): bool
    {
        try {
            $sql = "DELETE FROM assets where id = :id;";
            $count = $this->dbs->delete($sql, ['id' => $assetId]);
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


    /**
     * Get Asset
     *
     * @param int $contextId
     * @param int $contextType
     * @param int $assetType
     * @param int $attribute
     * @return Asset
     * @throws DatabaseException
     */
    public function getAsset(int $contextId, int $contextType, int $assetType, int $attribute): ?Asset
    {
        try {
            $sql = "SELECT * FROM assets 
                WHERE context_id    = :context_id
                AND   context_type  = :context_type
                AND   asset_type    = :asset_type
                AND   attribute     = :attribute;";
            $record = $this->dbs->selectOne($sql, [
                'context_id' => $contextId,
                'context_type' => $contextType,
                'asset_type' => $assetType,
                'attribute' => $attribute
            ]);
            return (empty($record) ? null : Asset::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }


    /**
     * Get Asset By Asset Id
     *
     * @param int $assetId
     * @return Asset
     * @throws DatabaseException
     */
    public function getAssetById(int $assetId): ?Asset
    {
        try {
            $sql = "SELECT * FROM assets 
                WHERE id    = :id;";
            $record = $this->dbs->selectOne($sql, ['id' => $assetId]);
            return (empty($record) ? null : Asset::withDBData($record));
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
    public function deleteAssets(int $contextId, int $contextType) : int
    {
        try {
            $sql = "DELETE FROM assets where context_id = :context_id AND context_type = :context_type;";
            return $this->dbs->delete($sql, ['context_id' => $contextId, 'context_type' => $contextType]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function hasAsset(int $contextId, int $contextType, int $assetType): bool
    {
        try {
            $sql = "SELECT * FROM assets 
                WHERE context_id    = :context_id
                AND   context_type  = :context_type
                AND   asset_type    = :asset_type;";
            $record = $this->dbs->selectOne($sql, [
                'context_id' => $contextId,
                'context_type' => $contextType,
                'asset_type' => $assetType
            ]);
            return (empty($record)) ? false : true;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}
