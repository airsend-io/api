<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Implementation\Data;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FolderInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FSEntryInterface;
use CodeLathe\Service\Storage\Contracts\Objects\SidecarInterface;
use CodeLathe\Service\Storage\Exceptions\StorageDatabaseEntryNotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageDatabaseException;
use CodeLathe\Service\Storage\Implementation\Objects\File;
use CodeLathe\Service\Storage\Implementation\Objects\Folder;
use CodeLathe\Service\Storage\Implementation\Objects\FolderProperties;
use CodeLathe\Service\Storage\Implementation\Objects\Sidecar;
use function foo\func;

class MySQLItemDataStore implements ItemDataStoreInterface
{

    /**
     * @var FSDatabaseService
     */
    protected $dbs;

    public function __construct(FSDatabaseService $dbs)
    {
        $this->dbs = $dbs;
    }

    protected function createEntryObject(array $record, ?string $path): FSEntryInterface
    {

        if ($path === null) {
            $path = $this->findParentPath((int)$record['id']) . "/{$record['name']}";
        }

        if ($record['type'] === 'folder') {
            return new Folder((int)$record['id'],
                $path,
                (int)$record['parent_id'],
                $record['creationdate'],
                $record['modificationdate'],
                $record['lastaccessdate']);
        }

        if ($record['type'] === 'sidecarfile') {
            return new Sidecar((int)$record['id'],
                $path,
                (int)$record['parent_id'],
                $record['extension'],
                (int)$record['size'],
                $record['fileid'],
                $record['storagezoneid'],
                $record['storagepath'],
                $record['backstoredata'],
                (bool)$record['complete'],
                $record['creationdate'],
                $record['modificationdate'],
                $record['lastaccessdate'],
                $record['owner']);
        }

        return new File((int)$record['id'],
            $path,
            (int)$record['parent_id'],
            $record['extension'] ?? '',
            (int)$record['size'],
            $record['fileid'],
            $record['storagezoneid'],
            $record['storagepath'],
            $record['backstoredata'],
            (bool)$record['complete'],
            $record['creationdate'],
            $record['modificationdate'],
            $record['lastaccessdate'],
            $record['owner'],
            $record['versioneddate']);
    }

    protected function findParentPath(int $id): string
    {
        if (!preg_match('/(.*)\/[^\/]+$/', $this->findPath($id), $matches)) {
            return '';
        }
        return $matches[1];
    }

    protected function findPath(int $id, string $path = ''): string
    {

        $sql = <<<sql
            SELECT name, parent_id
            FROM items
            WHERE id = :id;
sql;
        $record = $this->dbs->selectOne($sql, compact('id'));

        $path = empty($path) ? $record['name'] : "{$record['name']}/$path";

        if ($record['parent_id'] === null) {
            return "/$path";
        }

        return $this->findPath((int)$record['parent_id'], $path);

    }

    public function getEntryById(int $id, ?string $parentPath = null): ?FSEntryInterface
    {

        if ($parentPath === null) {
            $parentPath = $this->findParentPath($id);
        }

        $sql = <<<sql
            SELECT * 
            FROM items
            WHERE id = :id
sql;
        $record = $this->dbs->selectOne($sql, compact('id'));
        return $this->createEntryObject($record, "$parentPath/{$record['name']}");
    }

    protected function getRegistryByParentIdAndName(?int $parentId,
                                                    string $name,
                                                    ?string $versionedDate = null,
                                                    ?string $sidecarMetadata = null): ?array
    {
        $sql = <<<sql
            SELECT *
            FROM items
            WHERE parent_id = :parentId
            AND name = :name
sql;
        $bindings = compact('parentId', 'name');

        if ($versionedDate === null) {
            $sql .= ' AND versioneddate IS NULL ';
        } else {
            $sql .= ' AND versioneddate = :versionedDate';
            $bindings['versionedDate'] = $versionedDate;
        }

        if ($sidecarMetadata === null) {
            $sql .= ' AND sidecarmetadata IS NULL ';
        } else {
            $sql .= ' AND sidecarmetadata = :sidecarMetadata';
            $bindings['sidecarMetadata'] = $sidecarMetadata;
        }

        return $this->dbs->selectOne($sql, $bindings);
    }

