<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\ChannelUserPending;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\ServiceRegistryInterface;
use Generator;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class ChannelUserPendingDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    protected $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    protected $logger;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     * @param ServiceRegistryInterface $config
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger, ServiceRegistryInterface $config)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Query to get add user to a channel
     *
     * @param ChannelUserPending $channelUserPending
     * @return void
     * @throws DatabaseException
     */
    public function addChannelUserPending(ChannelUserPending $channelUserPending): void
    {
        try {
            $sql = <<<sql
                    INSERT INTO channel_users_pending
                        SET
                            channel_id        = :channel_id,
                            user_id           = :user_id,
                            created_on        = :created_on
sql;

            $this->dbs->insert($sql, $channelUserPending->getArray());
        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @return User[]
     * @throws DatabaseException
     */
    public function getPendingUsersForChannel(int $channelId): array
    {
        try {
            $sql = <<<sql
                    SELECT u.*
                    FROM channel_users_pending cup
                    INNER JOIN users u ON cup.user_id = u.id
                    WHERE cup.channel_id = :channel_id
sql;

            $rows = $this->dbs->select($sql, ['channel_id' => $channelId]);

            return array_map (
                function ($row) {
                    return User::withDBData($row);
                },
                $rows ?? []
            );

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function exists(int $channelId, int $userId): bool
    {
        try {
            $sql = <<<sql
                    SELECT 1
                    FROM channel_users_pending cup
                    WHERE cup.channel_id = :channel_id
                    AND cup.user_id = :user_id
sql;

            $result = $this->dbs->select($sql, ['channel_id' => $channelId, 'user_id' => $userId]);

            return !empty($result);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function remove(int $channelId, int $userId): void
    {
        try {
            $sql = <<<sql
                    DELETE FROM  channel_users_pending
                    WHERE channel_id = :channel_id
                    AND user_id = :user_id
sql;

            $this->dbs->executeStatement($sql, ['channel_id' => $channelId, 'user_id' => $userId]);

        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}