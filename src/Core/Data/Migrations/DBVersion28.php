<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion28 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add require_join_approval and allow_external_read fields to channel table and create the channel_users_pending table.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does require_join_approval column already exists on channel_users table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channels' 
                   AND COLUMN_NAME = 'require_join_approval';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels ADD COLUMN require_join_approval BOOLEAN DEFAULT TRUE AFTER public_url
sql;
            $dbs->executeStatement($sql);
        }

        // does allow_external_read column already exists on channel_users table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channels' 
                   AND COLUMN_NAME = 'allow_external_read';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels ADD COLUMN allow_external_read BOOLEAN DEFAULT FALSE AFTER require_join_approval
sql;
            $dbs->executeStatement($sql);
        }

        // keep already created public channels public (require_join_approval = false, allow_external_read = true)
        $sql = <<<sql
                   UPDATE channels
                   SET require_join_approval = false, allow_external_read = true
                   WHERE public_hash IS NOT NULL
sql;
        $dbs->executeStatement($sql);

        // does channel_users_pending table already exists on schema?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channel_users_pending';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE channel_users_pending (	
                    channel_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,       
                    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (channel_id, user_id),
                    CONSTRAINT fk_channel_users_pending_channel_id  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
                    CONSTRAINT fk_channel_users_pending_user_id  FOREIGN KEY (user_id) REFERENCES users(id)
                )ENGINE=INNODB
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
