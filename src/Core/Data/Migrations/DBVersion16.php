<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion16 extends AbstractMigration
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

        // does timezone column already exists on users table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'timezone'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE users ADD timezone VARCHAR(255) DEFAULT NULL AFTER is_phone_verified";
            $dbs->executeStatement($sql);
        }

        // does parent_id column already exists on actions table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'actions'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'parent_id'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE actions ADD parent_id BIGINT UNSIGNED DEFAULT NULL AFTER channel_id";
            $dbs->executeStatement($sql);
        }

        // does order column already exists on actions table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'actions' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'order_position'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE actions ADD order_position BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER action_status";
            $dbs->executeStatement($sql);

            // populate the column for already existing actions
            $sql = "SELECT id, channel_id FROM actions ORDER BY channel_id";
            $rows = $dbs->select($sql);
            $currentChannel = null;
            $position = 100;
            $updateSql = "UPDATE actions SET order_position = :position WHERE id = :action_id";
            foreach ($rows as $row) {
                if ($currentChannel === null || $row['channel_id'] !== $currentChannel) {
                    $currentChannel = $row['channel_id'];
                    $position = 100;
                }
                $dbs->executeStatement($updateSql, [
                    'position' => $position,
                    'action_id' => $row['id']
                ]);
                $position += 100;
            }
        }

        // does parent_id foreign key already exists on actions.parent_id?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'actions'  AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'parent_id' and REFERENCED_TABLE_NAME = 'actions';";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE actions ADD CONSTRAINT fk_actions_parent_id  FOREIGN KEY (parent_id) REFERENCES actions(id) ON DELETE CASCADE";
            $dbs->executeStatement($sql);
        }

        return true;
    }
}