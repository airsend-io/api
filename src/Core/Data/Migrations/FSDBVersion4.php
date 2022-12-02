<?php

namespace CodeLathe\Core\Data\Migrations;

use CodeLathe\Service\Database\DatabaseService;
use Throwable;
use function uniqid;

class FSDBVersion4 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Refactor storage db structure';
    }

    public function handle(DatabaseService $dbs): bool
    {

        try {

            // create parent_id column if it doesn't exists
            $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'items' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'parent_id'";
            if ($dbs->selectOne($sql) === null) {
                $dbs->executeStatement("ALTER TABLE {$this->database}.items ADD COLUMN parent_id int(20) unsigned default null after parentpath");
            }

            // create the foreign key on the parent_id column
            $sql = <<<sql
                SELECT 1
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE CONSTRAINT_SCHEMA = '{$this->database}'
                        AND TABLE_NAME = 'items'
                        AND COLUMN_NAME = 'parent_id'
                        AND REFERENCED_TABLE_SCHEMA = '{$this->database}'
                        AND REFERENCED_TABLE_NAME = 'items'
                        AND REFERENCED_COLUMN_NAME = 'id';
sql;
            if ($dbs->selectOne($sql) === null) {
                $dbs->executeStatement("ALTER TABLE {$this->database}.items ADD CONSTRAINT fk_items_items FOREIGN KEY (parent_id) REFERENCES {$this->database}.items(id);");
            }

            // create the index to search for parent_id and name
            $execute = false;
            $sql1 = <<<sql
                SHOW INDEX FROM {$this->database}.items 
                WHERE Column_name = 'parent_id' AND Seq_in_index = 1
sql;
            $rows = $dbs->select($sql1); // get all indexes that start with parent_id
            foreach ($rows as $row) {
                $sql2 = <<<sql
                    SHOW INDEX FROM {$this->database}.items 
                    WHERE Column_name = 'name' AND Seq_in_index = 2 AND Key_name = :keyName
sql;
                if ($dbs->selectOne($sql2, ['keyName' => $row['Key_name']])) {
                    $execute = true;
                    break;
                }
            }
            if ($execute) {
                $dbs->executeStatement("CREATE INDEX idx_parent_id_name ON {$this->database}.items (parent_id, name);");
            }

            $this->migrateParentId($dbs);

        } catch (Throwable $e) {
            $this->logger->debug($e->getMessage());
            var_dump($e->getMessage());
            return false;
        }

        return true;
    }

    protected function migrateParentId(DatabaseService $dbs)
    {
        $countQuery = "SELECT count(*) as count FROM {$this->database}.items WHERE parent_id IS NULL AND parentpath <> '/';";
        $outerQuery = <<<sql
            SELECT id, parentpath, name, creationdate, modificationdate, syncversion
            FROM {$this->database}.items 
            WHERE parent_id IS NULL 
              AND parentpath <> '/'
            ORDER BY id 
            LIMIT :limit;
sql;
        $innerQuery = 'SELECT id from items where parentpath = :parentpath and name = :name';
        $insertQuery = <<<sql
            INSERT INTO {$this->database}.items(
                              fileid, 
                              creationdate, 
                              modificationdate, 
                              complete, 
                              type, 
                              size, 
                              syncversion, 
                              owner, 
                              name, 
                              parentpath, 
                              storagezoneid
                              )
            VALUES (
                    :fileid, 
                    :creationdate, 
                    :modificationdate, 
                    0, 
                    'folder', 
                    0, 
                    :syncversion, 
                    :owner,
                    :name,
                    :parentpath,
                    'S3_US_EAST1'
                    );
sql;
        $updateQuery = "UPDATE {$this->database}.items SET parent_id = :parent_id WHERE id = :id";

        $limit = 100;
        $done = 0;

        $result = $dbs->select($countQuery);
        $total = $result[0]['count'] ?? 0;
        if (empty($result) || $total === 0) {
            echo "No rows to migrate" . PHP_EOL;
        }

        $invalidPaths = 0;
        $brokenPaths = 0;

        echo "Migrating $total items..." . PHP_EOL . PHP_EOL;

        while (true) {

            $rows = $dbs->select($outerQuery, compact('limit'));

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {

                $done++;

                // split parentpath
                if (!preg_match('/(.*?)\/([^\/]+)$/', $row['parentpath'], $matches)) {

                    // skip invalid paths (should never happen)
                    echo "Invalid path: {$row['parentpath']}" . PHP_EOL;
                    $invalidPaths++;
                    continue;

                }

                $parentpath = empty($matches[1]) ? '/' : $matches[1];
                $name = $matches[2];

                $id = $row['id'];

                $rows2 = $dbs->select($innerQuery, compact('parentpath', 'name'));
                if (empty($rows2)) {
                    $brokenPaths++;
                    $result = $dbs->executeStatement($insertQuery, [
                        'fileid' => str_replace(".", "", uniqid('', true)),
                        'creationdate' => $row['creationdate'],
                        'modificationdate' => $row['modificationdate'],
                        'syncversion' => $row['syncversion'],
                        'owner' => 0,
                        'name' => $name,
                        'parentpath' => $parentpath,
                    ]);

                    if ($result !== 1) {
                        echo "ERROR: Insert of broken path failed!" . PHP_EOL;
                        continue;
                    }
                    $parent_id = (int)$dbs->lastInsertId();

                } else {
                    $parent_id = $rows2[0]['id'] ?? null;
                    $parent_id = (int)$parent_id;
                }

                if ($parent_id !== null) {

                    $result = $dbs->executeStatement($updateQuery, compact('id', 'parent_id'));
                    if ($result === null) {
                        echo "ERROR: Update failed!" . PHP_EOL;
                        continue;
                    }
                    if ($result === 0) {
                        echo "ERROR: Update had no results [$parentpath/$name]" . PHP_EOL;
                        continue;
                    }

                } else {

                    // impossible
                    echo "ERROR: Invalid result!!!! [$parentpath/$name]" . PHP_EOL;
                    continue;

                }

            }

            echo number_format(($done / $total) * 100, 2) . " % done ($done) ...                   \r";
        }
    }
}