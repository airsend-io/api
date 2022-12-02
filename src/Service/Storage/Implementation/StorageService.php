<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Implementation;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Service\Storage\Backstore\AbstractBackstore;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FolderInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FSEntryInterface;
use CodeLathe\Service\Storage\Contracts\Objects\SidecarInterface;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use CodeLathe\Service\Storage\Exceptions\StorageDatabaseEntryNotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageDatabaseException;
use CodeLathe\Service\Storage\Implementation\Backstore\BackstoreFactory;
use CodeLathe\Service\Storage\Implementation\Data\ItemDataStoreInterface;
use CodeLathe\Service\Storage\Implementation\Objects\Download;
use CodeLathe\Service\Storage\Implementation\Objects\File;
use CodeLathe\Service\Storage\Implementation\Objects\Folder;
use CodeLathe\Service\Storage\Exceptions\DestinyPathAlreadyExistsException;
use CodeLathe\Service\Storage\Exceptions\InvalidPathException;
use CodeLathe\Service\Storage\Exceptions\NotAFileException;
use CodeLathe\Service\Storage\Exceptions\NotAFolderException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use CodeLathe\Service\Storage\Implementation\Objects\FolderProperties;
use CodeLathe\Service\Storage\Implementation\Utility\StorageServiceUtility;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use Psr\Log\LoggerInterface;

/**
 * Class StorageService
 *
 * This is the root class for the MySQL/AWS-S3 implementation of the storage.
 * This class is dependent on the DatabaseService (there is no interface defined for database service, so we depend on it
 * directly).
 *
 * @package CodeLathe\Service\Storage\Implementations\MysqlS3
 */
class StorageService implements StorageServiceInterface
{

    /**
     * @var ItemDataStoreInterface
     */
    protected $itemDataStore;

    /**
     * @var BackstoreFactory
     */
    protected $backStoreFactory;

    public function __construct(ItemDataStoreInterface $itemDataStore, array $storageConfig)
    {
        $this->itemDataStore = $itemDataStore;
        $this->backStoreFactory = new BackstoreFactory($storageConfig);
    }

    public function exists(string $path): bool
    {
        return $this->itemDataStore->pathExists($path);
    }

    /**
     * @param string $path
     * @return FSEntryInterface
     * @throws NotFoundException
     */
    public function info(string $path): FSEntryInterface
    {
        if (($entry = $this->itemDataStore->getByPath($path)) === null) {
            throw new NotFoundException($path);
        }
        return $entry;

    }

    /**
     * @param string $path
     * @param string|null $cursorPath
     * @param bool $inDepth
     * @param array|null $idWhitelist
     * @param string $sortBy
     * @param bool $sortDescendent
     * @param int|null $limitBefore
     * @param int|null $limitAfter
     * @param bool $ignoreFolders
     * @param array $extensionWhitelist
     * @param array $extensionBlacklist
     * @return array
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
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
                         array $extensionBlacklist = []): array
    {

        // validations
        if (!in_array($sortBy, ['name', 'updated_on'])) {
            throw new StorageServiceException('Invalid sort option');
        }
        $sortBy = $sortBy === 'updated_on' ? 'modificationdate' : $sortBy;


        $rootRow = $this->itemDataStore->getByPath($path);

        if ($rootRow === null) {
            throw new NotFoundException($path);
        }
        if (!($rootRow instanceof FolderInterface)) {
            throw new NotAFolderException($path);
        }

        return $this->itemDataStore->list((int) $rootRow->getId(),
            $path,
            $cursorPath,
            $inDepth,
            $idWhitelist,
            $sortBy,
            $sortDescendent,
            $limitBefore,
            $limitAfter,
            $ignoreFolders,
            $extensionWhitelist,
            $extensionBlacklist);
    }

    /**
     * @param string $srcPath
     * @param string $dstPath
     * @return array
     * @throws DestinyPathAlreadyExistsException
     * @throws InvalidPathException
     * @throws NotAFolderException
     * @throws NotFoundException
     */
    protected function copyMoveValidate(string $srcPath, string $dstPath): array
    {
        // try to grab the source entry
        $srcEntry = $this->itemDataStore->getByPath($srcPath);

        // source entry must exist
        if ($srcEntry === null) {
            throw new NotFoundException($srcPath);
        }

        // destiny path must not exist!
        $dstEntry = $this->itemDataStore->getByPath($dstPath);
        if ($dstEntry instanceof FSEntryInterface) {
            throw new DestinyPathAlreadyExistsException($dstPath);
        }

        // strip destiny name from the destiny folder
        if (!preg_match('/(.*?)\/([^\/]+)$/', $dstPath, $matches)) {
            throw new InvalidPathException($dstPath);
        }
        $dstFolder = $matches[1];
        $dstName = $matches[2];

        // destiny folder must exist
        $dstFolderEntry = $this->itemDataStore->getByPath($dstFolder);
        if ($dstFolderEntry === null) {
            throw new NotFoundException($dstFolder);
        }
        if (!($dstFolderEntry instanceof Folder)) {
            throw new NotAFolderException($dstFolder);
        }

        return [$srcEntry, $dstFolderEntry, $dstName];
    }

