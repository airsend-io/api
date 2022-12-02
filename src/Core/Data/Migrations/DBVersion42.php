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

class DBVersion42 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Creates the open_team_join column on channels table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does open_team_join column exists on channels table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'channels'
                   AND COLUMN_NAME = 'open_team_join';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE channels 
                    ADD COLUMN open_team_join BOOLEAN DEFAULT FALSE 
                    AFTER allow_external_read;
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
