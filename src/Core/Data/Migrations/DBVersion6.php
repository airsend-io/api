<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion6 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Include the source column on messages table.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does from_email column already exists on messages table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'messages' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'source'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE messages ADD source INT DEFAULT 0 AFTER parent_message";
            $dbs->executeStatement($sql);

        }

        return true;
    }
}