    /**
     * @param FSEntryInterface $srcEntry
     * @param FSEntryInterface $dstEntry
     * @return bool
     * @throws StorageServiceException
     */
    protected function mergeMove(FSEntryInterface $srcEntry, FSEntryInterface $dstEntry): bool
    {

        if (get_class($srcEntry) !== get_class($dstEntry)) {
            throw new StorageServiceException('Cannot merge a file to a folder or a folder to a file');
        }

        if ($dstEntry instanceof FileInterface) {

            // delete the destiny file
            $this->deleteEntry($dstEntry);

            // continue with the file move
            return true;

        }

        if ($dstEntry instanceof FolderInterface) {

            // list all items from the folder, and recursively move them
            // loop to ensure everything will be copied (even if we have more entries then the default page size)
            $cursor = null;
            $entries = [];
            while (true) {
                $newEntries = $this->itemDataStore->list($srcEntry->getId(), $srcEntry->getPath(), $cursor);
                if (empty($newEntries)) {
                    break;
                }
                $entries = array_merge($entries, $newEntries);
                $cursor = $newEntries[count($newEntries)-1]->getPath();
            }
            foreach ($entries as $entry) {
                $this->move($entry->getPath(), "{$dstEntry->getPath()}/{$entry->getName()}", true);
            }

            // force delete the source directory (it's already on destiny), and stop
            $this->deleteEntry($srcEntry, true);
            return false;
        }

        // if the entry is anything different from folder or file, halt (impossible)
        throw new StorageServiceException('Can only move folder os files');

    }

    /**
     * @param string $srcPath
     * @param string $dstPath
     * @param bool $merge
     * @throws DestinyPathAlreadyExistsException
     * @throws InvalidPathException
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function move(string $srcPath, string $dstPath, bool $merge = false): void
    {

        // try to grab the source entry
        $srcEntry = $this->itemDataStore->getByPath($srcPath);

        // source entry must exist
        if ($srcEntry === null) {
            throw new NotFoundException($srcPath);
        }

        // destiny path exists?
        $dstEntry = $this->itemDataStore->getByPath($dstPath);
        if ($dstEntry instanceof FSEntryInterface) {
            if (!$merge) {
                throw new DestinyPathAlreadyExistsException($dstPath);
            }

            // handle the merge
            if (!$this->mergeMove($srcEntry, $dstEntry)) {
                return;
            }
        }

        // strip destiny name from the destiny folder
        if (!preg_match('/(.*?)\/([^\/]+)$/', $dstPath, $matches)) {
            throw new InvalidPathException($dstPath);
        }
        $dstFolder = $matches[1];
        $dstName = $matches[2];

        // destiny folder must exist
        $dstFolderEntry = $this->itemDataStore->getByPath($dstFolder);
        if ($dstFolderEntry === null) {
            throw new NotFoundException($dstFolder);
        }
        if (!($dstFolderEntry instanceof Folder)) {
            throw new NotAFolderException($dstFolder);
        }

        try {
            $this->itemDataStore->move($srcEntry->getId(), $dstFolderEntry->getId(), $dstName);
        } catch (StorageDatabaseException $e) {
            throw new StorageServiceException('Failed to move the file.');
        }
    }

    /**
     * @param string $srcPath
     * @param string $dstPath
     * @throws DestinyPathAlreadyExistsException
     * @throws InvalidPathException
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function copy(string $srcPath, string $dstPath): void
    {
        /**
         * @var FSEntryInterface $srcEntry
         * @var FolderInterface $dstFolderEntry
         * @var string $dstName
         */
        [$srcEntry, $dstFolderEntry, $dstName] = $this->copyMoveValidate($srcPath, $dstPath);

