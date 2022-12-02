<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Log\LoggerInterface;

class ChannelBlacklistDataStore
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
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
    }

    /**
     * @param int $channelId
     * @param string $email
     * @throws DatabaseException
     */
    public function insert(int $channelId, string $email): void
    {
        try {

            $sql = <<<sql
                INSERT INTO channel_blacklist (channel_id, user_email) values (:channel_id, :user_email)
sql;
            $this->dbs->executeStatement($sql, ['channel_id' => $channelId, 'user_email' => $email]);

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function remove(int $channelId, string $email): void
    {
        try {

            $sql = <<<sql
                DELETE FROM channel_blacklist WHERE channel_id = :channel_id AND user_email = :user_email
sql;
            $this->dbs->executeStatement($sql, ['channel_id' => $channelId, 'user_email' => $email]);

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function isBlacklisted(int $channelId, $inviteeCode): bool
    {
        try {

            if (Utility::isValidEmail($inviteeCode)) {
                $sql = <<<sql
                    SELECT 1 FROM channel_blacklist WHERE channel_id = :channel_id AND user_email = :user_email
sql;
                $result = $this->dbs->selectOne($sql, ['channel_id' => $channelId, 'user_email' => $inviteeCode]);
            } else {
                $sql = <<<sql
                    SELECT 1 
                    FROM channel_blacklist cb
                    INNER JOIN users u ON u.email = cb.user_email
                    WHERE cb.channel_id = :channel_id AND u.id = :user_id
sql;
                $result = $this->dbs->selectOne($sql, ['channel_id' => $channelId, 'user_id' => $inviteeCode]);

            }
            return !empty($result);
        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}