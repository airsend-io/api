<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;

class DBVersion43 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Creates the structure to allow/block DM channels";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does one_one_approved column exists on channels table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channels'
                   AND COLUMN_NAME = 'one_one_approved';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels 
                    ADD COLUMN one_one_approved BOOLEAN DEFAULT FALSE 
                    AFTER one_one;
sql;
            $dbs->executeStatement($sql);
        }

        // set all existent DM channels which have at least one message from the other side to approved
        $sql = <<<sql
        SELECT DISTINCT c.id
        FROM channels c
        INNER JOIN messages m ON m.channel_id = c.id
        WHERE c.one_one
        AND m.user_id <> c.owned_by
        LIMIT :limit OFFSET :offset;
sql;

        $limit = 100;
        $offset = 0;
        do {
            $rows = $dbs->select($sql, compact('limit', 'offset'));
            foreach ($rows as $row) {
                $channelId = (int)$row['id'];
                $updateSql = <<<sql
                UPDATE channels SET one_one_approved = true WHERE id = :channelId
sql;
                $dbs->executeStatement($updateSql, compact('channelId'));
            }
            $offset += $limit;
        } while (!empty($rows));

        // does blocked_on column exists on channels table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channel_users'
                   AND COLUMN_NAME = 'blocked_on';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channel_users 
                    ADD COLUMN blocked_on DATETIME DEFAULT NULL
                    AFTER muted;
sql;
            $dbs->executeStatement($sql);
        }

        // does spam_reports table exists?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'spam_reports';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE spam_reports (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT UNSIGNED NOT NULL,
                    reported_user_id BIGINT UNSIGNED NOT NULL,
                    is_dm BOOL NOT NULL,
                    reporter_id BIGINT UNSIGNED NOT NULL,
                    reported_at DATETIME NOT NULL,
                    report_message VARCHAR(255) NOT NULL
                )ENGINE = INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
