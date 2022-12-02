<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion7 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add the public_hash, public_url, and blurb columns to channels table, and create the short_urls table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does public_hash column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'public_hash'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channels ADD public_hash VARCHAR(32) AFTER has_background";
            $dbs->executeStatement($sql);

        }

        // does public_url column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'public_url'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channels ADD public_url VARCHAR(255) AFTER public_hash";
            $dbs->executeStatement($sql);

        }

        // does blurb column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'blurb'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channels ADD blurb TEXT AFTER channel_email";
            $dbs->executeStatement($sql);

        }

        // does short_urls table already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'short_urls' AND TABLE_SCHEMA = '{$this->database}'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = <<<sql
                CREATE TABLE short_urls (
                    hash varchar(10) PRIMARY KEY,
                    url VARCHAR(255) NOT NULL
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);

        }

        return true;
    }
}