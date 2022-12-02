<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion13 extends AbstractMigration
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
        $sql = "SELECT 1 FROM information_schema.statistics WHERE TABLE_SCHEMA = '{$this->database}' AND table_name = 'timelines' AND index_name = 'idx_timelines_user_id_message_id'";
        if ($dbs->selectOne($sql) === null) {

            // doesn't exists, so add it
            $sql = "CREATE INDEX idx_timelines_user_id_message_id ON timelines(user_id, message_id)";
            $dbs->executeStatement($sql);

        }
        
        return true;
    }
}