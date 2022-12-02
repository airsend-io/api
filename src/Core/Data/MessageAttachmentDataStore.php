<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\ActionHistory;
use CodeLathe\Core\Objects\Mention;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Service\Database\DatabaseService;
use phpDocumentor\Reflection\Types\Iterable_;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class MessageAttachmentDataStore
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
     * MentionDataStore constructor.
     *
     * @param DatabaseService $databaseService
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseService $databaseService, LoggerInterface $logger)
    {
        $this->dbs = $databaseService;
        $this->logger = $logger;
    }

    /**
     * @param MessageAttachment $messageAttachment
     * @return bool
     * @throws DatabaseException
     */
    public function create(MessageAttachment $messageAttachment) : bool
    {
        try {
            $sql = <<<sql
                INSERT INTO message_attachments
                SET                
                    message_id = :message_id,
                    attachment_type = :attachment_type,
                    attachment_key = :attachment_key,
                    content = :content
sql;

            $bindings = $messageAttachment->getArray();
            $bindings['content'] = Json::encode($bindings['content']);
            $count = $this->dbs->insert($sql, $bindings);
            $messageAttachment->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param int $messageId
     * @return MessageAttachment[]
     */
    public function findForMessage(int $messageId): array
    {
        try {
            $sql = <<<sql
                    SELECT *
                    FROM message_attachments
                    WHERE message_id = :message_id
sql;

            $rows = $this->dbs->select($sql, ['message_id' => $messageId]);
            $output = [];
            foreach ($rows as $row) {
                $output[] = MessageAttachment::withDBData($row);
            }
            return $output;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param string $type
     * @param string $key
     * @return Message[]
     * @throws DatabaseException
     */
    public function findMessageForAttachment(string $type, string $key): array
    {
        try {
            $sql = <<<sql
                    SELECT DISTINCT m.*
                    FROM message_attachments ma 
                    INNER JOIN messages m ON ma.message_id = m.id
                    WHERE ma.attachment_type = :attachment_type
                    AND ma.attachment_key = :attachment_key
sql;

            $rows = $this->dbs->select($sql, [
                'attachment_type' => $type,
                'attachment_key' => $key
            ]);
            $output = [];
            foreach ($rows as $row) {
                $output[] = Message::withDBData($row);
            }
            return $output;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function removeByAttachment(string $type, string $key): void
    {
        try {
            $sql = <<<sql
                    DELETE FROM message_attachments
                    WHERE attachment_type = :attachment_type
                    AND attachment_key = :attachment_key;
sql;

            $this->dbs->executeStatement($sql, [
                'attachment_type' => $type,
                'attachment_key' => $key
            ]);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function listLinksForChannel(int $channelId, ?int $cursor = null, ?int $limitAfter = null, ?int $limitBefore = null): ?array
    {

        try {

            // if there is a cursor set, find the created_on date for this
            if ($cursor !== null) {
                $sql = <<<sql
                SELECT ma.*, m.created_on
                FROM message_attachments ma
                INNER JOIN messages m ON ma.message_id = m.id
                WHERE ma.id = :id
sql;
                $result = $this->dbs->selectOne($sql, ['id' => $cursor]);
                if (!$result) {
                    // invalid cursor
                    return [];
                }
                $cursorDate = $result['created_on'];
                $cursorRow = $result;
                unset($cursorRow['created_on']);
            }

            // setup the sql
            $baseSql = <<<sql
                SELECT ma.*
                FROM message_attachments ma
                INNER JOIN messages m ON ma.message_id = m.id
                WHERE ma.attachment_type = :attachment_type
                AND m.channel_id = :channel_id
                AND ma.attachment_key NOT REGEXP '^https://media[0-9]+.giphy.com' -- ignore giphy links
                
sql;

            $orderSql = <<<sql
                ORDER BY m.created_on DESC
sql;
            $limitSql = <<<sql
                LIMIT :limit OFFSET :offset
sql;

            $chunkSize = 30;
            $output = [];

            // grab the before rows
            $beforeRows = [];
            if ($limitBefore !== null) {

                $cursorPassed = false;
                $offset = 0;
                $cursorSql = ' AND m.created_on >= :cursor_date ';
                $orderSql = ' ORDER BY m.created_on ASC ';

                while(true) {
                    $rows = $this->dbs->select($baseSql . $cursorSql . $orderSql . $limitSql, [
                        'attachment_type' => MessageAttachment::ATTACHMENT_TYPE_UNFURL,
                        'channel_id' => $channelId,
                        'cursor_date' => $cursorDate,
                        'limit' => $chunkSize,
                        'offset' => $offset,
                    ]);

                    if (empty($rows)) {
                        break;
                    }

                    foreach ($rows as $row) {
                        if ($row['id'] == $cursor) {
                            $cursorPassed = true;
                            continue;
                        }
                        if ($cursorPassed) {
                            $beforeRows[] = $row;
                            if (count($beforeRows) >= $limitBefore) {
                                break;
                            }
                        }
                    }
                    if (count($beforeRows) >= $limitBefore) {
                        break;
                    }

                    $offset += $chunkSize;
                }
            }
            $beforeRows = array_reverse($beforeRows);
            $output = array_merge($output, $beforeRows);

            // add the cursor row if both limits are defined
            if ($limitBefore !== null && $limitAfter !== null) {
                $output[] = $cursorRow;
            }

            // grab the after rows
            $afterRows = [];
            if ($limitAfter !== null) {

                $orderSql = ' ORDER BY m.created_on DESC ';

                if ($cursor === null) {
                    $afterRows = $this->dbs->select($baseSql . $orderSql . $limitSql, [
                        'attachment_type' => MessageAttachment::ATTACHMENT_TYPE_UNFURL,
                        'channel_id' => $channelId,
                        'limit' => $limitAfter,
                        'offset' => 0,
                    ]);
                } else {

                    $cursorPassed = false;
                    $offset = 0;
                    $cursorSql = ' AND m.created_on <= :cursor_date ';

                    while (true) {
                        $rows = $this->dbs->select($baseSql . $cursorSql . $orderSql . $limitSql, [
                            'attachment_type' => MessageAttachment::ATTACHMENT_TYPE_UNFURL,
                            'channel_id' => $channelId,
                            'cursor_date' => $cursorDate,
                            'limit' => $chunkSize,
                            'offset' => $offset,
                        ]);

                        if (empty($rows)) {
                            break;
                        }

                        foreach ($rows as $row) {
                            if (!$cursorPassed && $row['id'] == $cursor) {
                                $cursorPassed = true;
                                continue;
                            }
                            if ($cursorPassed) {
                                $afterRows[] = $row;
                                if (count($afterRows) >= $limitAfter) {
                                    break;
                                }
                            }
                        }
                        if (count($afterRows) >= $limitAfter) {
                            break;
                        }

                        $offset += $chunkSize;
                    }
                }

            }
            return array_merge($output, $afterRows);

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function removeFromMessage(int $messageId): void
    {
        try {
            $sql = <<<sql
                    DELETE FROM message_attachments
                    WHERE message_id = :messageId;
sql;

            $this->dbs->executeStatement($sql, compact('messageId'));
        } catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

}