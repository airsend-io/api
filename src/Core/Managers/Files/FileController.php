<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Files;

use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Objects\FileSystemObject;
use CodeLathe\Core\Objects\FolderProps;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Utility\Image;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\StorageService;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Psr7\Stream;

/**
 * Class FileController
 *
 * FileController wraps the Storage Service
 * Application code should not use FileController but only use ASFileSystem
 *
 * @package CodeLathe\Core\Files
 * @deprecated - logic from this class must be rewrite inside the storage service
 */
class FileController
{
    protected $logger;
    protected $ss;

    public function __construct(LoggerInterface $logger, StorageService $ss)
    {
        $this->ss = $ss;
        $this->logger = $logger;
    }

    /**
     * Upload a File
     *
     * @param string $fsparent
     * @param string $fsname
     * @param string $fpath
     * @param int $chunkstart
     * @param int $chunkcomplete
     * @return bool
     */
    public function upload(string $fsparent, string $fsname, string $fpath, $chunkstart = 0, $chunkcomplete = 1, int $userid = 0, $sidecarMeta = "")
    {
        try {
            $fres = ResourceManager::getResource($fsparent);
            $objectID = $fres->getResourceObjectID();
            $fsparent = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ". $fsparent."/".$fsname);
            return false;
        }

        $this->logger->info(__CLASS__.":".__FUNCTION__. " Uploading ". $fsparent."/".$fsname." chunk start = ".$chunkstart. " complete = ".$chunkcomplete);
        $upfile = array(
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID(),
            StorageObject::OBJECT_PARENTPATH => $fsparent,
            StorageObject::OBJECT_NAME => $fsname,
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_FILE,
            StorageObject::OBJECT_OWNER => (string)$userid,
            StorageObject::OBJECT_SRCPATH => $fpath,
            StorageObject::OBJECT_CHUNKSTART => $chunkstart,
            StorageObject::OBJECT_COMPLETE => $chunkcomplete,
            StorageObject::OBJECT_SITEID => (string)$objectID
        );

        if (strlen($sidecarMeta) > 0) {
            $upfile[StorageObject::OBJECT_SIDECARMETADATA] = $sidecarMeta;
            $upfile[StorageObject::OBJECT_TYPE] = StorageObject::OBJECTTYPE_SIDECARFILE;
        }

        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $upfile);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Upload for ". $fsparent."/".$fsname);
            return false;
        }


        return true;
    }

    /**
     * Download a file
     *
     * @param string $fspath
     * @param string $resource
     * @param string $type
     * @return bool
     * @deprecated
     */
    public function download(string $fspath, string $versionid, string &$resource, string &$type )
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID
        );

        if (strlen($versionid) > 0)
            $srcitem[StorageObject::OBJECT_VERSIONIDENTFIER] = $versionid;

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->debug(__CLASS__.":".__FUNCTION__. " Error: Failed Download for ". $fspath);
            return false;
        }
        if ($resp->getResponseType() == ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT)
        {
            $resource = $resp->getDownloadInfo();
            $type = "redirect";
        }

        return true;
    }

    /**
     * Delete a file
     *
     * @param string $fspath
     * @return bool
     */
    public function delete(string $fspath, bool $updateSyncVersion = true) : bool
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID
        );

        $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECT, $srcitem);
        if (!$updateSyncVersion)
        {
            $req->setDonotUpdateDstSyncVersion(true); // ... This will not update or propagate sync version
        }
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Delete for ". $fspath);
            return false;
        }

        return true;
    }

    private function createInternal(string $fsparent, string $fsname, int $userid, $updateSyncVersion = true)
    {
        $this->logger->debug("FileController::createInternal creating path: ". $fsparent." ".$fsname);
        $reqItem = array(
            StorageObject::OBJECT_PARENTPATH => $fsparent,
            StorageObject::OBJECT_NAME => $fsname,
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_FOLDER,
            StorageObject::OBJECT_OWNER => (string) $userid,
            StorageObject::OBJECT_STORAGEZONEID => StorageServiceConfig::getDefaultStorageZoneID()
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_ADDOBJECT, $reqItem);
        if (!$updateSyncVersion)
        {
            $req->setDonotUpdateDstSyncVersion(true); // ... This will not update or propagate sync version
        }
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Create Folder for ". $fsparent." ".$fsname);
            return false;
        }

        return true;
    }

    /**
     * Create a new folder
     *
     * @param string $fsparent
     * @param string $fsname
     * @throws UnknownResourceException
     * @return bool
     */
    public function create(string $fsparent, string $fsname, int $userid) : bool
    {

        try {
            $fres = ResourceManager::getResource($fsparent);
            $fsparent = $fres->transform();

        } catch (UnknownResourceException $e) {
            $this->logger->error("Unknown Resource Exception for provided path: ".$fsparent);
            return false;
        }

        return $this->createInternal($fsparent, $fsname, $userid);
    }

    /**
     * Lists the contents of a folder
     *
     * @param string $fspath
     * @return array|bool
     */
    public function list(string $fspath, string $resourcePrefix = "", string $resourceDisplayPathPrefix = "", array $flags = array(), int $start = 0, int $limit = 300)
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECTLIST_OFFSET => $start,
            StorageObject::OBJECTLIST_LIMIT => $limit,
            StorageObject::OBJECT_SITEID => (string)$objectID
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFILELIST, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed List Folder for ". $fspath);
            return false;
        }

        $items = array();
        foreach ($resp->getStorageObjects() as $object )
        {
            $items[] = FileSystemObject::create($object, $resourcePrefix, $resourceDisplayPathPrefix, $this->getPathFlags($object->getObjectFullPath(), $object->getType()));
        }

        $returnArr = array('total' => $resp->getTotalRowCount(), 'items' => $items);
        return $returnArr;
    }

    /**
     * @param string $path
     * @param string $type
     * @param array|null $previousFlags
     * @return array
     * @deprecated
     */
    protected function getPathFlags(string $path, string $type, ?array $previousFlags = []): array
    {

        $previousFlags = $previousFlags ?? [];

        // set attachments folders as SYSTEM
        if ($type === 'folder' && preg_match('/^\/[0-9]+\/Channels\/[^\/]+\/files\/attachments$/', $path)) {
            return array_merge($previousFlags, ['SYSTEM' => true]);
        }

        // ... other system folders/files here

        return [];
    }

    /**
     * Internal function for copy or move
     *
     * @param string $fsfrompath
     * @param string $fstopath
     * @param bool $isCopy
     * @return bool
     */
    protected function copyOrMove(string $fsfrompath, string $fstopath, bool $isCopy, bool $updateSyncVersion = true)
    {
        try {
            $fres = ResourceManager::getResource($fsfrompath);
            $fromObjectID = $fres->getResourceObjectID();
            $fsfrompath = $fres->transform();

            $fres = ResourceManager::getResource($fstopath);
            $toObjectID = $fres->getResourceObjectID();
            $fstopath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fsfrompath." ".$fstopath);
            return false;
        }

        $frompath = Path::createFromPath($fsfrompath);
        $topath = Path::createFromPath($fstopath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $frompath->getParent(),
            StorageObject::OBJECT_NAME => $frompath->getName(),
            StorageObject::OBJECT_SITEID => (string)$fromObjectID
        );
        $tgtitem = array(
            StorageObject::OBJECT_PARENTPATH => $topath->getParent(),
            StorageObject::OBJECT_NAME => $topath->getName(),
            StorageObject::OBJECT_SITEID => (string)$toObjectID
        );

        $type = $isCopy ?  RequestObject::REQUEST_COPYOBJECT : RequestObject::REQUEST_MOVEOBJECT;
        $req = RequestObject::createRequest($type, $srcitem);
        $req->setDstStorageObject($tgtitem);
        if (!$updateSyncVersion)
        {
            $req->setDonotUpdateDstSyncVersion(true); // ... This will not update or propagate sync version
            $this->logger->debug(__CLASS__.":".__FUNCTION__. " Setting Do Not Update Sync Version for move from ". $fsfrompath." to ".$fstopath);
        }
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Copy or Move for ". $fsfrompath." to ".$fstopath);
            return false;
        }

        return true;
    }

    /**
     * Move a file to a new location
     *
     * @param string $fsfrompath
     * @param string $fstopath
     * @return bool
     */
    public function move(string $fsfrompath, string $fstopath, bool $updateSyncVersion = true)
    {
        return $this->copyOrMove($fsfrompath, $fstopath, false, $updateSyncVersion);
    }

    /**
     * Copy a file to a new location
     *
     * @param string $fsfrompath
     * @param string $fstopath
     * @return bool
     */
    public function copy(string $fsfrompath, string $fstopath)
    {
        return $this->copyOrMove($fsfrompath, $fstopath, true, false);
    }

    /**
     * Gets info related to a specific file or folder
     *
     * @param string $fspath
     * @return bool|\CodeLathe\Core\Objects\File|\CodeLathe\Core\Objects\Folder
     * @throws \Exception
     * @deprecated
     *
     */
    public function info(string $fspath,  string $resourcePrefix = "", string $resourceDisplayPathPrefix = "")
    {
        $this->logger->debug("FileController::info FSPath: ".$fspath);
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTINFO, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            //$this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Info for ". $fspath);
            return false;
        }

        $item = $resp->getStorageObject();
        if (!isset($item)) {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " failed to get Storage Object: ".$fspath);
            return false;
        }

        return FileSystemObject::create($item, $resourcePrefix, $resourceDisplayPathPrefix);
    }

    /**
     * Gets previous versions
     *
     * @throws NotImplementedException
     */
    public function versions(string $fspath, string $resourcePrefix = "")
    {

        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFILEVERSIONLIST, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Get Versions List for ". $fspath);
            return false;
        }

        $items = array();
        foreach ($resp->getStorageObjects() as $object )
        {
            $this->logger->debug(__CLASS__.":".__FUNCTION__. " Version ". print_r($object, true));
            $items[] = FileSystemObject::create($object, $resourcePrefix);
        }


        return $items;
    }

    /**
     * @param string $fspath
     * @param string $sidecarmetadata
     * @param string $resourcePrefix
     * @return \CodeLathe\Core\Objects\File|\CodeLathe\Core\Objects\Folder|false
     * @throws \Exception
     * @deprecated
     */
    public function infoSideCar(string $fspath, string $sidecarmetadata, string $resourcePrefix = "")
    {
        $this->logger->debug("FileController::info FSPath: ".$fspath);
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH =>  $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_SIDECARFILE,
            StorageObject::OBJECT_SIDECARMETADATA => $sidecarmetadata

        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETSIDECARINFO, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            //$this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed SideCar Info for ". $fspath);
            return false;
        }

        $item = $resp->getStorageObject();
        if (!isset($item)) {
            $this->logger->error(__CLASS__.":".__FUNCTION__ . " failed to get Storage Object: ".$fspath);
            return false;
        }

        return FileSystemObject::create($item, $resourcePrefix);
    }

    public function downloadSideCar(string $fspath, string $sidecarmetadata, string $contentType, string &$resource, string &$type )
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID,
            StorageObject::OBJECT_TYPE => StorageObject::OBJECTTYPE_SIDECARFILE,
            StorageObject::OBJECT_SIDECARMETADATA => $sidecarmetadata
        );

        if (strlen($contentType) > 0) {
            $srcitem[StorageObject::OBJECT_DL_HTTPCONTENTTYPE_OVERRIDE] = $contentType;
        }

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->info(__CLASS__.":".__FUNCTION__. " : Failed Download for ". $fspath);
            return false;
        }
        if ($resp->getResponseType() == ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT)
        {
            $resource = $resp->getDownloadInfo();
            $type = "redirect";
        }

        return true;
    }


    public function deleteAllSideCars(string $fspath)
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID
        );

        $req = RequestObject::createRequest(RequestObject::REQUEST_DELETEOBJECTSIDECARS, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Delete All Side Cars for ". $fspath);
            return false;
        }

        return true;
    }

    /**
     * @param string $fspath
     * @param string $fpath
     * @return bool
     * @deprecated
     */
    public function uploadSideCar(string $fspath, string $fpath)
    {
        $path = Path::createFromPath($fspath);
        $thumbwidth = 120; // ... TODO: Hardcoded
        $thumbheight = 120; // ... TODO: Hardcoded
        $sidecarMetadata = "THUMB|".$thumbwidth."x".$thumbheight;
        if (!$this->upload($path->getParent(), $path->getName(), $fpath,0, 1, 0, $sidecarMetadata))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Uploading Thumb Image for ". $fspath);
            // ... Send back standard image
            return false;
        }

        return true;
    }

    public function zip(string $fspath, string &$resource, string &$type)
    {
        $origFSPath = $fspath;
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }


    }

    /**
     * Useful to get a file locally on the filesystem
     * By default a stream is returned, otherwise a path to a local file is also possible
     * @param string $fspath
     * @param bool $isStream
     * @return string|Stream|null
     * @deprecated - replace with the new FS service implementation
     */
    public function downloadlocal(string $fspath, bool $isStream = true)
    {
        $origFSPath = $fspath;
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return null;
        }

        $path = Path::createFromPath($fspath);
        $pi = Path::mb_pathinfo($fspath);
        $fileext = "";
        if (isset($pi["extension"]))
            $fileext = mb_strtolower($pi["extension"]);

        $respType = ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM;
        if ($isStream == false)
            $respType = ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD;

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID,
            StorageObject::OBJECT_DL_RESPONSETYPE => $respType
        );

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Download for ". $fspath);
            return null;
        }
        if ($resp->getResponseType() != $respType)
            return null;

        $info = $resp->getDownloadInfo();
        if ($respType == ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD)
            return $info[0]['tmpfile'];

        return $info;
    }

    /**
     * @param string $fspath
     * @param int $thumbwidth
     * @param int $thumbheight
     * @param string $resource
     * @param string $type
     * @return bool
     * @throws \CodeLathe\Core\Exception\SecurityException
     * @deprecated
     */
    public function thumb(string $fspath, int $thumbwidth, int $thumbheight, string &$resource, string &$type)
    {
        $origFSPath = $fspath;
        try {
            $fres = ResourceManager::getResource($fspath);
            $objectID = $fres->getResourceObjectID();
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Resource Exception for path ".$fspath);
            return false;
        }

        $path = Path::createFromPath($fspath);
        $pi = Path::mb_pathinfo($fspath);
        $fileext = "";
        if (isset($pi["extension"]))
            $fileext = mb_strtolower($pi["extension"]);

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $path->getParent(),
            StorageObject::OBJECT_NAME => $path->getName(),
            StorageObject::OBJECT_SITEID => (string)$objectID,
            StorageObject::OBJECT_DL_RESPONSETYPE => ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD
        );

        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECT, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed Download for ". $fspath);
            return false;
        }
        if ($resp->getResponseType() != ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD)
            return false;

        $info = $resp->getDownloadInfo();

        $basename = bin2hex(random_bytes(8));// see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('tmp_thumb_%s.%0.8s', $basename, $fileext);
        $tgtFile = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
        if (!Image::resizeImage($info[0]['tmpfile'], $fileext, $thumbwidth, $thumbheight, $tgtFile)) {
            $this->logger->debug(__CLASS__.":".__FUNCTION__. " Failed Creating Thumb Image for ". $fspath);
            // ... Send back standard image
            return false;
        }
        $origpath = Path::createFromPath($origFSPath);
        $sidecarMetadata = "THUMB|".$thumbwidth."x".$thumbheight;
        if (!$this->upload($origpath->getParent(), $origpath->getName(), $tgtFile, 0, 1,0, $sidecarMetadata))
        {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Failed Uploading Thumb Image for ". $fspath);
            // ... Send back standard image
            return false;
        }

        SafeFile::unlink($tgtFile);
        return $this->downloadSideCar($origFSPath, $sidecarMetadata, "", $resource, $type);
    }


    /**
     * Creates all complete paths recursively given a full path
     *
     * @param string $fspath
     * @return bool
     * @deprecated
     */
    public function createAllPaths(string $fspath, int $userid, bool $updateSyncVersion = true)
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed create All Paths  ". $fspath);
            return false;
        }

        foreach (Path::getAllPaths($fspath) as $pathComponent) {
            $pc = Path::createFromPath($pathComponent);
            if (!$this->createInternal($pc->getParent(), $pc->getName(), $userid, $updateSyncVersion)) {
                $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed create folder  ". $pathComponent);
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $fspath
     * @return FolderProps|false
     * @deprecated
     */
    public function folderProps(string $fspath)
    {
        try {
            $fres = ResourceManager::getResource($fspath);
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed folder props transform exception  ". $fspath);
            return false;
        }


        if ($fspath == "/")
        {
            $parent = "";
            $name = "/";
        }
        else
        {
            $path = Path::createFromPath($fspath);
            $parent = $path->getParent();
            $name = $path->getName();
        }

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $parent,
            StorageObject::OBJECT_NAME => $name,
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETFOLDERPROPERTIES, $srcitem);
        try {
            $resp = $this->ss->service($req);
            if (!$resp->isSuccess()) {
                //$this->logger->debug(__CLASS__.":".__FUNCTION__. "  Failed folder props for ". $fspath);
                return false;
            }
        }
        catch (StorageServiceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: ". $e->getMessage());
            return false;

        }

        return FolderProps::withStorageObject($resp->getFolderPropertiesObject());
    }

    private function addUpdateMetaTags(string $fspath, string $type, array $metatags, bool $doAdd){
        try {
            $fres = ResourceManager::getResource($fspath);
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed add or update meta tags  ". $fspath);
            return false;
        }


        if ($fspath == "/")
        {
            $parent = "";
            $name = "/";
        }
        else
        {
            $path = Path::createFromPath($fspath);
            $parent = $path->getParent();
            $name = $path->getName();
        }

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $parent,
            StorageObject::OBJECT_NAME => $name,
            StorageObject::OBJECT_TYPE => $type,
            StorageObject::OBJECT_METATAGS => json_encode($metatags)
        );
        $reqType = RequestObject::REQUEST_SETOBJECTMETATAGS;
        if (!$doAdd)
            $reqType = RequestObject::REQUEST_UPDATEOBJECTMETATAGS;
        $req = RequestObject::createRequest($reqType, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed add or update metatags for ". $fspath);
            return false;
        }
        return true;
    }

    public function addMetaTags(string $fspath, string $type, array $metatags){
        $this->addUpdateMetaTags($fspath, $type, $metatags, true);
    }

    public function getMetaTags(string $fspath){
        try {
            $fres = ResourceManager::getResource($fspath);
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed get metatags for". $fspath);
            return false;
        }


        if ($fspath == "/")
        {
            $parent = "";
            $name = "/";
        }
        else
        {
            $path = Path::createFromPath($fspath);
            $parent = $path->getParent();
            $name = $path->getName();
        }

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $parent,
            StorageObject::OBJECT_NAME => $name,
        );
        $req = RequestObject::createRequest(RequestObject::REQUEST_GETOBJECTMETATAGS, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed get metatags for ". $fspath);
            return false;
        }
        $storageobject = $resp->getStorageObject();
        $jsonobject = json_decode($storageobject->getMetaTagsAsString());
        return $jsonobject;
    }

    public function updateMetaTags(string $fspath, array $metatags){
        $this->addUpdateMetaTags($fspath, $metatags, false);
    }

    public function deleteMetaTags(string $fspath){
        try {
            $fres = ResourceManager::getResource($fspath);
            $fspath = $fres->transform();
        } catch (UnknownResourceException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed delete metatags for  ". $fspath);
            return false;
        }


        if ($fspath == "/")
        {
            $parent = "";
            $name = "/";
        }
        else
        {
            $path = Path::createFromPath($fspath);
            $parent = $path->getParent();
            $name = $path->getName();
        }

        $srcitem = array(
            StorageObject::OBJECT_PARENTPATH => $parent,
            StorageObject::OBJECT_NAME => $name
        );
        $reqType = RequestObject::REQUEST_REMOVEOBJECTMETATAGS;
        $req = RequestObject::createRequest($reqType, $srcitem);
        $resp = $this->ss->service($req);
        if (!$resp->isSuccess()) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Failed delete metatags for ". $fspath);
            return false;
        }
        return true;
    }

}