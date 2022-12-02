<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion22 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add locale columns to users and channels";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does locale column already exists on users table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'locale'";
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE users ADD locale VARCHAR(16) DEFAULT NULL AFTER timezone
sql;
            $dbs->executeStatement($sql);
        }

        // does locale column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'locale'";
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels ADD locale VARCHAR(16) DEFAULT NULL AFTER blurb
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}