<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion8 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add is_deleted to messages table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does is_deleted column already exists on messages table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'messages' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'is_deleted'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE messages ADD is_deleted BOOLEAN DEFAULT 0 AFTER is_edited";
            $dbs->executeStatement($sql);

        }

        return true;
    }
}