    public function entryExists(?int $parentId,
                                string $name,
                                ?string $versionedDate = null,
                                ?string $sidecarMetadata = null): bool
    {
        return $this->getRegistryByParentIdAndName($parentId, $name, $versionedDate, $sidecarMetadata) !== null;
    }

    /**
     * @inheritDoc
     */
    public function getEntry(?int $parentId,
                             string $parentPath,
                             string $name,
                             ?string $versionedDate = null,
                             ?string $sidecarMetadata = null): ?FSEntryInterface
    {

        $record = $this->getRegistryByParentIdAndName($parentId, $name, $versionedDate, $sidecarMetadata);
        if ($record === null) {
            return null;
        }

        return $this->createEntryObject($record, "$parentPath/$name");

    }

    protected function splitPath(string $path): array
    {
        return explode("/", trim($path, '/'));

    }

    protected function findRecordByPath(string $path): ?array
    {
        // first split the path
        $steps = $this->splitPath($path);

        $result = null;
        $parentId = null;
        while (!empty($steps)) {

            $name = array_shift($steps);
            if ($parentId === null) {
                $parentIdSql = "parent_id IS NULL";
                $bindings = compact('name');
            } else {
                $parentIdSql = "parent_id = :parentId";
                $bindings = compact('parentId', 'name');
            }

            $sql = <<<sql
                SELECT * 
                FROM items 
                WHERE {$parentIdSql} 
                  AND name = :name 
                  AND type IN ('file', 'folder') 
                  AND versioneddate IS NULL
                  AND (type = 'folder' OR complete > 0)
sql;
            $result = $this->dbs->selectOne($sql, $bindings);

            if ($result === null) {
                return null; // path not found
            }

            $parentId = $result['id'];

        }

        return $result;
    }

    protected function findRecordVersion(int $parentId, string $name, string $versionedDate): ?array
    {

        $sql = <<<sql
                SELECT * 
                FROM items 
                WHERE parent_id = :parentId
                  AND name = :name 
                  AND type = 'file'
                  AND versioneddate = :versionedDate
                  AND complete > 0
sql;


        $result = $this->dbs->selectOne($sql, compact('parentId', 'name', 'versionedDate'));

        if ($result === null) {
            return null; // path not found
        }

        return $result;
    }

    protected function findRecordSidecar(int $parentId, string $name, string $sidecarMetadata): ?array
    {
        $sql = <<<sql
                SELECT * 
                FROM items 
                WHERE parent_id = :parentId
                  AND name = :name 
                  AND type = 'sidecarfile'
                  AND sidecarmetadata = :sidecarMetadata
                  AND complete > 0
sql;

        $result = $this->dbs->selectOne($sql, compact('parentId', 'name', 'sidecarMetadata'));

        if ($result === null) {
            return null; // path not found
        }

        return $result;
    }

    public function pathExists(string $path, ?string $versionedDate = null): bool
    {
        $record = $this->findRecordByPath($path);
        if ($versionedDate !== null) {
            $record = $this->findRecordVersion($record['parent_id'], $record['name'], $versionedDate);
        }
        return $record !== null;
    }

    /**
     * @param string $path
     * @param string|null $versionedDate
     * @return File|Folder|null
     */
    public function getByPath(string $path, ?string $versionedDate = null): ?FSEntryInterface
    {

        $record = $this->findRecordByPath($path);
        if ($record === null) {
            return null;
        }

        // if we're looking for an older version of the file, replace the record
        if ($record['type'] === 'file' && $versionedDate !== null){
            $record = $this->findRecordVersion((int)$record['parent_id'], $record['name'], $versionedDate);
            if ($record === null) {
                return null;
            }
        }

        return $this->createEntryObject($record, $path);

    }

