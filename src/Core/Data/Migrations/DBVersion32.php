<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion32 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Include channel_groups table, and channel_group column on the channels table.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does channel_groups table exists?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channel_groups';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE channel_groups(
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    name VARCHAR(255) DEFAULT NULL,
                    `virtual` BOOLEAN DEFAULT FALSE,
                    order_score BIGINT UNSIGNED NOT NULL,
                    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_channel_groups_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE 
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // does group_id column exists on channel_users table
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channel_users'
                   AND COLUMN_NAME = 'group_id';
sql;
        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channel_users 
                    ADD COLUMN group_id BIGINT UNSIGNED DEFAULT NULL 
                    AFTER read_watermark_id;
sql;
            $dbs->executeStatement($sql);

            $sql = <<<sql
                ALTER TABLE channel_users 
                    ADD CONSTRAINT fk_channel_users_group_id 
                    FOREIGN KEY (group_id) REFERENCES channel_groups(id) ON DELETE SET NULL;
sql;
            $dbs->executeStatement($sql);


        }

        return true;
    }
}
