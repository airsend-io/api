<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion20 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Rename created_by column to owned_by on table channels";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does one_one column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'owned_by'";
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels CHANGE created_by owned_by BIGINT UNSIGNED NOT NULL;
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}