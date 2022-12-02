<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion31 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Include form_hash and color on contact_forms table";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does form_hash column already exists on contact_forms table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'contact_forms' 
                   AND COLUMN_NAME = 'form_hash';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE contact_forms ADD COLUMN form_hash VARCHAR(255) DEFAULT NULL AFTER owner_id;
sql;
            $dbs->executeStatement($sql);

            // populate the existent forms with unique public hashes
            $sql = 'SELECT 1 FROM contact_forms WHERE form_hash = :hash';
            foreach ($dbs->cursor('SELECT * FROM contact_forms') as $row) {

                // generate a unique hash
                do {
                    $hash = StringUtility::generateRandomString(64);
                } while($dbs->selectOne($sql, ['hash' => $hash]) !== null);

                // update the row
                $dbs->update('UPDATE contact_forms SET form_hash = :hash WHERE id = :id', ['hash' => $hash, 'id' => $row['id']]);

            }

            $dbs->executeStatement("ALTER TABLE contact_forms ADD CONSTRAINT uq_contact_forms_form_hash UNIQUE(form_hash)");
            $dbs->executeStatement("ALTER TABLE contact_forms CHANGE COLUMN form_hash form_hash VARCHAR(255) NOT NULL");

        }

        // does color column already exists on contact_forms table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'contact_forms' 
                   AND COLUMN_NAME = 'color';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                ALTER TABLE contact_forms ADD COLUMN color BIGINT DEFAULT NULL AFTER confirmation_message
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
