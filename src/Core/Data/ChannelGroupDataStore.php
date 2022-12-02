<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\ChannelGroup;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Log\LoggerInterface;

class ChannelGroupDataStore
{

    /**
     * @var DatabaseService|mixed
     */
    protected $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    protected $logger;

    public function __construct(DatabaseService $dbs, LoggerInterface $logger)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
    }

    public function createChannelGroup(string $name, int $userId, bool $virtual = false): ChannelGroup
    {

        try {

            // find the biggest order_score
            $sql = <<<sql
                SELECT MAX(order_score) AS max
                FROM channel_groups
                WHERE user_id = :user_id
sql;
            $result = $this->dbs->selectOne($sql, ['user_id' => $userId]);

            // set the new order score
            $max = (int)($result['max'] ?? 0);
            $orderScore = $max + 100;

            // insert the registry
            $sql = <<<sql
                INSERT INTO channel_groups(user_id, name, order_score, `virtual`)
                VALUES (:user_id, :name, :order_score, :virtual);
sql;
            $this->dbs->executeStatement($sql, [
                'user_id' => $userId,
                'name' => $name,
                'order_score' => $orderScore,
                'virtual' => $virtual
            ]);

            // get the inserted registry
            $id = $this->dbs->lastInsertId();
            $sql = "SELECT * FROM channel_groups WHERE id = :id";
            $row = $this->dbs->selectOne($sql, ['id' => $id]);
            $channelGroup = ChannelGroup::withDBData($row);

            // if it's a virtual group, put it on the last position
            if ($virtual) {
                $sql = <<<sql
                    SELECT id
                    FROM channel_groups
                    WHERE user_id = :user_id
                    ORDER BY order_score
                    LIMIT 1
sql;

                $result = $this->dbs->selectOne($sql, ['user_id' => $userId]);
                $this->move($userId, $channelGroup->getId(), (int)$result['id']);
            }

            return $channelGroup;

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }



    public function addChannelToGroup(int $channelId, int $userId, int $groupId)
    {
        try {

             $sql = <<<sql
                UPDATE channel_users
                SET group_id = :group_id
                WHERE channel_id = :channel_id
                AND user_id = :user_id;
sql;
             $this->dbs->executeStatement($sql, [
                 'group_id' => $groupId,
                 'channel_id' => $channelId,
                 'user_id' => $userId,
             ]);


        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function findForUser(int $userId)
    {
        try {

            $sql = <<<sql
                SELECT *
                FROM channel_groups
                WHERE user_id = :user_id
                ORDER BY order_score DESC
sql;
            $rows = $this->dbs->select($sql, [
                'user_id' => $userId
            ]);

            return array_map(function ($row) {
                return ChannelGroup::withDBData($row);
            }, $rows);


        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function exists(int $groupId)
    {
        try {

            $sql = "SELECT 1 FROM channel_groups WHERE id = :id";

            return $this->dbs->selectOne($sql, ['id' => $groupId]) !== null;

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function findVirtualGroupId(int $userId, string $virtualKey): ?int
    {
        try {

            $sql = "SELECT id FROM channel_groups WHERE user_id = :user_id AND `virtual` AND `name` = :virtual_key";

            $result = $this->dbs->selectOne($sql, ['user_id' => $userId, 'virtual_key' => $virtualKey]);
            return $result === null ? null : ((int) $result['id']);

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function move(int $userId, int $groupId, int $after)
    {
        try {

            // first find the order_score from after
            if ($after > 0) {
                $sql = <<<sql
                    SELECT order_score
                    FROM channel_groups
                    WHERE id = :id
                    AND user_id = :user_id;
sql;
                $row = $this->dbs->selectOne($sql, ['id' => $after, 'user_id' => $userId]);
                if ($row === null) { // after not found, do nothing (impossible)
                    return;
                }
                $upperLimit = (int)$row['order_score'];

                $sql = <<<sql
                    SELECT order_score
                    FROM channel_groups
                    WHERE user_id = :user_id
                    AND order_score < :limit
                    ORDER BY order_score DESC
                    LIMIT 1
sql;

                $row = $this->dbs->selectOne($sql, ['user_id' => $userId, 'limit' => $upperLimit]);
                $lowerLimit = $row === null ? 0 : (int)$row['order_score'];

                $orderScore = ((int)ceil(($upperLimit - $lowerLimit) / 2)) + $lowerLimit;

                if ($orderScore >= $upperLimit) {
                    $sql = <<<sql
                        SELECT id 
                        FROM channel_groups 
                        WHERE user_id = :user_id
                        AND order_score >= :order_score
                        ORDER BY order_score;
sql;
                    $rows = $this->dbs->select($sql, ['user_id' => $userId, 'order_score' => $orderScore]);
                    $updateSql = "UPDATE channel_groups SET order_score = :score WHERE id = :id";
                    $score = $orderScore;
                    foreach ($rows as $row) {
                        $score = $score + 100;
                        $this->dbs->update($updateSql, ['score' => $score, 'id' => $row['id']]);
                    }
                }

            } else {
                $sql = "SELECT MAX(order_score) AS max FROM channel_groups WHERE user_id = :user_id;";
                $row = $this->dbs->selectOne($sql, ['user_id' => $userId]);
                $orderScore = ($row['max'] ?? 0) + 100;
            }

            $sql = "UPDATE channel_groups SET order_score = :order_score WHERE id = :id AND user_id = :user_id";
            $this->dbs->update($sql, ['order_score' => $orderScore, 'id' => $groupId, 'user_id' => $userId]);

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function findById(int $groupId): ?ChannelGroup
    {
        try {

            $sql = "SELECT * FROM channel_groups WHERE id = :id";
            $row = $this->dbs->selectOne($sql, ['id' => $groupId]);
            return $row === null ? null : ChannelGroup::withDBData($row);

        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function removeChannelFromGroup(int $channelId, int $userId): void
    {
        try {

            $sql = <<<sql
                UPDATE channel_users
                SET group_id = null
                WHERE channel_id = :channel_id
                AND user_id = :user_id;
sql;
            $this->dbs->executeStatement($sql, [
                'channel_id' => $channelId,
                'user_id' => $userId,
            ]);


        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function delete(int $groupId): void
    {
        try {

            $sql = <<<sql
                DELETE FROM channel_groups
                WHERE id = :id;
sql;
            $this->dbs->executeStatement($sql, [
                'id' => $groupId,
            ]);


        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function update(int $groupId, string $name): ChannelGroup
    {
        try {

            $sql = <<<sql
                UPDATE channel_groups
                SET name = :name
                WHERE id = :id;
sql;
            $this->dbs->executeStatement($sql, [
                'id' => $groupId,
                'name' => $name,
            ]);

            return $this->findById($groupId);


        } catch(\PDOException $e) {
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }

    }
}