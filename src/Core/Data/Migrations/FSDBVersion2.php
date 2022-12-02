<?php

namespace CodeLathe\Core\Data\Migrations;

use CodeLathe\Service\Database\DatabaseService;

class FSDBVersion2 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Create fulltext index on items.name';
    }

    public function handle(DatabaseService $dbs): bool
    {
        try {
            $sql = "ALTER TABLE asstoragedb.items ADD FULLTEXT (name);";
            return $dbs->executeStatement($sql);
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            var_dump($e->getMessage());
            return false;
        }
    }
}