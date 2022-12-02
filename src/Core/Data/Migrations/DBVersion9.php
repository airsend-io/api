<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion9 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add the contact_forms table to database and contact_form_id to channels table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does contact_forms table already exists on database
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'contact_forms' AND TABLE_SCHEMA = '{$this->database}'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = <<<sql
                CREATE TABLE contact_forms(
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    owner_id BIGINT UNSIGNED NOT NULL,
                    form_title VARCHAR(255) NOT NULL,
                    confirmation_message TEXT NOT NULL,
                    copy_from_channel_id BIGINT UNSIGNED DEFAULT NULL,
                    enable_overlay BOOLEAN DEFAULT FALSE,
                    enabled BOOLEAN DEFAULT TRUE,
                    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                   CONSTRAINT fk_contact_forms_users FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
                   CONSTRAINT fk_contact_forms_channels FOREIGN KEY (copy_from_channel_id) REFERENCES channels(id) ON DELETE CASCADE  
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);

        }

        // does column contact_form_id already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'contact_form_id'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channels ADD contact_form_id INT UNSIGNED DEFAULT NULL AFTER public_hash";
            $dbs->executeStatement($sql);

        }

        // check if the foreign key constraint exists on channels.contact_form_id
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'channels' AND COLUMN_NAME = 'contact_form_id' AND REFERENCED_TABLE_NAME = 'contact_forms';";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = <<<SQL
                ALTER TABLE channels 
                ADD CONSTRAINT fk_channels_contact_forms FOREIGN KEY (contact_form_id) REFERENCES contact_forms(id) ON DELETE CASCADE
SQL;

            $dbs->executeStatement($sql);

        }

        // does column contact_form_filler_id already exists on channels table?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channels' AND COLUMN_NAME = 'contact_form_filler_id'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channels ADD contact_form_filler_id BIGINT UNSIGNED DEFAULT NULL AFTER contact_form_id";
            $dbs->executeStatement($sql);

        }

        // check if the foreign key constraint exists on channels.contact_filler_id
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'channels' AND COLUMN_NAME = 'contact_form_filler_id' AND REFERENCED_TABLE_NAME = 'users';";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = <<<SQL
                ALTER TABLE channels 
                ADD CONSTRAINT fk_channels_filler_users FOREIGN KEY (contact_form_filler_id) REFERENCES users(id) ON DELETE CASCADE
SQL;

            $dbs->executeStatement($sql);

        }

        return true;
    }
}