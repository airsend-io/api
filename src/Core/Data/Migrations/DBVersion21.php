<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion21 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Fix channel_blacklist foreign key.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        $sql1 = "ALTER TABLE channel_blacklist DROP FOREIGN KEY fk_channel_blacklist_channels;";
        $sql2 = "ALTER TABLE channel_blacklist ADD CONSTRAINT fk_channel_blacklist_channels FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE";

        if (!$dbs->executeStatement($sql1)) {
            return false;
        }

        if (!$dbs->executeStatement($sql2)) {
            return false;
        }

        return true;
    }
}