<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion12 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add index to notifications.created_on";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does an index already exists on column notifications.created_on
        $sql = "SELECT 1 FROM information_schema.statistics WHERE TABLE_SCHEMA = '{$this->database}' AND table_name = 'notifications' AND column_name = 'created_on'";
        if ($dbs->selectOne($sql) === null) {

            // doesn't exists, so add it
            $sql = "CREATE INDEX idx_notifications_created_on ON notifications(created_on)";
            $dbs->executeStatement($sql);

        }
        
        return true;
    }
}