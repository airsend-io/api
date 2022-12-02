<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion34 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Creates the mentions table.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does mentions table exists?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'mentions';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE mentions(
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    message_id BIGINT UNSIGNED NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    resource_type VARCHAR(255) NOT NULL,
                    resource_id BIGINT NOT NULL,
                    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_mentions_message_id FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);

            $sql = "CREATE INDEX idx_mentions_resource_id ON mentions(resource_id)";
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
