<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Contracts;

use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FSEntryInterface;
use CodeLathe\Service\Storage\Contracts\Objects\SidecarInterface;
use CodeLathe\Service\Storage\Exceptions\DestinyPathAlreadyExistsException;
use CodeLathe\Service\Storage\Exceptions\InvalidPathException;
use CodeLathe\Service\Storage\Exceptions\NotAFileException;
use CodeLathe\Service\Storage\Exceptions\NotAFolderException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use CodeLathe\Service\Storage\Implementation\Objects\Download;
use CodeLathe\Service\Storage\Implementation\Objects\FolderProperties;

interface StorageServiceInterface
{

    /**
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * @param string $path
     * @return FSEntryInterface|null
     * @throws NotFoundException
     */
    public function info(string $path): ?FSEntryInterface;

    /**
     * Returns the listing of child entries for a given folder.
     * This method returns a paginated result (using infinite cursor-based, bi-directional pagination).
     * If the cursor path is not provided, it returns the first elements found, based on order and limit_after.
     * If the cursor path is provided, it will return X records before or after the cursor path (if both limits
     * are set, the cursor is included).
     *
     * @param string $path
     * @param string|null $cursorPath
     * @param bool $inDepth
     * @param array|null $idWhitelist When set, only ids included on this array are included on the results
     * @param string $sortBy Only accepts updated_on or name
     * @param bool $sortDescendent
     * @param int|null $limitBefore
     * @param int|null $limitAfter
     * @param bool $ignoreFolders
     * @param array $extensionWhitelist
     * @param array $extensionBlacklist
     * @return FSEntryInterface[]
     * @throws StorageServiceException
     * @throws NotAFolderException
     * @throws NotFoundException
     */
    public function list(string $path,
                         ?string $cursorPath = null,
                         bool $inDepth = false,
                         ?array $idWhitelist = null,
                         string $sortBy = 'updated_on',
                         bool $sortDescendent = true,
                         ?int $limitBefore = null,
                         ?int $limitAfter = null,
                         bool $ignoreFolders = false,
                         array $extensionWhitelist = [],
                         array $extensionBlacklist = []): array;

    /**
     * @param string $path
     * @param string|null $versionId
     * @param string|null $downloadType Can be 'redirect', 'local' or 'stream'. 'redirect' by default
     * @return mixed
     * @throws NotAFileException
     * @throws NotFoundException
     */
    public function download(string $path, ?string $versionId = null, ?string $downloadType = null): Download;

    /**
     * @param string $path
     * @param string $sidecarMetadata
     * @param string|null $downloadType
     * @return Download
     * @throws NotFoundException
     */
    public function downloadSidecar(string $path, string $sidecarMetadata, ?string $downloadType = null): Download;

    /**
     * @param string $name
     * @param string $owner
     * @return int
     * @throws DestinyPathAlreadyExistsException
     */
    public function createRoot(string $name, string $owner): int;

    /**
     * @param string $path
     * @param string $name
     * @param string $owner
     * @return int
     * @throws DestinyPathAlreadyExistsException
     * @throws NotAFolderException
     * @throws NotFoundException
     */
    public function createFolder(string $path, string $name, string $owner): int;

    /**
     * Uploads the file and returns a FileInterface if the upload is complete
     * or null if not
     *
     * @param string $parentPath
     * @param string $name
     * @param string $localFilePath
     * @param int $chunkStart
     * @param bool $finalChunk
     * @param string $owner
     * @return FileInterface
     * @throws DestinyPathAlreadyExistsException
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function uploadFile(string $parentPath,
                               string $name,
                               string $localFilePath,
                               int $chunkStart,
                               bool $finalChunk,
                               string $owner): ?FileInterface;

    /**
     * @param string $srcPath
     * @param string $dstPath
     * @param bool $merge
     * @return mixed
     * @throws NotFoundException
     * @throws InvalidPathException
     * @throws DestinyPathAlreadyExistsException
     * @throws StorageServiceException
     */
    public function move(string $srcPath, string $dstPath, bool $merge = false): void;

    public function copy(string $srcPath, string $dstPath): void;

    /**
     * @param string $path
     * @param bool $children
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function delete(string $path, bool $children = false): void;

    /**
     * @param string $mainPath
     * @param string $sideCarMetaData
     * @param string $localThumbFile
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function uploadSidecar(string $mainPath, string $sideCarMetaData, string $localThumbFile): void;

    /**
     * @param string $path
     * @return FolderProperties
     * @throws NotFoundException
     */
    public function folderProperties(string $path): FolderProperties;

    /**
     * @param string $path
     * @return FileInterface[]
     * @throws NotFoundException
     */
    public function versions(string $path): array;

    /**
     * @param string $path
     * @param string $sideCarMetaData
     * @return mixed
     * @throws NotFoundException
     */
    public function infoSideCar(string $path, string $sideCarMetaData): SidecarInterface;


}