    /**
     * @param string $path
     * @param string $sidecarMetadata
     * @return SidecarInterface|null
     * @throws StorageDatabaseException
     */
    public function getSidecarByPath(string $path, string $sidecarMetadata): ?SidecarInterface
    {
        $mainRecord = $this->findRecordByPath($path);
        if ($mainRecord === null) {
            return null;
        }
        $record = $this->findRecordSidecar((int)$mainRecord['parent_id'], $mainRecord['name'], $sidecarMetadata);
        if ($record === null) {
            return null;
        }
        $entry = $this->createEntryObject($record, $path);

        if ($entry instanceof SidecarInterface) {
            return $entry;
        }

        throw new StorageDatabaseException("Failed to find the sidecar: $path:$sidecarMetadata");

    }

    public function getVersion()
    {

    }

    /**
     * @param int $rootId
     * @param string $rootPath
     * @param bool $recursive
     * @return string[]
     */
    protected function listFoldersIds(int $rootId, string $rootPath, bool $recursive = true): array
    {

        $sql = <<<sql
                SELECT id, name
                FROM items 
                WHERE parent_id = :rootId
                  AND type = 'folder'
sql;

        $rows = $this->dbs->select($sql, compact('rootId'));

        $foundRoots = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $path = "$rootPath/{$row['name']}";
            $foundRoots[$id] = $path;
            if ($recursive) {
                $foundRoots += $this->listFoldersIds($id, $path);
            }
        }

