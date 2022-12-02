<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion30 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Include the status columns on the users table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does user_status column already exists on users table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'user_status';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE users ADD COLUMN user_status INTEGER DEFAULT NULL AFTER lang_code
sql;
            $dbs->executeStatement($sql);
        }

        // does user_status column already exists on users table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'user_status_message';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE users ADD COLUMN user_status_message VARCHAR(255) DEFAULT null AFTER user_status
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
