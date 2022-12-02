<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion11 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add the locks table to database";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does locks table already exists on database
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'locks' AND TABLE_SCHEMA = '{$this->database}'";
        if ($dbs->selectOne($sql) === null) {

            // doesn't exists, so add it
            $sql = <<<sql
            CREATE TABLE locks(
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL, /* lock owner */
                created_on DATETIME NOT NULL,
                expiry DATETIME NULL,	
                path VARCHAR(500) NOT NULL, 
                context VARCHAR(32),  
                CONSTRAINT unique_locked_path UNIQUE KEY(path)                 
            )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);

        }


        return true;
    }
}