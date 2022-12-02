<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;

class DBVersion37 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Migrate attachments from messages table to message_attachments table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does message_attachments table exists?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'message_attachments';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE message_attachments(
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    message_id BIGINT UNSIGNED NOT NULL,
                    attachment_type VARCHAR(255) NOT NULL,
                    attachment_key VARCHAR(255) NOT NULL,
                    content JSON DEFAULT NULL,
                    CONSTRAINT fk_message_attachments_message_id FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
                    CONSTRAINT uq_message_attachments UNIQUE (message_id, attachment_type, attachment_key)
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // find all messages that have attachments, and migrate (by chunks)

        $querySql = <<<sql
            SELECT id, attachments
            FROM messages
            WHERE attachments IS NOT NULL
            LIMIT :limit OFFSET :offset
sql;
        $insertSql = <<<sql
            INSERT INTO message_attachments (message_id, attachment_type, attachment_key, content)
            VALUES(:message_id, :attachment_type, :attachment_key, :content);
sql;

        $possibleAttachmentTypes = ["ATTACHMENT_TYPE_FILE", "ATTACHMENT_TYPE_UNFURL"];

        $chunkSize = 50;
        $offset = 0;

        while (true) {
            $rows = $dbs->select($querySql, ['limit' => $chunkSize, 'offset' => $offset]);
            if (empty($rows)) {
                break;
            }
            foreach ($rows as $row) {
                $attachments = \json_decode($row['attachments'], true);
                if (empty($attachments)) {
                    // no attachments on message, skip it
                    continue;
                }
                
                // go though all attachments
                foreach ($attachments as $i => $attachment) {
                    if (!isset($attachment['ctp']) || !in_array($attachment['ctp'], $possibleAttachmentTypes) || !isset($attachment['content'])) {
                        // not a valid attachment, so skip
                        continue;
                    }

                    $type = $attachment['ctp'];
                    $content = \json_decode($attachment['content'], true);

                    $key = $content[$type === 'ATTACHMENT_TYPE_FILE' ? 'path' : 'url'] ?? null;

                    if ($key === null) {
                        // invalid, skip
                        continue;
                    }

                    // if the attachment is a file, and it doesn't exists on the storage, don't migrate it
                    if ($type === MessageAttachment::ATTACHMENT_TYPE_FILE && !$this->pathExists($content['path'])) {

                        // skip
                        continue;
                    }

                    $bindings = [
                        'message_id' => $row['id'],
                        'attachment_type' => $type,
                        'attachment_key' => $key,
                        'content' => \json_encode($content),
                    ];

                    try {
                        $dbs->executeStatement($insertSql, $bindings);
                    } catch (\Throwable $e) {
                        continue; // just ignore errors on this (duplicated key, etc)
                    }
                }

            }
            $offset += $chunkSize;
        }

        return true;
    }

    protected function pathExists(string $path): bool
    {

        // translate the path
        /** @var FileOperations $fops */
        $fops = ContainerFacade::get(FileOperations::class);
        $translatedPath = $fops->translatePath($path);

        // query fs database for the file...
        if (!preg_match('/^\/f(\/.+attachments)\/(.+)$/', trim($translatedPath->getPrefixedPath()), $matches)) {
            return false; // the path is invalid, so don't migrate it
        }
        $parentPath = $matches[1];
        $fileName = $matches[2];
        $sql = <<<sql
            SELECT 1 
            FROM items 
            WHERE type = 'file'
            AND parentpath = :parentpath
            AND name = :name
sql;

        /** @var FSDatabaseService $fsdbs */
        $fsdbs = ContainerFacade::get(FSDatabaseService::class);

        return $fsdbs->selectOne($sql, ['parentpath' => $parentPath, 'name' => $fileName]) !== null;

    }
}
