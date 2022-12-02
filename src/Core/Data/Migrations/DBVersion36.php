<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion36 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Remove all `favs` groups from channel_groups";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does action_history table exists?
        $sql = <<<sql
                   DELETE FROM channel_groups WHERE `virtual` AND name = 'favs';
sql;

        $dbs->executeStatement($sql);

        return true;
    }
}
