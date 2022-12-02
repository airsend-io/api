<?php

namespace CodeLathe\Core\Data\Migrations;

use CodeLathe\Service\Database\DatabaseService;

class FSDBVersion3 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Alter parentpath size to 750 and create1 index on items.parentpath';
    }

    public function handle(DatabaseService $dbs): bool
    {
        // this is not needed anymore...
//        try {
//            //Alter items table
//            $sql = "ALTER TABLE asstoragedb.items MODIFY parentpath VARCHAR(750);";
//            $success = $dbs->executeStatement($sql);
//            if(!$success){
//                $this->logger->debug("Failed to alter parentpath to 750 chars");
//                return false;
//            }
//        } catch (\Throwable $e) {
//            $this->logger->debug($e->getMessage());
//            var_dump($e->getMessage());
//            return false;
//        }
//
//        try {
//            //Create index
//            $sql = "CREATE INDEX idx_items_parentpath ON asstoragedb.items (parentpath);";
//            return $dbs->executeStatement($sql);
//
//        } catch (\Throwable $e) {
//            $message = $e->getMessage();
//            $this->logger->debug($message);
//            if(stripos($message, "duplicate")){
//                //if the index already exists, then ignore the error
//                $this->logger->debug("Index already exists. Skipping creation.");
//                return true;
//            }
//            return false;
//        }

        return true;
    }
}