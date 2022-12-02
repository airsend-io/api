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

class DBVersion39 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Create calls table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does calls table exists?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'calls';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE calls(
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    channel_id BIGINT UNSIGNED NOT NULL,
                    is_public BOOLEAN DEFAULT FALSE,                    
                    allowed_users VARCHAR(255) NOT NULL,
                    server_address VARCHAR(255) NOT NULL,
                    call_hash VARCHAR(255) NOT NULL
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);

            $sql = "CREATE INDEX idx_call_hash ON calls(call_hash)";
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
