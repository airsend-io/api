<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion15 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Update the current channel users roles";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // All current users that have role 20 (collaborator) and the membership is not for a public channel,
        // will be upgraded to 30 (collaborator + wiki)
        $sql = <<<sql
            update channel_users
            inner join channels on channels.id = channel_users.channel_id
            set channel_users.user_role = 30
            where channel_users.user_role = 20 and channels.public_url is null;
sql;
        $dbs->executeStatement($sql);
        return true;
    }
}