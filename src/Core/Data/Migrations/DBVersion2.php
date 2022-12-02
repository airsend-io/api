<?php

namespace CodeLathe\Core\Data\Migrations;

use CodeLathe\Service\Database\DatabaseService;

class DBVersion2 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Create fulltext index on channels.channel_name";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        try {
            $sql = "SHOW INDEXES FROM channels WHERE column_name = 'channel_name' AND index_type = 'FULLTEXT'";
            $indexes = array_map(function($row) {
                return $row['Key_name'];
            }, $dbs->select($sql));
            foreach ($indexes as $index) {
                $sql = "DROP INDEX $index ON channels";
                $dbs->executeStatement($sql);
            }
            $sql = "ALTER TABLE channels ADD FULLTEXT (channel_name);";
            return $dbs->executeStatement($sql);
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }
    }
}