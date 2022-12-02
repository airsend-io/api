<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion24 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add the muted flag to channel_users table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {


        // does default_joiner_role column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channel_users' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'muted'";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channel_users ADD COLUMN muted BOOLEAN DEFAULT FALSE AFTER notifications_config
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}