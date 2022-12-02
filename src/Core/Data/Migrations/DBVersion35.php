<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion35 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Creates the action_history table.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does action_history table exists?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'action_history';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE action_history(
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    action_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    history_type VARCHAR(255) NOT NULL,
                    attachments JSON DEFAULT NULL,
                    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_channel_history_action_id FOREIGN KEY (action_id) REFERENCES actions(id) ON DELETE CASCADE,
                    CONSTRAINT fk_channel_history_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
