<?php

namespace CodeLathe\Core\Data\Migrations;

use CodeLathe\Service\Database\DatabaseService;
use Throwable;
use function uniqid;

class FSDBVersion5 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Fix teams without the proper roots.';
    }

    public function handle(DatabaseService $dbs): bool
    {

        try {

            // first find teams created after 26-jun-2021 (we'll check all of them)
            $sql1 = <<<sql
                SELECT id, owner, creationdate, modificationdate
                FROM items
                WHERE parent_id is null
                AND creationdate  > DATE_SUB(NOW(), INTERVAL 2 DAY)
                AND name REGEXP '^[0-9]+$';
sql;
            $sql2 = <<<sql
                SELECT id
                FROM items
                WHERE parent_id = :parent_id
                AND name = :name
sql;

            $sqlInsert = <<<sql
                INSERT INTO items (
                            name, parent_id, type, owner, creationdate, 
                            modificationdate
                        )
                        VALUES (
                            :name,
                            :parent_id,
                            'folder',
                            :owner,
                            :creationdate,
                            :modificationdate
                        );
sql;



            $rows = $dbs->select($sql1);

            foreach ($rows as $row) {

                $insertBindings = [
                    'parent_id' => (int)$row['id'],
                    'owner' => $row['owner'],
                    'creationdate' => $row['creationdate'],
                    'modificationdate' => $row['modificationdate']
                ];

                // ensure Channels path
                if ($dbs->selectOne($sql2, ['parent_id' => $row['id'], 'name' => 'Channels']) === null) {
                    $insertBindings['name'] = 'Channels';
                    $dbs->executeStatement($sqlInsert, $insertBindings);
                }

                // ensure delete items path
                if ($dbs->selectOne($sql2, ['parent_id' => $row['id'], 'name' => 'deleted items']) === null) {
                    $insertBindings['name'] = 'deleted items';
                    $dbs->executeStatement($sqlInsert, $insertBindings);
                }
            }


        } catch (Throwable $e) {
            $this->logger->debug($e->getMessage());
            var_dump($e->getMessage());
            return false;
        }

        return true;
    }

}