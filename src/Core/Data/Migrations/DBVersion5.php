<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion5 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Create the fcm_devices table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does fcm_devices table already exists?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'fcm_devices' AND TABLE_SCHEMA = '{$this->database}';";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, create
            $sql = <<<EOSQL
CREATE TABLE fcm_devices(
	device_id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    client_app VARCHAR(32),
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,    
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fcm_devices_user_id  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)ENGINE=INNODB;
EOSQL;

            $dbs->executeStatement($sql);
        }
        return true;
    }
}