        return $foundRoots;

    }

    protected function listPage(FSEntryInterface $cursorEntry, int $pageSize, string $parentIdSql, string $idListSql, string $ignoreFoldersSql, string $extensionSql, $sortBy, $sortDirection, $cursorConditionOp): array
    {

        if ($sortBy === 'name') {
            $orderSql = "concat(CASE WHEN type = 'file' THEN '1' ELSE '0' END, name) $sortDirection, id $sortDirection";
            $sortName = ($cursorEntry instanceof FolderInterface ? '0' : '1') . $cursorEntry->getName();
            $cursorCondition = "concat(CASE WHEN type = 'file' THEN '1' ELSE '0' END, name) $cursorConditionOp '$sortName'";
        } else {
            $orderSql = "(CASE WHEN type = 'file' THEN 1000000000 ELSE 0 END) + UNIX_TIMESTAMP(modificationdate) $sortDirection, id $sortDirection";
            $sortValue = $cursorEntry->getModifiedOn()->timestamp + ($cursorEntry instanceof FolderInterface ? 0 : 1000000000);
            $cursorCondition = "(CASE WHEN type = 'file' THEN 1000000000 ELSE 0 END) + UNIX_TIMESTAMP(modificationdate) $cursorConditionOp $sortValue";
        }

        $output = [];
        $limit = 50;
        $offset = 0;
        $cursorPassed = false;
        while (true) {

            $sql = <<<sql
                        SELECT i.*
                        FROM items i
                        WHERE $parentIdSql
                          AND type IN ('file', 'folder') 
                          AND versioneddate IS NULL
                          AND (type = 'folder' OR complete > 0)
                          $idListSql
                          $ignoreFoldersSql
                          $extensionSql
                          AND $cursorCondition
                        ORDER BY $orderSql
                        LIMIT :limit OFFSET :offset
sql;
            $rows = $this->dbs->select($sql, compact('limit', 'offset'));

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if ($row['id'] == $cursorEntry->getId()) {
                    $cursorPassed = true;
                    continue;
                }

                if ($cursorPassed && count($output) < $pageSize) {
                    $output[] = $row;
                }
            }

            if (count($output) >= $pageSize) {
                break;
            }

            $offset += $limit;

        }

        return $output;
    }

    /**
     * @param int $rootId
     * @param string $rootPath
     * @param string|null $cursorPath
     * @param bool $inDepth
     * @param int[]|null $idWhitelist
     * @param string $sortBy - Only supports modificationdate or name
     * @param bool $sortDescendent
     * @param int|null $limitBefore
     * @param int|null $limitAfter
     * @param bool $ignoreFolders
     * @param array $extensionWhitelist
     * @param array $extensionBlacklist
     * @return FSEntryInterface[]
     */
    public function list(int $rootId,
                         string $rootPath,
                         ?string $cursorPath = null,
                         bool $inDepth = false,
                         ?array $idWhitelist = null,
                         string $sortBy = 'modificationdate',
                         bool $sortDescendent = true,
                         ?int $limitBefore = null,
                         ?int $limitAfter = null,
                         bool $ignoreFolders = false,
                         array $extensionWhitelist = [],
                         array $extensionBlacklist = []): array
    {

        $roots[$rootId] = $rootPath;
        if ($inDepth) {
            // recursively list all folders under the current root
            $roots += $this->listFoldersIds($rootId, $rootPath);
        }

        $parentIdSql = "parent_id IN (" . implode(',', array_keys($roots)) . ') ';

        // define the ids whitelist
        $idListSql = '';
        if ($idWhitelist !== null && !empty($idWhitelist)) {
            $idListSql = 'AND id IN (' . implode(',', $idWhitelist) . ')';
        }

        // ignore folders?
        $ignoreFoldersSql = $ignoreFolders ? " AND type <> 'folder'" : '';

        // define the extension whitelist/blacklist
        $extensionSql = !empty($extensionWhitelist) ? ' AND extension IN (' . implode(',', $extensionWhitelist) . ')' : '';
        if (empty($extensionSql)) {
            $extensionSql = !empty($extensionBlacklist)
                ? ' AND extension NOT IN (' . implode(',', $extensionBlacklist) . ')'
                : '';
        }

        // if there is no cursor defined, just grab data from the database
        if ($cursorPath === null) {

            // define the sorting - always force directories to come before files
            $sortDirection = $sortDescendent ? 'desc' : 'asc';
            if ($sortBy === 'name') {
                $orderSql = "ORDER BY concat(CASE WHEN type = 'file' THEN '1' ELSE '0' END, name) $sortDirection";
            } else {
                $orderSql = "ORDER BY (CASE WHEN type = 'file' THEN 1000000000 ELSE 0 END) + UNIX_TIMESTAMP(modificationdate) $sortDirection";
            }

            // if no limit is defined, consider it 50
            $limit = $limitAfter ?? 50;

            // select the database
            $sql = <<<sql
                    SELECT i.*
                    FROM items i
                    WHERE $parentIdSql
                      AND type IN ('file', 'folder') 
                      AND versioneddate IS NULL
                      AND (type = 'folder' OR complete > 0)
                      $idListSql
                      $ignoreFoldersSql
                      $extensionSql
                    $orderSql
                    LIMIT :limit
sql;

            $rows = $this->dbs->select($sql, compact('limit'));

        } else {

            // handle the cursor
            $cursorEntry = $this->getByPath($cursorPath);

            // ensure the limit
            if ($limitBefore === null && $limitAfter === null) {
                $limitAfter = 50;
            }

            $rowsBefore = [];
            if ($limitBefore !== null) {

                // define the sorting and cursor condition - always force directories to come before files
                // this sorting is inverted, because we're listing items before the cursor
                $sortDirection = $sortDescendent ? 'asc' : 'desc';
                $cursorConditionOp = $sortDescendent ? '>=' : '<=';
                $rowsBefore = $this->listPage($cursorEntry, $limitBefore, $parentIdSql, $idListSql, $ignoreFoldersSql, $extensionSql, $sortBy, $sortDirection, $cursorConditionOp);
                $rowsBefore = array_reverse($rowsBefore);
            }

            $rowsAfter = [];
            if ($limitAfter !== null) {

                // define the sorting and cursor condition - always force directories to come before files
                $sortDirection = $sortDescendent ? 'desc' : 'asc';
                $cursorConditionOp = $sortDescendent ? '<=' : '>=';
                $rowsAfter = $this->listPage($cursorEntry, $limitAfter, $parentIdSql, $idListSql, $ignoreFoldersSql, $extensionSql, $sortBy, $sortDirection, $cursorConditionOp);

            }

            if (!empty($rowsBefore) && !empty($rowsAfter)) {
                $rows = array_merge($rowsBefore, [$cursorEntry], $rowsAfter);
            } else {
                $rows = array_merge($rowsBefore, $rowsAfter);
            }

        }

        return array_map(function ($row) use ($roots) {

            if ($row instanceof FSEntryInterface) {
                return $row;
            }

            return $this->createEntryObject($row, "{$roots[(int)$row['parent_id']]}/{$row['name']}");

        }, $rows);

    }

    /**
     * @param int $srcId
     * @param int $dstParentId
     * @param string $dstName
     * @return bool
     * @throws StorageDatabaseException
     */
    public function move(int $srcId, int $dstParentId, string $dstName): void
    {

        // find the registry
        $record = $this->findRecordById($srcId);

        // move main file
        $sql = <<<sql
            UPDATE items
            SET name = :dstName, parent_id = :dstParentId, modificationdate = :now
            WHERE id = :srcId
sql;
        $now = Carbon::now()->format('Y-m-d H:i:s');

        if ($this->dbs->executeAffectingStatement($sql, compact('srcId', 'dstParentId', 'dstName', 'now')) < 1) {
            throw new StorageDatabaseException('Failed to update the parent id for entry');
        }

        // move versions and sidecars
        $sql = <<<sql
            UPDATE items
            SET name = :dstName, parent_id = :dstParentId, modificationdate = :now
            WHERE name = :srcName AND parent_id = :parentId
sql;
        $srcName = $record['name'];
        $parentId = $record['parent_id'];
        $this->dbs->executeStatement($sql, compact('dstParentId', 'dstName', 'now', 'srcName', 'parentId'));


    }

    public function createFolder(?int $parentId, string $name, string $owner): ?int
    {
        $sql = <<<sql
            INSERT INTO items (
                            name, parent_id, type, owner, creationdate, 
                            modificationdate
                        )
                        VALUES (
                            :name,
                            :parentid,
                            'folder',
                            :owner,
                            :creationdate,
                            :modificationdate
                        );
sql;
        $now = CarbonImmutable::now()->format('Y-m-d H:i:s');

        $bindings = [
            'name' => $name,
            'parentid' => $parentId,
            'owner' => $owner,
            'creationdate' => $now,
            'modificationdate' => $now
        ];

        if ($this->dbs->executeAffectingStatement($sql, $bindings) < 1) {
            return null;
        }

        return (int)$this->dbs->lastInsertId();
    }

    /**
     * @param int $parentId
     * @param string $name
     * @param string $owner
     * @param bool $complete
     * @param int $size
     * @param string $fileId
     * @param string $storagePath
     * @param string $storageZoneId
     * @param string|null $backstoreData
     * @param string|null $extension
     * @param string|null $sidecarMetadata
     * @return int
     * @throws StorageDatabaseException
     */
    public function createFile(int $parentId,
                               string $name,
                               string $owner,
                               bool $complete,
                               int $size,
                               string $fileId,
                               string $storagePath,
                               string $storageZoneId,
                               ?string $backstoreData,
                               ?string $extension = null,
                               ?string $sidecarMetadata = null): int
    {
        $sql = <<<sql
            INSERT INTO items (
                            name, extension, parent_id, type, size, owner, creationdate, 
                            modificationdate, fileid, complete, storagepath, storagezoneid, backstoredata, sidecarmetadata
                        )
                        VALUES (
                            :name,                            
                            :ext,
                            :parentid,
                            :type,
                            :size,
                            :owner,
                            :creationdate,
                            :modificationdate,
                            :fileid,
                            :complete,
                            :storagepath,
                            :storagezoneid,
                            :backstoredata,
                            :sidecarMetadata
                        );
sql;
        $now = CarbonImmutable::now()->format('Y-m-d H:i:s');

        $bindings = [
            'name' => $name,
            'ext' => $extension!== null ? strtolower($extension) : null,
            'parentid' => $parentId,
            'type' => $sidecarMetadata === null ? 'file' : 'sidecarfile',
            'size' => $size,
            'owner' => $owner,
            'creationdate' => $now,
            'modificationdate' => $now,
            'fileid' => $fileId,
            'complete' => (int)$complete,
            'storagepath' => $storagePath,
            'storagezoneid' => $storageZoneId,
            'backstoredata' => $backstoreData,
            'sidecarMetadata' => $sidecarMetadata
        ];

        if ($this->dbs->executeAffectingStatement($sql, $bindings) < 1) {
            throw new StorageDatabaseException('Could not create the file on storage.');
        }

        return (int)$this->dbs->lastInsertId();
    }

    /**
     * @param int $parentId
     * @param string $name
     * @param bool $complete
     * @param int $size
     * @param string $backstoreData
     * @return int
     * @throws StorageDatabaseEntryNotFoundException
     */
    public function updateFileProgress(int $parentId, string $name, bool $complete, int $size, string $backstoreData): int
    {
        $sql = <<<sql
            SELECT id
            FROM items
            WHERE parent_id = :parentId
            AND name = :name
            AND type = 'file'
            AND NOT complete
sql;
        $record = $this->dbs->selectOne($sql, compact('parentId', 'name'));
        if ($record === null) {
            throw new StorageDatabaseEntryNotFoundException('Previous file not found');
        }
        $id = (int)$record['id'];

        $sql = <<<sql
            UPDATE items 
            SET size = size + :size, complete = :complete, backstoredata = :backstoreData
            WHERE id = :id
sql;
        $bindings = compact('id', 'size', 'backstoreData');
        $bindings['complete'] = (int) $complete;
        if ($this->dbs->executeAffectingStatement($sql, $bindings) < 1) {
            throw new StorageDatabaseEntryNotFoundException('Previous file not found');
        }

        return $id;

    }

    public function createVersion(FileInterface $file): bool
    {

        // delete all sidecars for the file
        $name = $file->getName();
        $parentId = $file->getParentId();

        if ($parentId === null) {
            $sql = <<<sql
                DELETE FROM items
                WHERE name = :name 
                    AND parent_id IS NULL
                    AND type = 'sidecarfile';
sql;
            $bindings = compact('name');
        } else {
            $sql = <<<sql
                DELETE FROM items
                WHERE name = :name 
                    AND parent_id = :parentId
                    AND type = 'sidecarfile';
sql;
            $bindings = compact('name', 'parentId');
        }

        $this->dbs->executeStatement($sql, $bindings);

        $sql = <<<sql
            UPDATE items
            SET versioneddate = :now
            WHERE id = :id
sql;

        $now = CarbonImmutable::now()->format('Y-m-d H:i:s');
        $id = $file->getId();
        return $this->dbs->executeAffectingStatement($sql, compact('id', 'now')) > 0;

    }

    /**
     * @param int $entryId
     * @param bool $force
     * @throws StorageDatabaseException
     */
    public function deleteEntry(int $entryId, bool $force = false): void
    {

        // recursively delete all children of the path
        if ($force) {

            $record = $this->findRecordById($entryId);
            // recursive clear children
            $sql = <<<sql
                SELECT id FROM items WHERE parent_id = :parentId
sql;
            $bindings = ['parentId' => $record['id']];
            foreach ($this->dbs->select($sql, $bindings) as $row) {
                $this->deleteEntry((int)$row['id'], true);
            }
            $this->dbs->executeStatement("DELETE FROM items WHERE parent_id = :parentId", $bindings);

        }

        $sql = <<<sql
            DELETE FROM items
            WHERE id = :entryId
sql;

        if ($this->dbs->executeAffectingStatement($sql, compact('entryId')) < 1) {
            throw new StorageDatabaseException('Failed to delete the file');
        }
    }

    /**
     * @param int $srcId
     * @param int $dstParentId
     * @param string $dstName
     * @param string|null $newFileId
     * @param string|null $newStoragePath
     * @throws StorageDatabaseException
     */
    public function copy(int $srcId,
                         int $dstParentId,
                         string $dstName,
                         ?string $newFileId = null,
                         ?string $newStoragePath = null): void
    {

        // grab the source entry from database
        $sql = <<<sql
            SELECT * 
            FROM items
            WHERE id = :srcId
sql;
        $record = $this->dbs->selectOne($sql, compact('srcId'));
        if ($record === null) {
            throw new StorageDatabaseException("Copy: Source entry not found: $srcId");
        }

        // remove the id
        unset($record['id']);

        // update the name
        $record['name'] = $dstName;

        // replace the fileid/storagepath
        $record['fileid'] = $newFileId !== null ? $newFileId : $record['fileid'];
        $record['storagepath'] = $newStoragePath !== null ? $newStoragePath : $record['storagepath'];

        // update timestamps
        $now = CarbonImmutable::now();
        $record['creationdate'] = $now->format('Y-m-d H:i:s');
        $record['modificationdate'] = $now->format('Y-m-d H:i:s');
        $record['lastaccessdate'] = null; // replace the parent_id
        $record['parent_id'] = $dstParentId; // prepare the insert sql
        $columns = implode(', ', array_keys($record));
        $values = ':' . implode(', :', array_keys($record));
        $sql = <<<sql
            INSERT INTO items ( {$columns} ) VALUES ( {$values} );
sql;

        // insert the new entry
        if ($this->dbs->executeAffectingStatement($sql, $record) < 1) {
            throw new StorageDatabaseException('Failed creating entry copy.');
        }
    }

    protected function findRecordById(int $id): ?array
    {
        $sql = <<<sql
            SELECT *
            FROM items
            WHERE id = :id
sql;
        return $this->dbs->selectOne($sql, compact('id'));
    }

    public function calculateFolderSizes(int $parentId): FolderProperties
    {

        $foldersCount = 0;
        $filesCount = 0;
        $filesSize = 0;
        $versionsCount = 0;
        $versionsSize = 0;
        $sidecarsCount = 0;
        $sidecarsSize = 0;

        $sql = <<<sql
            SELECT id, type, size, versioneddate
            FROM items
            WHERE parent_id = :parentId
sql;

        $rows = $this->dbs->select($sql, compact('parentId'));
        foreach ($rows as $row) {
            switch ($row['type']) {
                case 'folder':
                    $foldersCount++;
                    $props = $this->calculateFolderSizes((int)$row['id']);
                    $foldersCount += $props->getFolderCount();
                    $filesCount += $props->getFileCount();
                    $filesSize += $props->getFileSize();
                    $sidecarsCount += $props->getSidecarCount();
                    $sidecarsSize += $props->getSidecarSize();
                    break;
                case 'file':
                    if ($row['versioneddate'] === null) {
                        $filesCount++;
                        $filesSize += (int)$row['size'];
                    } else {
                        $versionsCount++;
                        $versionsSize += (int)$row['size'];
                    }
                    break;
                case 'sidecarfile':
                    $sidecarsCount++;
                    $sidecarsSize += (int)$row['size'];
                    break;
            }
        }
        return new FolderProperties($foldersCount, $filesCount, $filesSize, $versionsCount, $versionsSize, $sidecarsCount, $sidecarsSize);

    }

    /**
     * @inheritDoc
     */
    public function getVersions(int $parentId, string $parentPath, string $name): array
    {

        $sql = <<<sql
            SELECT *
            FROM items
            WHERE parent_id = :parentId
                AND name = :name
                AND type = 'file'
                AND versioneddate IS NOT NULL
sql;

        $rows = $this->dbs->select($sql, compact('parentId', 'name'));

        return array_map(function ($row) use ($parentPath) {
            return $this->createEntryObject($row, "$parentPath/{$row['name']}");
        }, $rows);
    }
}