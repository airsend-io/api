<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion19 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Creates table channel_blacklist";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does channel_blacklist table already exists on database
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'channel_blacklist' AND TABLE_SCHEMA = '{$this->database}'";
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE channel_blacklist (
                    id 	BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT UNSIGNED NOT NULL,
                    user_email VARCHAR(255) NULL,
                    CONSTRAINT fk_channel_blacklist_channels FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
                )ENGINE = INNODB;
sql;


            $dbs->executeStatement($sql);
        }

        return true;
    }
}