        if ($srcEntry instanceof FileInterface) {

            // store copy the file on the backstorage
            // TODO - Here we do whatever we need to do use the old backstore implementation because we're not
            // TODO - refactoring the backstore now.
            // TODO - In the future we need to change this piece of code to use the Backstore factory.
            $backstoreImpl = AbstractBackstore::getInstance(StorageServiceConfig::getDefaultStorageZoneID());
            $srcParams = [
                StorageObject::OBJECT_PARENTPATH => $srcEntry->getParentPath(),
                StorageObject::OBJECT_NAME => $srcEntry->getName(),
                StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
                //StorageObject::OBJECT_SITEID => (string)$srcEntry->getId(),
            ];
            $srcStorageObject = StorageObject::createStorageObject($srcParams);
            $srcStorageObject->storagepath = $srcEntry->getStoragePath();
            $dstParams = [
                StorageObject::OBJECT_PARENTPATH => $dstFolderEntry->getPath(),
                StorageObject::OBJECT_NAME => $dstName,
                StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
                //StorageObject::OBJECT_SITEID => (string)$toObjectID
            ];
            $dstStorageObject = StorageObject::createStorageObject($dstParams);
            $fileId = StorageServiceUtility::generateUniqueID();
            $dstStorageObject->setFileId($fileId);

            $backstoreImpl->storeFile($dstStorageObject, $srcStorageObject);
            $newFileId = $dstStorageObject->getFileId();
            $newStoragePath = $dstStorageObject->getFileStoragePath();
            // ---------------------

            // save it to the database
            try {
                $this->itemDataStore->copy($srcEntry->getId(), $dstFolderEntry->getId(), $dstName, $newFileId, $newStoragePath);
            } catch (StorageDatabaseException $e) {
                throw new StorageServiceException('Failed to copy the file.');
            }
            return;
        }

        // handle folder copy (in depth search)
        if ($srcEntry instanceof FolderInterface) {

            // first create the new path on the database
            try {
                $this->itemDataStore->copy($srcEntry->getId(), $dstFolderEntry->getId(), $dstName);
            } catch (StorageDatabaseException $e) {
                throw new StorageServiceException('Failed to copy the file.');
            }

            // list all items from the folder, and recursively copy them
            // loop to ensure everything will be copied (even if we have more entries then the default page size)
            $cursor = null;
            while (true) {
                $entries = $this->itemDataStore->list($srcEntry->getId(), $srcEntry->getPath(), $cursor);
                if (empty($entries)) {
                    break;
                }
                foreach ($entries as $entry) {
                    $this->copy($entry->getPath(), "{$dstFolderEntry->getPath()}/$dstName/{$entry->getName()}");
                    $cursor = $entry->getPath();
                }
            }
            return;
        }

