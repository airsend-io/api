<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;

class DBVersion41 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Removes the unique key on teams.team_name";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        $sql = "SHOW INDEXES FROM {$this->database}.teams WHERE Key_name = 'unique_team_name'";
        $result = $dbs->selectOne($sql);
        if ($result !== null) {
            // drop unique index on team name
            $sql = "ALTER TABLE {$this->database}.teams DROP INDEX unique_team_name;";
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
