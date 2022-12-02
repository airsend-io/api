<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Timeline;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class TimelineDataStore
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
     * Timeline DataStore constructor.
     *
     * @param ContainerInterface $container
     */

    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Insert into timeline table. If duplicate exists,
     * mysql will update non unique key and not throw error.
     *
     * @param Timeline $timeline
     * @return bool
     * @throws DatabaseException
     */
    public function addTimeline(Timeline $timeline): bool
    {
        try {
            $sql = "INSERT INTO timelines
              SET
                channel_id        = :channel_id,
                user_id           = :user_id,
                message_id        = :message_id, 
                activity          = :activity,              
                created_on        = :created_on
                ON DUPLICATE KEY UPDATE activity = activity;";
            $count = $this->dbs->insert($sql, $timeline->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * Get the last message written on the channel timeline
     *
     * @param int $channelId
     * @return Message|null
     * @throws DatabaseException
     */
    public function getTimelineLastWrittenMessage(int $channelId) : ?Message
    {
        try {
            $sql = "SELECT * from messages 
                WHERE id in (SELECT max(t.message_id)
                            FROM timelines t
                            WHERE t.channel_id = :channel_id AND t.activity = 20);";
            $record = $this->dbs->selectOne($sql, ['channel_id' => $channelId]);
            return (empty($record) ? null : Message::withDBData($record));
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    private function getTotalMessagesWritten(int $channelId, int $userId)
    {
        try {
            $sql = "select count(1) as 'total' from messages 
                where channel_id = :channel_id and user_id = :user_id and message_type in (1,2,3,4);";
            $record = $this->dbs->selectOne($sql, ['channel_id' => $channelId, 'user_id' => $userId]);
            return (empty($record) ? 0 : (int)$record['total']);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}