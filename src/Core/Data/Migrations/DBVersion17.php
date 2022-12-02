<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion17 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Include timezone column to users table and parent_id/order columns to actions";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does one_one column already exists on users table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'one_one'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channels ADD one_one BOOLEAN DEFAULT FALSE AFTER contact_form_filler_id";
            $dbs->executeStatement($sql);
        }

        return true;
    }
}