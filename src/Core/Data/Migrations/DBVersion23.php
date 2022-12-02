<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion23 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add default role for invitees and joiners";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {


        // does default_joiner_role column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'default_joiner_role'";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels ADD default_joiner_role INTEGER DEFAULT NULL AFTER locale
sql;
            $dbs->executeStatement($sql);
        }

        // does default_invitee_role column already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'default_invitee_role'";
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels ADD default_invitee_role INTEGER DEFAULT NULL AFTER default_joiner_role
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}