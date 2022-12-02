<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Implementation\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FSEntryInterface;
use CodeLathe\Service\Storage\Contracts\Objects\SidecarInterface;
use CodeLathe\Service\Storage\Exceptions\StorageDatabaseEntryNotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageDatabaseException;
use CodeLathe\Service\Storage\Implementation\Objects\File;
use CodeLathe\Service\Storage\Implementation\Objects\Folder;
use CodeLathe\Service\Storage\Implementation\Objects\FolderProperties;

interface ItemDataStoreInterface
{

    public function entryExists(?int $parentId,
                                string $name,
                                ?string $versionedDate = null,
                                ?string $sidecarMetadata = null): bool;

    /**
     * @param int|null $parentId
     * @param string $parentPath
     * @param string $name
     * @param string|null $versionedDate
     * @param string|null $sidecarMetadata
     * @return FSEntryInterface|null
     */
    public function getEntry(?int $parentId,
                             string $parentPath,
                             string $name,
                             ?string $versionedDate = null,
                             ?string $sidecarMetadata = null): ?FSEntryInterface;

    public function pathExists(string $path, ?string $versionedDate = null): bool;


    /**
     * @param string $path
     * @param string|null $versionedDate
     * @return File|Folder|null
     */
    public function getByPath(string $path, ?string $versionedDate = null): ?FSEntryInterface;


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
                         array $extensionBlacklist = []): array;

    /**
     * @param int $srcId
     * @param int $dstParentId
     * @param string $dstName
     * @throws StorageDatabaseException
     */
    public function move(int $srcId, int $dstParentId, string $dstName): void;

    public function createFolder(?int $parentId, string $name, string $owner): ?int;

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
                               ?string $sidecarMetadata = null): int;

    /**
     * Returns the FileInterface object if the upload is complete, or null if not
     *
     * @param int $parentId
     * @param string $name
     * @param bool $complete
     * @param int $size
     * @param string $backstoreData
     * @return int
     * @throws StorageDatabaseEntryNotFoundException
     */
    public function updateFileProgress(int $parentId, string $name, bool $complete, int $size, string $backstoreData): int;

    public function createVersion(FileInterface $file): bool;

    /**
     * @param int $entryId
     * @param bool $force
     * @throws StorageDatabaseException
     */
    public function deleteEntry(int $entryId, bool $force = false): void;

    public function getEntryById(int $id, ?string $parentPath = null): ?FSEntryInterface;

    public function getSidecarByPath(string $path, string $sidecarMetadata): ?SidecarInterface;

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
                         ?string $newStoragePath = null): void;

    /**
     * @param int $parentId
     * @return FolderProperties
     */
    public function calculateFolderSizes(int $parentId): FolderProperties;

    /**
     * @param int $parentId
     * @param string $parentPath
     * @param string $name
     * @return FileInterface[]
     */
    public function getVersions(int $parentId, string $parentPath, string $name): array;


}