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

class DBVersion40 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Create the ios_devices table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does fcm_devices table already exists?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ios_devices' AND TABLE_SCHEMA = '{$this->database}';";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, create
            $sql = <<<EOSQL
CREATE TABLE ios_devices(
	device_push_token VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,    
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ios_devices_user_id  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)ENGINE=INNODB;
EOSQL;

            $dbs->executeStatement($sql);
        }
        return true;
    }
}
