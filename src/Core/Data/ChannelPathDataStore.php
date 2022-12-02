<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use CodeLathe\Core\Objects\ChannelPath;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class ChannelPathDataStore
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
     * Query Statement to create channel path
     *
     * @param ChannelPath $channelPath
     * @return bool
     * @throws DatabaseException
     */
    public function createChannelPath(ChannelPath $channelPath) : bool
    {
        try {
            $sql = "INSERT INTO channel_paths
              SET                
                channel_id          = :channel_id,
                path_type           = :path_type,
                path_value          = :path_value,
                created_on          = :created_on,
                created_by          = :created_by";
            $count = $this->dbs->insert($sql, $channelPath->getArray());
            $channelPath->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * update channel Path
     *
     * @param ChannelPath $channelPath
     * @return bool
     * @throws DatabaseException
     */
    public function updateChannelPath(ChannelPath $channelPath) : bool
    {
        try {
            $sql = "UPDATE channel_paths
              SET                
                channel_id          = :channel_id,
                path_type           = :path_type,
                path_value          = :path_value,
                created_on          = :created_on,
                created_by          = :created_by
               WHERE
                id                  = :id";
            $count = $this->dbs->update($sql, $channelPath->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * get channel path by Id
     *
     * @param int $channelPathId
     * @return ChannelPath|null
     * @throws DatabaseException
     */
    public function getChannelPathById(int $channelPathId) :?ChannelPath
    {
        try {
            $sql = "SELECT * from channel_paths where id = :id";
            $record = $this->dbs->selectOne($sql, ['id' => $channelPathId]);
            return (empty($record) ? null : ChannelPath::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * delete channel path by Id
     *
     * @param int $channelPathId
     * @return int     *
     * @throws DatabaseException
     */
    public function deleteChannelPathById(int $channelPathId) : int
    {
        try {
            $sql = "DELETE from channel_paths where id = :id";
            return $this->dbs->delete($sql, ['id' => $channelPathId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * select channel paths
     *
     * @param int $channelId
     * @return array
     * @throws DatabaseException
     */
    public function getChannelPathsByChannelId(int $channelId) : array
    {
        try {
            $sql = "SELECT * from channel_paths where channel_id = :channel_id";
            return $this->dbs->select($sql, ['channel_id' => $channelId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * delete channel paths
     *
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function deleteChannelPathsByChannelId(int $channelId) : int
    {
        try {
            $sql = "DELETE from channel_paths where channel_id = :channel_id";
            return $this->dbs->delete($sql, ['channel_id' => $channelId]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get Channel Paths by value
     *
     * @param string $pathValue
     * @return iterable
     * @throws DatabaseException
     */
    public function getChannelPathsByValue(string $pathValue) : iterable
    {
        try {
            $sql = <<<SQL
                SELECT * 
                FROM channel_paths
                WHERE path_value LIKE :path_value1 OR path_value = :path_value2;
SQL;

            return $this->dbs->cursor($sql, [
                'path_value1' => "$pathValue/%",
                'path_value2' => "$pathValue"
            ]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getChannelPath(int $channelId, int $type): ?ChannelPath
    {
        try {
            $sql = <<<SQL
                SELECT * 
                FROM channel_paths
                WHERE channel_id = :channelId
                    AND path_type = :type
SQL;

            $row = $this->dbs->selectOne($sql, compact('channelId', 'type'));
            return $row === null ? null : ChannelPath::withDBData($row);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}