        // if the entry is anything different from folder or file, halt (impossible)
        throw new StorageServiceException('Only files and folders can be copied');
    }

    /**
     * @param string $name
     * @param string $owner
     * @return int
     * @throws DestinyPathAlreadyExistsException
     */
    public function createRoot(string $name, string $owner): int
    {
        // final path, must not exist
        if ($this->itemDataStore->pathExists("/$name")) {
            throw new DestinyPathAlreadyExistsException("/$name");
        }

        return $this->itemDataStore->createFolder(null, $name, $owner);
    }

    /**
     * @param string $path
     * @param string $name
     * @param string $owner
     * @return int
     * @throws DestinyPathAlreadyExistsException
     * @throws NotFoundException
     * @throws NotAFolderException
     */
    public function createFolder(string $path, string $name, string $owner): int
    {
        // final path, must not exist
        if ($this->itemDataStore->pathExists("$path/$name")) {
            throw new DestinyPathAlreadyExistsException("$path/$name");
        }

        // parent path must exist and be a folder
        $parentFolder = $this->itemDataStore->getByPath($path);
        if ($parentFolder === null) {
            throw new NotFoundException($path);
        }
        if (!($parentFolder instanceof Folder)) {
            throw new NotAFolderException($path);
        }

        return $this->itemDataStore->createFolder($parentFolder->getId(), $name, $owner);
    }

    /**
     * @param string $path
     * @param string|null $versionId
     * @param string|null $downloadType Can be 'redirect', 'local' or 'stream'. 'redirect' by default
     * @return Download
     * @throws NotAFileException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function download(string $path, ?string $versionId = null, ?string $downloadType = null): Download
    {

        // path must exist and be a file
        $file = $this->itemDataStore->getByPath($path, $versionId);
        if ($file === null) {
            throw new NotFoundException($path);
        }
        if (!($file instanceof FileInterface)) {
            throw new NotAFileException($path);
        }

        $downloadType = $downloadType ?? 'redirect';
        if (!in_array($downloadType, ['redirect', 'local', 'stream'])) {
            throw new StorageServiceException('Invalid download type');
        }

        // TODO - From here we do whatever we need to do use the old backstore implementation because we're not
        // TODO - refactoring the backstore now.
        // TODO - In the future we need to change this piece of code to use the Backstore factory.
        \CodeLathe\Service\Storage\StorageService::$registry = ContainerFacade::get(ConfigRegistry::class);
        \CodeLathe\Service\Storage\StorageService::$logger = ContainerFacade::get(LoggerInterface::class);
        $backstoreImpl = AbstractBackstore::getInstance($file->getStorageZoneId());
        $srcDBObject = new StorageObject();
        $srcDBObject->name = $file->getName();
        $srcDBObject->type = 'file';
        $srcDBObject->storagepath = $file->getStoragePath();
        $srcDBObject->size = $file->getFileSize();
        $srcitem = [
            StorageObject::OBJECT_PARENTPATH => $file->getParentPath(),
            StorageObject::OBJECT_NAME => $file->getName(),
            //StorageObject::OBJECT_SITEID => (string)$objectID,
            //StorageObject::OBJECT_VERSIONIDENTFIER => $versionId
        ];
        switch ($downloadType) {
            case 'redirect':
                $srcitem[StorageObject::OBJECT_DL_RESPONSETYPE] = ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT;
                break;
            case 'local':
                $srcitem[StorageObject::OBJECT_DL_RESPONSETYPE] = ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD;
                break;
            case 'stream':
                $srcitem[StorageObject::OBJECT_DL_RESPONSETYPE] = ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM;
                break;
        }
        $requestObject = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $srcitem);
        $downloadResponse = $backstoreImpl->createDownloadResponse($srcDBObject, $requestObject);

        // handle the response object type
        switch ($downloadResponse->getResponseType())
        {
            case ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT:
                return new Download('redirect', ['url' => $downloadResponse->getDownloadInfo()]);
            case ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD:
                $payload = array_values($downloadResponse->getDownloadInfo() ?? [])[0] ?? [];
                return new Download('local', $payload);
            case ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM:
                return new Download('stream', [
                    'stream' => $downloadResponse->getDownloadInfo(),
                    'extension' => $file->getExtension(),
                    'size' => $file->getFileSize()
                ]);
        }

        throw new StorageServiceException('Invalid response type');

    }

    /**
     * @param string $path
     * @param string $sidecarMetadata
     * @param string|null $downloadType
     * @return Download
     * @throws NotFoundException
     */
    public function downloadSidecar(string $path, string $sidecarMetadata, ?string $downloadType = null): Download
    {

        // path must exist
        $sidecarFile = $this->itemDataStore->getSidecarByPath($path, $sidecarMetadata);
        if ($sidecarFile === null) {
            throw new NotFoundException($path);
        }

        // TODO - From here we do whatever we need to do use the old backstore implementation because we're not
        // TODO - refactoring the backstore now.
        // TODO - In the future we need to change this piece of code to use the Backstore factory.
        $backstoreImpl = AbstractBackstore::getInstance($sidecarFile->getStorageZoneId());
        $srcDBObject = new StorageObject();
        $srcDBObject->name = $sidecarFile->getName();
        $srcDBObject->type = 'file';
        $srcDBObject->storagepath = $sidecarFile->getStoragePath();
        $srcDBObject->size = $sidecarFile->getFileSize();
        $srcitem = [
            StorageObject::OBJECT_PARENTPATH => $sidecarFile->getParentPath(),
            StorageObject::OBJECT_NAME => $sidecarFile->getName(),
        ];
        switch ($downloadType) {
            case 'redirect':
                $srcitem[StorageObject::OBJECT_DL_RESPONSETYPE] = ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT;
                break;
            case 'local':
                $srcitem[StorageObject::OBJECT_DL_RESPONSETYPE] = ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD;
                break;
            case 'stream':
                $srcitem[StorageObject::OBJECT_DL_RESPONSETYPE] = ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM;
                break;
        }
        $requestObject = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $srcitem);
        $downloadResponse = $backstoreImpl->createDownloadResponse($srcDBObject, $requestObject);

        // handle the response object type
        switch ($downloadResponse->getResponseType())
        {
            case ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT:
                return new Download('redirect', ['url' => $downloadResponse->getDownloadInfo()]);
            case ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD:
                $payload = array_values($downloadResponse->getDownloadInfo() ?? [])[0] ?? [];
                return new Download('local', $payload);
            case ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM:
                return new Download('stream', []); // TODO - Properly handle strem downloads...
        }

        throw new StorageServiceException('Invalid response type');

    }

    /**
     * @param string $parentPath
     * @param string $name
     * @param string $localFilePath
     * @param int $chunkStart
     * @param bool $finalChunk
     * @param string $owner
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
                               string $owner): ?FileInterface
    {

        // parent path must exist and be a folder
        $parentFolder = $this->itemDataStore->getByPath($parentPath);
        if ($parentFolder === null) {
            throw new NotFoundException($parentPath);
        }
        if (!($parentFolder instanceof Folder)) {
            throw new NotAFolderException($parentPath);
        }

        // local file must exist
        if (!file_exists($localFilePath)) {
            throw new StorageServiceException("Local file not found");
        }

        // extract extension and size from local file
        if (($size = filesize($localFilePath)) === false) {
            throw new StorageServiceException("Impossible to get file size");
        }
        $extension = StorageServiceUtility::getPathExtension($localFilePath);

        // check if the file already exists
        $currentFile = $this->itemDataStore->getEntry($parentFolder->getId(), $parentFolder->getPath(), $name);

        // if file exists, it must be a file (not a folder)
        if ($currentFile !== null && !($currentFile instanceof FileInterface)) {
            throw new DestinyPathAlreadyExistsException($currentFile->getPath());
        }

        // TODO - Here we do whatever we need to do use the old backstore implementation because we're not
        // TODO - refactoring the backstore now.
        // TODO - In the future we need to change this piece of code to use the Backstore factory.
        $backstoreImpl = AbstractBackstore::getInstance(StorageServiceConfig::getDefaultStorageZoneID());
        $storageObjectParams = [
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => $parentFolder->getPath(),
            StorageObject::OBJECT_NAME => $name,
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_FILE,
            StorageObject::OBJECT_OWNER => (string)$owner,
            StorageObject::OBJECT_SRCPATH => $localFilePath,
            StorageObject::OBJECT_CHUNKSTART => $chunkStart,
            StorageObject::OBJECT_COMPLETE => $finalChunk,
            //StorageObject::OBJECT_SITEID => (string)$objectID
        ];

        $fileObject = StorageObject::createStorageObject($storageObjectParams);
        $fileId = StorageServiceUtility::generateUniqueID();
        $fileObject->setFileId($fileId);

        // if the current file already exists and the file is completed
        if ($currentFile !== null && $currentFile->isComplete()) {
            // change the file storage path (it's a new version of the existing file)
            $fileObject->setFileStorageName($currentFile->getFileId() . '_' . $fileId);
        }

        // if current file already exists and is not completed, send the partial upload. otherwise, just store the file
        if ($chunkStart !== 0 && $currentFile !== null && !$currentFile->isComplete()) {
            $fileObject->setFileStorageName($currentFile->getFileId());
            $fileObject->setFileStoragePath($currentFile->getStoragePath());
            $fileObject->setBackstoredata(json_decode($currentFile->getBackstoreData(), true));
            if (!$backstoreImpl->addToExistingFile($fileObject)) {
                throw new StorageServiceException('Backstore failure...');
            }
            $backstoreData = $fileObject->getBackstoredataAsString();
        } else {
            if (!$backstoreImpl->storeFile($fileObject)) {
                throw new StorageServiceException('Backstore failure...');
            }
            $backstoreData = $fileObject->getBackstoredataAsString();
        }
        // -------------------

        // store the uploaded file to the database
        // if the file is the start of a new file ...
        if ($chunkStart === 0) {

            // check if the file already exists
            if ($currentFile !== null) {

                // if it's a complete file, version it, if  not, delete it
                if ($currentFile->isComplete()) {
                    if (!$this->itemDataStore->createVersion($currentFile)) {
                        throw new StorageServiceException('Error creating version for file');
                    }
                } else {
                    try {
                        $this->deleteEntry($currentFile);
                    } catch (StorageServiceException $e) {
                        throw new StorageServiceException('Error deleting incomplete file');
                    }

                }

            }

            // create the file entry on database
            try {
                $fileId = $this->itemDataStore->createFile($parentFolder->getId(),
                    $name,
                    $owner,
                    $finalChunk,
                    $size,
                    $fileObject->getFileId(),
                    $fileObject->getFileStoragePath(),
                    $fileObject->getStorageZoneId(),
                    $backstoreData,
                    $extension);
            } catch (StorageDatabaseException $e) {
                throw new StorageServiceException($e->getMessage());
            }

        } else {

            // update database entry with the progress
            try {
                $fileId = $this->itemDataStore->updateFileProgress($parentFolder->getId(), $name, $finalChunk, $size, $backstoreData);
            } catch (StorageDatabaseEntryNotFoundException $e) {
                throw new NotFoundException("Failed uploading the chunk, there is no previous upload.");
            }

        }

        $file = $this->itemDataStore->getEntryById($fileId, $parentPath);

        if (!($file instanceof FileInterface)) {
            throw new StorageServiceException('Uploaded item is not a file');
        }

        return $file->isComplete() ? $file : null;

    }

    /**
     * @param string $mainPath
     * @param string $sideCarMetaData
     * @param string $localThumbFile
     * @throws NotAFolderException
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function uploadSidecar(string $mainPath, string $sideCarMetaData, string $localThumbFile): void
    {
        // main file must exist and be a live file (not a old version
        $mainFile = $this->itemDataStore->getByPath($mainPath);
        if ($mainFile === null) {
            throw new NotFoundException($mainPath);
        }
        if (!($mainFile instanceof File)) {
            throw new NotAFolderException($mainPath);
        }
        // TODO - validate if the file is a old version (it can't be)

        // extract extension and size from local file
        if (($size = filesize($localThumbFile)) === false) {
            throw new StorageServiceException("Impossible to get file size");
        }
        $extension = StorageServiceUtility::getPathExtension($localThumbFile);

        // TODO - Here we do whatever we need to do use the old backstore implementation because we're not
        // TODO - refactoring the backstore now.
        // TODO - In the future we need to change this piece of code to use the Backstore factory.
        preg_match('/(.*?)\/([^\/]+)$/', $mainPath, $matches);
        $backstoreImpl = AbstractBackstore::getInstance(StorageServiceConfig::getDefaultStorageZoneID());
        $storageObjectParams = [
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => $matches[1],
            StorageObject::OBJECT_NAME => $mainFile->getName(),
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_SIDECARFILE,
            StorageObject::OBJECT_SRCPATH => $localThumbFile,
            StorageObject::OBJECT_CHUNKSTART => 0,
            StorageObject::OBJECT_COMPLETE => 1,
        ];

        $fileObject = StorageObject::createStorageObject($storageObjectParams);
        $fileId = StorageServiceUtility::generateUniqueID();
        $fileObject->setFileId($fileId);

        if (!$backstoreImpl->storeFile($fileObject)) {
            throw new StorageServiceException('Backstore failure...');
        }
        // -------------------

        // store the uploaded file to the database
        try {
            $this->itemDataStore->createFile($mainFile->getParentId(),
                $mainFile->getName(),
                $mainFile->getOwner(),
                true,
                $size,
                $fileObject->getFileId(),
                $fileObject->getFileStoragePath(),
                $fileObject->getStorageZoneId(),
                null,
                $extension,
                $sideCarMetaData);
        } catch (StorageDatabaseException $e) {
            throw new StorageServiceException($e->getMessage());
        }

    }

    /**
     * @param FSEntryInterface $entry
     * @param bool $force
     * @throws StorageServiceException
     */
    protected function deleteEntry(FSEntryInterface $entry, bool $force = false): void
    {
        // TODO - delete from backstorage

        try {
            $this->itemDataStore->deleteEntry($entry->getId(), $force);
        } catch (StorageDatabaseException $e) {
            throw new StorageServiceException($e->getMessage());
        }
    }


    /**
     * @param string $path
     * @param bool $children
     * @throws NotFoundException
     * @throws StorageServiceException
     */
    public function delete(string $path, bool $children = false): void
    {
        if (($entry = $this->itemDataStore->getByPath($path)) === null) {
            throw new NotFoundException($path);
        }

        if ($children) {
            // TODO - list all children
            // TODO - delete each one...
        }

        $this->deleteEntry($entry);
    }

    /**
     * @param string $path
     * @return FolderProperties
     * @throws NotFoundException
     */
    public function folderProperties(string $path): FolderProperties
    {
        $entry = $this->itemDataStore->getByPath($path);

        // entry must exist and be a folder
        if ($entry === null || !($entry instanceof Folder)) {
            throw new NotFoundException($path);
        }

        return $this->itemDataStore->calculateFolderSizes($entry->getId());
    }

    /**
     * @param string $path
     * @return FileInterface[]
     * @throws NotFoundException
     */
    public function versions(string $path): array
    {
        if (($entry = $this->itemDataStore->getByPath($path)) === null) {
            throw new NotFoundException($path);
        }

        return $this->itemDataStore->getVersions($entry->getParentId(), $entry->getParentPath(), $entry->getName());

    }

    /**
     * @param string $path
     * @param string $sideCarMetaData
     * @return SidecarInterface
     * @throws NotFoundException
     */
    public function infoSideCar(string $path, string $sideCarMetaData): SidecarInterface
    {

        if (($entry = $this->itemDataStore->getSidecarByPath($path, $sideCarMetaData)) === null) {
            throw new NotFoundException($path);
        }
        return $entry;
    }
}