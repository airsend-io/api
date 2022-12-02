<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage;


use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\Storage\Shared\ListQueryObject;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\DB\StorageServiceDB;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;


/**
 * Class StorageService
 * @package airstorage
 * @since
 * @deprecated
 */
class StorageWorker
{

    /** @var StorageServiceDB*/
    private $storageDB;

    /** @var bool  */
    private $skipVersionCheck = false;

    /**
     * StorageService constructor.
     * @param array $dbParams
     * @param string $domainId
     */
    public function __construct(FSDatabaseService $dbservice)
    {
        $this->storageDB = new StorageServiceDB($dbservice);
    }



    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function addObject(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_ADDOBJECT);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        $inStorageObject = $requestObject->getSrcStorageObject();
        if($inStorageObject->isFolder())
        {
            /** @var StorageObject $resultObject */
            $resultObject =  $this->addFolderObject($inStorageObject);
        } else if($inStorageObject->isSideCarFile()) {
            //sidecar file
            $resultObject = $this->addSidecarFileObject($inStorageObject);
        } else {
            //file
            $resultObject = $this->addFileObject($inStorageObject);
        }

        if(isset($resultObject)){
            $err = $resultObject->errorMessage();
            if(!isset($err)){
                //Added successfully in storage. Update sync version
                if(!$resultObject->isComplete() && !$resultObject->isFolder()){
                    $msg = "Partial object added successfully: ".$inStorageObject->getObjectFullPath();
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$msg);
                    return ResponseObject::createSuccessResponse($msg);
                } elseif($resultObject->alreadyExists() || $resultObject->isSideCarFile()
                    || $requestObject->isDonotUpdateDstSyncVersion()
                    || $this->storageDB->updateSyncVersionOnAdd($resultObject)){
                    $msg = "Object added successfully: ".$inStorageObject->getObjectFullPath();
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$msg);
                    return ResponseObject::createSuccessResponse($msg);
                } else{
                    $msg = "Object added but unable to update sync version: ".$inStorageObject->getObjectFullPath();
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                    return ResponseObject::createErrorResponse($msg);
                }
            }
            else{
                $msg = "Validation failed ".$err;
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }

        //should not reach here
        $msg = "Unknown error";
        StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
        return ResponseObject::createErrorResponse($msg);

    }

    /**
     * @param RequestObject $requestObject
     * @return bool
     */
    public function deleteObject(RequestObject $requestObject): ResponseObject
    {
        
        set_time_limit(0);

        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_DELETEOBJECT);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->info(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //get the DB record from the database
        $inStorageObject = $requestObject->getSrcStorageObject();
        $storageDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($inStorageObject->getParentPath(),
            $inStorageObject->getName());
        if(!isset($storageDBObject)){
            $msg = "No matching object found in storage ". $inStorageObject->getObjectFullPath();
            StorageService::logger()->info(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //check storage service health
        if(!StorageServiceUtility::isStorageBackendReady($storageDBObject->getStorageZoneId())){
            $msg = "Backend storage ". $storageDBObject->getStorageZoneId()." not ready.";
            StorageService::logger()->info(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        if($storageDBObject->isFolder()){
            if(!$this->deleteFolderObject($storageDBObject)){
                $msg = "Error deleting folder object ". $storageDBObject->getObjectFullPath();
                StorageService::logger()->info(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }
        else{
            //file
            if(!$this->deleteFileObject($storageDBObject)){
                $msg = "Error deleting file object ". $storageDBObject->getObjectFullPath();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }

        //Added successfully in storage. Update sync version if donotupdatesync is not selected
        if($requestObject->isDonotUpdateDstSyncVersion() ||
            $this->storageDB->updateSyncVersionOnDelete($storageDBObject)){
            //Request executed successfully.
            $msg = "Successfully deleted object ". $storageDBObject->getObjectFullPath();
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $msg = "Object deleted but unable to update sync version: ".$storageDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param RequestObject $requestObject
     * @return ResponseObject|null
     */
    public function getObjectList(RequestObject $requestObject) : ?ResponseObject
    {
        

        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETFILELIST);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //get the DB record from the database
        $inStorageObject = $requestObject->getSrcStorageObject();
        $storageDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName(
            $inStorageObject->getParentPath(), $inStorageObject->getName());
        if(!isset($storageDBObject)){
            $msg = "Source path not found ".$inStorageObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Get file list : ".$storageDBObject->getObjectFullPath());

        $childObjects = array();
        $listQueryObject = new ListQueryObject(array(
            "parentpath" => $storageDBObject->getObjectFullPath(),
            "recursive" => FALSE,
            "leafFirst" => FALSE,
            "offset" => $requestObject->getPageOffset(),
            "limit" => $requestObject->getPageLimit(),
        ));

        $rowcountHolder = 0;
        /* @var $childObject StorageObject*/
        foreach($this->storageDB->getLiveChildObjectsForPath($listQueryObject, $rowcountHolder) as $childObject ){

            if($childObject->isFolder()){
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": [Folder] : "
                //    . $childObject->getObjectFullPath());
            }else{
                //StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": [File] : "
                //    . $childObject->getObjectFullPath());
            }
            $childObjects[] = $childObject;
        }
        return ResponseObject::createGetFileListResponse($childObjects, $rowcountHolder);
    }

    /**
     * @param StorageObject
     * @return StorageObject
     */
    public function moveObject(RequestObject $requestObject): ResponseObject
    {
        
        set_time_limit(0);

        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_MOVEOBJECT);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $reqSrcStorageObject = $requestObject->getSrcStorageObject();
        /** @var StorageObject $reqDstObject */
        $reqDstObject = $requestObject->getDstStorageObject();

        //get the DB record from the database
        $srcObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($reqSrcStorageObject->getParentPath(),
            $reqSrcStorageObject->getName());
        if(!isset($srcObject)){
            $msg = "No matching object found in storage ".$reqSrcStorageObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //both src and destination are same
        if($srcObject->getParentPath() == $reqDstObject->getParentPath() &&
            $srcObject->getName() == $reqDstObject->getName()){
            $msg = "Source and destination are same : ". $reqDstObject->getParentPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        $tgtParentObject = $this->storageDB->getFolderObjectForPath($reqDstObject->getParentPath());
        if(!isset($tgtParentObject) && !$reqDstObject->isRootFolder())
        {
            $msg = "Invalid target parent folder : ". $reqDstObject->getParentPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //copy attribs
        $movesource = $srcObject;
        $reqDstObject->setOwner($movesource->getOwner());
        $reqDstObject->setType($movesource->getType());
        $reqDstObject->setStorageZoneId($movesource->getStorageZoneId());
        //set objectid as well as the moved object is same as the source and this id is used in updating sync versions
        $reqDstObject->setObjectId($movesource->getObjectId());

        //check storage service health
        if(!StorageServiceUtility::isStorageBackendReady($srcObject->getStorageZoneId())){
            $msg = "Backend storage ".$srcObject->getStorageZoneId()." not ready.";
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }


        if($srcObject->isFolder()){
            if(!$this->moveFolderObject($srcObject, $reqDstObject)){
                $msg = "Error moving folder object ". $srcObject->getObjectFullPath();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }
        else{
            if(!$this->moveFileObject($srcObject, $reqDstObject)){
                $msg = "Error moving file object ". $srcObject->getObjectFullPath();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }

        //Moved successfully in storage. Update sync version of source hierarchy
        $srcSyncUpdated = $this->storageDB->updateSyncVersionOnDelete($srcObject);
        if($requestObject->isDonotUpdateDstSyncVersion()){
            //no need to update destination.
            //this case will be true if the destination is recyclebin.
            if($srcSyncUpdated){
                //Request executed successfully.
                $msg = "Successfully moved object ". $srcObject->getObjectFullPath()
                    ." -> ". $reqDstObject->getObjectFullPath();
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createSuccessResponse($msg);
            } else {
                $msg = "Object moved but unable to update source hierarchy sync versions: "
                    . $srcObject->getObjectFullPath() ." -> ". $reqDstObject->getObjectFullPath();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }

        //Flow reaches here only if destination sync version is also opted to be updated
        //Moved successfully in storage. Update sync version of destination hierarchy
        if($this->storageDB->updateSyncVersionOnAdd($reqDstObject)){
            //Request executed successfully.
            $msg = "Successfully moved object ". $srcObject->getObjectFullPath()
                ." -> ". $reqDstObject->getObjectFullPath();
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $location = $srcSyncUpdated ? "destination" : "source and destination";
            $msg = "Object moved but unable to update $location hierarchy sync versions: "
                . $srcObject->getObjectFullPath() ." -> ". $reqDstObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param StorageObject
     * @return StorageObject
     */
    public function copyObject(RequestObject $requestObject): ResponseObject
    {
        set_time_limit(0);

        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_COPYOBJECT);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $reqSrcStorageObject = $requestObject->getSrcStorageObject();
        /** @var StorageObject $reqDstObject */
        $reqDstObject = $requestObject->getDstStorageObject();

        //get the DB record from the database
        $srcDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName(
            $reqSrcStorageObject->getParentPath(), $reqSrcStorageObject->getName());
        if(!isset($srcDBObject)){
            $msg = "No matching object found in DB ".$reqSrcStorageObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //both src and destination are same
        if($srcDBObject->getParentPath() == $reqDstObject->getParentPath() && $srcDBObject->getName() == $reqDstObject->getName()){
            $msg = "Source and destination are same : ". $reqDstObject->getParentPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);

        }

        //check if target parent path is valid
        $tgtParentDBObject = $this->storageDB->getFolderObjectForPath($reqDstObject->getParentPath());
        if(!isset($tgtParentDBObject) && !$reqDstObject->isRootFolder())
        {
            $msg = "Invalid target parent folder : ". $reqDstObject->getParentPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //copy attribs
        $copysource = $tgtParentObject??$srcDBObject;
        $reqDstObject->setOwner($copysource->getOwner());
        $reqDstObject->setType($copysource->getType());
        $reqDstObject->setStorageZoneId($copysource->getStorageZoneId());

        //check storage service health
        if(!StorageServiceUtility::isStorageBackendReady($srcDBObject->getStorageZoneId())){
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": "."Backend storage ".$srcDBObject->getStorageZoneId()." not ready.");
            return FALSE;
        }

        if($srcDBObject->isFolder()){
            if(!$this->copyFolderObject($srcDBObject, $reqDstObject, $requestObject->isOverwrite())){
                $msg = "Error copying folder object ". $srcDBObject->getObjectFullPath()
                    ." -> ". $reqDstObject->getObjectFullPath();;
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }
        else{
            if(!$this->copyFileObject($srcDBObject, $reqDstObject)){
                $msg = "Error copying file object ". $srcDBObject->getObjectFullPath()
                    ." -> ". $reqDstObject->getObjectFullPath();;
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
                return ResponseObject::createErrorResponse($msg);
            }
        }

        //Added successfully in storage. Update sync version
        if($this->storageDB->updateSyncVersionOnAdd($reqDstObject)){
            //Request executed successfully.
            $msg = "Successfully copied object ". $srcDBObject->getObjectFullPath()
                ." -> ". $reqDstObject->getObjectFullPath();
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $msg = "Object copied but unable to update sync version: ". $srcDBObject->getObjectFullPath()
            ." -> ". $reqDstObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param RequestObject
     * @return ResponseObject
     * @deprecated
     */
    public function getObject(RequestObject $requestObject): ResponseObject
    {
        set_time_limit(0);

        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETOBJECT);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $reqSrcStorageObject = $requestObject->getSrcStorageObject();

        //check if the request for a particular version
        if(!empty($reqSrcStorageObject->getObjectVersionIdentifier())){
            //get the DB record from the database
            $srcDBObject = $this->storageDB->getNonLiveFileObjectByID($reqSrcStorageObject);
            if (!isset($srcDBObject)) {
                $msg = "No matching object version found in DB " . $reqSrcStorageObject->getObjectFullPath()
                . "|" . $reqSrcStorageObject->getObjectVersionIdentifier();
            }
        } else if($reqSrcStorageObject->isSideCarFile()){
            //get the DB record from the database
            $srcDBObject = $this->storageDB->getSideCarFileObject($reqSrcStorageObject);
            if (!isset($srcDBObject)) {
                $msg = "No matching sidecar object found in DB "
                    . $reqSrcStorageObject->getObjectFullPath()
                    . "|" . $reqSrcStorageObject->getSidecarMetadataAsString();
            } else{
                //copy the HTTP content override if present
                $httpcontentoverride = $reqSrcStorageObject->getObjectDLHTTPContentTypeOverride();
                if(isset($httpcontentoverride)){
                    $srcDBObject->setObjectDLHTTPContentTypeOverride($httpcontentoverride);
                }
            }
        } else{
            //live file request
            //get the DB record from the database
            $srcDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName(
                $reqSrcStorageObject->getParentPath(), $reqSrcStorageObject->getName());
            if (!isset($srcDBObject)) {
                $msg = "No matching object found in DB " . $reqSrcStorageObject->getObjectFullPath();
            }
        }

        //if no $srcDBObject then return error
        if (!isset($srcDBObject)) {
            StorageService::logger()->debug(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //update last access date
        $this->storageDB->updateLastAccessDate($srcDBObject);

        //return download response
        $backstoreImpl = $srcDBObject->getStorageImplementation();
        return $backstoreImpl->createDownloadResponse( $srcDBObject, $requestObject);

    }

    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function getObjectInfo(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETOBJECTINFO);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $reqSrcStorageObject = $requestObject->getSrcStorageObject();

        //get the DB record from the database
        $srcDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName(
            $reqSrcStorageObject->getParentPath(), $reqSrcStorageObject->getName());
        if (!isset($srcDBObject)) {
            $msg = "No matching object found in DB " . $reqSrcStorageObject->getObjectFullPath();
            //StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //$msg = "Matching object found in DB " . $reqSrcStorageObject->getObjectFullPath();
        //StorageService::logger()->debug(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
        return ResponseObject::createGetFileInfoResponse($srcDBObject);
    }

    /**
     * @param StorageObject $fileObject
     * @return ResponseObject
     */
    public function getFileOldVersions(RequestObject $requestObject) : ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETFILEVERSIONLIST);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $fileObject */
        $fileObject = $requestObject->getSrcStorageObject();

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Getting old versions : " . $fileObject->getObjectFullPath());
        $nonLiveObjects = $this->storageDB->getNonLiveFileObjects($fileObject);
        return ResponseObject::createGetFileListResponse($nonLiveObjects);
    }


    /**
     * @param StorageObject $inFolderObject
     * @param bool $caseInsensitiveCheck
     * @param array $additionalOptions
     * @return StorageObject|null
     */
    protected function addFolderObject(StorageObject $inFolderObject, $caseInsensitiveCheck = FALSE,
                                       array $additionalOptions = array()): ?StorageObject
    {
        
        //unpack additional options here

        //check if parent path is valid
        if(!$inFolderObject->isRootFolder())
        {
            //validate parent path
            $parentStorageObject = $this->storageDB->getFolderObjectForPath($inFolderObject->getParentPath());
            if(!isset($parentStorageObject))
            {
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Invalid parent object : ". $inFolderObject->errorMessage());
                return null;
            }
        }

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Adding folder ". $inFolderObject->getObjectFullPath());

        //check if folder already exists
        $existingStorageObject = $this->storageDB->getLiveStorageObjectForParentPathAndName(
            $inFolderObject->getParentPath(), $inFolderObject->getName(), $caseInsensitiveCheck);

        if(isset($existingStorageObject))
        {
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Folder already exists ". $inFolderObject->getObjectFullPath());
            $existingStorageObject->setAlreadyExists();
            return $existingStorageObject;
        }

        //prepare folder object
        $inFolderObject->setFileId(StorageServiceUtility::generateUniqueID());

        //add object to database
        $result = $this->storageDB->addFolderObject($inFolderObject);
        if($result){
            return $inFolderObject;
        } else{
            return StorageObject::createErrorObject("Error saving folder ".
                $inFolderObject->getObjectFullPath());
        }
    }

    /**
     * @param StorageObject $inFileObject
     * @param array $additionalOptions
     * @return StorageObject|null
     */
    private function addFileObject(StorageObject $inFileObject, array $additionalOptions = array()): ?StorageObject
    {
        

        $fileID = StorageServiceUtility::generateUniqueID();
        $fileVersioned = FALSE;
        $fileUpdated = FALSE;

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Adding file ".$inFileObject->getObjectFullPath());

        //check if parent folder is valid
        $parentStorageObj = $this->storageDB->getFolderObjectForPath($inFileObject->getParentPath());
        if(!isset($parentStorageObj))
        {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Error saving file. Invalid folder ".
                $inFileObject->getParentPath());
            return StorageObject::createErrorObject("Error saving file. Invalid parent folder ".
                $inFileObject->getParentPath());
        }

        StorageService::logger()->debug("Storing File(file, start, complete): (" .
            $inFileObject->getObjectFullPath().",".
            $inFileObject->getFileChunkStart().",".
            $inFileObject->isComplete().")");

        /** @var StorageObject $partialFileObject */
        /** @var StorageObject $liveFileObject */
        list($liveFileObject, $partialFileObject) = $this->storageDB->getLiveAndPartialFileObjectForParentPathAndName(
            $inFileObject->getParentPath(), $inFileObject->getName());

        if($inFileObject->isFileStart())
        {
            //start of a new file
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Creating new file: " . $inFileObject->getObjectFullPath());

            //check if there are any existing partial uploads for this item
            if(isset($partialFileObject)){
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removing existing partial file: " .
                    $inFileObject->getObjectFullPath());
                //this is a partial file. so dont need to retain in deferred delete store
                $this->deleteFileFromStore($partialFileObject);
            }

            //there is an existing live object and the incoming file is complete as well (file size <= uploaded chunk size
            //version the existing live object as applicable
            if($inFileObject->isComplete() && isset($liveFileObject))
            {
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Single chunk complete file: " .
                    $inFileObject->getObjectFullPath());
                if($this->skipVersionCheck)
                {
                    //version checks to be skipped. this flag will be set if an old version has to be made into a live version
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Bypassing versioning: " .
                        $inFileObject->getObjectFullPath());
                }
                else
                {
                    //version the current live file.
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Versioning current live file: " .
                        $inFileObject->getObjectFullPath());
                    $fileID = $liveFileObject->getFileId();
                    $fileVersioned = $this->versionExistingFile($liveFileObject);
                    $fileUpdated = TRUE;
                }
            }

            //update file id if it is an versioned file
            if($fileVersioned)
            {
                $tgtFileName = $fileID . "_" . StorageServiceUtility::generateUniqueID();
                $inFileObject->setFileStorageName($tgtFileName);
            }
            $inFileObject->setFileId($fileID);

            //Store the file in the parent's storagezone
            $copyFromObj = $additionalOptions['copyfrom']??NULL;
            $backstoreImpl = $parentStorageObj->getStorageImplementation();
            $result = $backstoreImpl->storeFile($inFileObject, $copyFromObj);
            if($result){
                if($this->storageDB->addFileObject($inFileObject)){
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": File object add success : "
                        . $inFileObject->getObjectFullPath());
                }
                else{
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": File object add failed : "
                        . $inFileObject->getObjectFullPath());
                    return StorageObject::createErrorObject("Error adding file object to DB  "
                        . $inFileObject->getObjectFullPath());
                }
            } else{
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                    .": Skipping DB entry due error to S3 upload error : ". $inFileObject->getObjectFullPath());
                return StorageObject::createErrorObject("Error uploading file to S3  "
                    . $inFileObject->getObjectFullPath());
            }

            //Placeholder to publish events
            if($inFileObject->isComplete()){
               $fileUpdated ?
                   StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event UPDATEFILE : "
                       . $inFileObject->getObjectFullPath()):
                   StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event ADDFILE : "
                    . $inFileObject->getObjectFullPath());
            }
            //success.. return object
            return $inFileObject;

        }
        else
        {
            //not a new file. append to existing file
            if(isset($partialFileObject)) {
                //this is a next chunk of existing partial file
                StorageService::logger()->debug("Appending to an existing File: "
                    . $inFileObject->getObjectFullPath());
                $partialFileObject->prepareUsingRequestStorageObject($inFileObject);

                if($inFileObject->isComplete() && isset($liveFileObject)) {
                    //there is an existing live object and the incoming file is complete as well

                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__."File complete: " .
                        $inFileObject->getObjectFullPath());
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                        .": Versioning current live file : " . $inFileObject->getObjectFullPath());

                    if($this->versionExistingFile($liveFileObject)) {
                        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Versioning complete : " .
                            $inFileObject->getObjectFullPath());
                    }
                    else{
                        StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                            .": Unable to version live file : " . $inFileObject->getObjectFullPath());
                        return StorageObject::createErrorObject("Unable to version live file : "
                            . $inFileObject->getObjectFullPath());
                    }
                    $fileUpdated = TRUE;
                } else if($inFileObject->isComplete()) {
                    //the incoming file is complete and there is no-existing live file
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__."File complete: " .
                        $inFileObject->getObjectFullPath());
                }

                //store the segmented file
                $backstoreImpl = $parentStorageObj->getStorageImplementation();
                $result = $backstoreImpl->addToExistingFile($partialFileObject);
                if($result){
                    if($this->storageDB->updateFileObject($partialFileObject)){
                        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                            .": File object update success : ". $partialFileObject->getObjectFullPath());
                    }
                    else{
                        StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                            .": File object update failed : " . $partialFileObject->getObjectFullPath());
                        return StorageObject::createErrorObject("Error updating file object in DB  "
                            . $partialFileObject->getObjectFullPath());
                    }
                } else{
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__
                        .": Skipping DB entry due error to S3 upload error : ". $partialFileObject->getObjectFullPath());
                    return StorageObject::createErrorObject("Error uploading file to S3  "
                        . $partialFileObject->getObjectFullPath());
                }

                //Placeholder to publish events
                if($partialFileObject->isComplete()){
                    $fileUpdated ?
                        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event UPDATEFILE : "
                            . $inFileObject->getObjectFullPath()):
                        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event ADDFILE : "
                            . $inFileObject->getObjectFullPath());

                }
                //success.. return object
                return $partialFileObject;

            } else{
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Trying to append to a non-existent partial file : "
                    . $inFileObject->getObjectFullPath());
                return StorageObject::createErrorObject("Unable to find a partial file to update : "
                    . $inFileObject->getObjectFullPath());
            }
        }
    }

    /**
     * @param StorageObject $liveObject
     * @return bool
     */
    protected function versionExistingFile(StorageObject $liveObject) : bool
    {
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Versioning current live file: " . $liveObject->getObjectFullPath());

        $skipVersionSize = (float) StorageServiceConfig::CLSTORAGE_MYFILES_VERSION_SKIPSIZE;
        $shouldSkipVersion = $liveObject->getFileSize() > $skipVersionSize;

        //If versioning is disabled, delete current live item
        if (StorageServiceConfig::CLSTORAGE_NO_OF_FILEVERSIONS == 0) {
            StorageService::logger()->debug("NOOFVERSIONS=0, Removing live file: " .  $liveObject->getObjectFullPath());

            if($this->deferredDeleteFileFromStore($liveObject)){
                if($this->storageDB->deleteStorageObject($liveObject)){
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removed live file object add success : "
                        . $liveObject->getObjectFullPath());
                } else {
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to remove live file object: "
                        . $liveObject->getObjectFullPath());
                }
            }
        }
        else if ($shouldSkipVersion) {
            //check whether we should version the current file (if the size is smaller than the specified limit)
            StorageService::logger()->debug("VERSIONING FILES - Current file (".$liveObject->getFileSize().") is "
                ."bigger than the versioning threshold ($skipVersionSize). Removing live file: "
                . $liveObject->getObjectFullPath());

            if($this->deferredDeleteFileFromStore($liveObject))
            {
                if($this->storageDB->deleteStorageObject($liveObject)){
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removed live file object add success : "
                        . $liveObject->getObjectFullPath());
                } else {
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to remove live file object: "
                        . $liveObject->getObjectFullPath());
                }
            }
        }
        else{
            // If the number of allowed version files exceed the allowed limit,
            // delete the oldest one
            $nonLiveObjects = $this->storageDB->getNonLiveFileObjects($liveObject);
            if (StorageServiceConfig::CLSTORAGE_NO_OF_FILEVERSIONS > 0 &&
                        count($nonLiveObjects) >= StorageServiceConfig::CLSTORAGE_NO_OF_FILEVERSIONS) {
                $oldestNonLiveObject = $nonLiveObjects[0];
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": NOOFVERSIONS > "
                     . StorageServiceConfig::CLSTORAGE_NO_OF_FILEVERSIONS
                     . ". Removing oldest version: " . $liveObject->getObjectFullPath());
                if ($this->deferredDeleteFileFromStore($oldestNonLiveObject)) {
                    if($this->storageDB->deleteStorageObject($oldestNonLiveObject)){
                        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removed oldest file version ".
                             "object add success : " . $liveObject->getObjectFullPath());
                    } else {
                        StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to remove oldest file version: "
                             . $liveObject->getObjectFullPath());
                    }
                }
            }
        }

        // Flag the item as versioned, return TRUE
        if (StorageServiceConfig::CLSTORAGE_NO_OF_FILEVERSIONS  > 0 && !$shouldSkipVersion){
            $this->storageDB->moveLiveToNonLiveFileObject($liveObject);
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Versioning complete: "
                . $liveObject->getObjectFullPath());
            return TRUE;
        } else{
            //item not versioned, return FALSE
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Versioning not done: "
                . $liveObject->getObjectFullPath());
            return FALSE;
        }
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    protected function deleteFolderObject(StorageObject $fileObject) : bool
    {
        

        //delete folder contents
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleting contents of folder : " . $fileObject->getObjectFullPath());

        $listQueryObject = new ListQueryObject(array(
            "parentpath" => $fileObject->getObjectFullPath(),
            "recursive" => TRUE,
            "leafFirst" => TRUE
        ));
        /* @var $childObject StorageObject*/
        foreach($this->storageDB->getLiveChildObjectsForPath($listQueryObject) as $childObject ){
            if($childObject->isFolder()){
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleting child folder : "
                    . $childObject->getObjectFullPath());

                //just remove file entry from database
                if($this->storageDB->deleteStorageObject($childObject)){
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removed child folder object from DB: "
                        . $childObject->getObjectFullPath());
                } else {
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Stopping folder delete, due to error deleting child "
                        ."folder object from DB: ". $childObject->getObjectFullPath());
                    return FALSE;
                }
            }else{
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleting child file : " . $childObject->getObjectFullPath());
                if(!$this->deleteFileObject($childObject)){
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Stopping folder delete, due to error deleting child "
                        . "file object from DB: ". $fileObject->getObjectFullPath());
                    return FALSE;
                }
            }
        }

        //reached here, which means folder contents removed
        //safe to remove the top folder
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleting folder : " . $fileObject->getObjectFullPath());
        //just remove file entry from database
        if($this->storageDB->deleteStorageObject($fileObject)){
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Folder removed successfully : "
                . $fileObject->getObjectFullPath());
        } else {
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Folder contents removed, however unable to remove folder "
                ."object from DB: ". $fileObject->getObjectFullPath());
            return FALSE;
        }
        return TRUE;
    }

    /**
     * @param StorageObject $fileObject
     * @param bool $force
     * @return bool
     */
    protected function deleteFileObject(StorageObject $fileObject, $force = FALSE) : bool
    {
        
        //Remove the file from storage first
        if ($force || $this->deferredDeleteFileFromStore($fileObject)) {
            //Remove file entry from database
            if($this->storageDB->deleteStorageObject($fileObject)){
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Removed live file object from DB: "
                    . $fileObject->getObjectFullPath());
            } else {
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to remove live file object from DB: "
                    . $fileObject->getObjectFullPath());
                return FALSE;
            }

            //Remove thumb images if the file has any.
            $this->deleteAllSidecarsForFileObject($fileObject);

            $this->deleteOldVersions($fileObject);

            $this->deleteMetaTags($fileObject);

            //Placeholder to publish events
            if($fileObject->isComplete()){
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event DELETEFILE : "
                    . $fileObject->getObjectFullPath());
            }

            return TRUE;
        }
        StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Error removing live file : "
            . $fileObject->getObjectFullPath());
        return FALSE;
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    protected function deleteFileFromStore(StorageObject $fileObject) : bool
    {
        
        $backstoreImpl = $fileObject->getStorageImplementation();
        $result = $backstoreImpl->deleteFile($fileObject);
        if($result){
            return TRUE;
        } else{
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Unable to remove file from S3 : "
                . $fileObject->getObjectFullPath());
            return FALSE;
        }
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    protected function deferredDeleteFileFromStore(StorageObject $fileObject) : bool
    {
        

        if (!StorageServiceConfig::CLSTORAGE_DEFERRED_DELETE) {
            //deferred delete not selected. delete the file
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deferred delete not selected : " . $fileObject->getObjectFullPath());
            return $this->deleteFileFromStore($fileObject);
        }

        //deferred delete selected, don't delete the file directly. add to deferred delete store
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Adding file to deferred delete store : " . $fileObject->getObjectFullPath());

        //add entry to deleteditems collection
        if($this->storageDB->copyFileObjectToDeferredDeleteStore($fileObject)){
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": File object added to defere success : "
                . $fileObject->getObjectFullPath());
            return TRUE;
        }

        return FALSE;
    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    protected function deleteOldVersions(StorageObject $fileObject) {

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Deleting old versions : " . $fileObject->getObjectFullPath());
        $nonLiveObjects = $this->storageDB->getNonLiveFileObjects($fileObject);
        foreach($nonLiveObjects as $nonLiveObject){
            //remove these old versions
            $this->deferredDeleteFileFromStore($nonLiveObject);
        }

        return $this->storageDB->deleteNonLiveFileObjects($fileObject);
    }

    /**
     * @param StorageObject $srcObject
     * @param StorageObject $tgtObject
     * @return bool
     */
    protected function moveFileObject(StorageObject $srcObject, StorageObject $tgtObject) {
        
        $caseInsensitiveFileCheck = TRUE;
        if($srcObject->getParentPath() == $tgtObject->getParentPath()){
            //if source and target folder are the same
            //make a case sensitive search, as this could be rename
            $caseInsensitiveFileCheck = FALSE;
        }

        $existingTgtObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($tgtObject->getParentPath(),
            $tgtObject->getName(), $caseInsensitiveFileCheck);
        if(isset($existingTgtObject)){
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Versioning current live file: "
                . $existingTgtObject->getObjectFullPath());
            //version the existing file
            $this->versionExistingFile($existingTgtObject);

            //we use the live items name for further processing
            //Reason: if the existing name is a case variation of source name
            //so that source file will use the existing file on the target
            //folder during move
            $tgtObject->setFileName($existingTgtObject->getName());
            $tgtObject->setFileId($existingTgtObject->getFileId());
        } else {
            $tgtObject->setFileId($srcObject->getFileId());
        }

        $result = $this->storageDB->moveFileObject($srcObject, $tgtObject);
        if ($result) {
            //Placeholder to publish events
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Move file complete : "
                . $srcObject->getObjectFullPath() ." => ". $tgtObject->getObjectFullPath());
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event RENAMEORMOVEFILE : "
                . $srcObject->getObjectFullPath() ." => ". $tgtObject->getObjectFullPath());
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event " . (isset($existingTgtObject)?"UPDATEFILE":"ADDFILE")
                . " " . $tgtObject->getObjectFullPath());
        } else {
            StorageService::logger()->error(__FUNCTION__ . ' : ' ."Move file failed: "
                . $srcObject->getObjectFullPath() ." => ". $tgtObject->getObjectFullPath());
            return FALSE;
        }
        return TRUE;
    }

    /**
     * @param StorageObject $srcObject
     * @param StorageObject $tgtObject
     * @return bool
     */
    protected function moveFolderObject(StorageObject $srcObject, StorageObject $tgtObject) : bool
    {
        
        $tgtFullPath = $tgtObject->getObjectFullPath();

        //if the source and target paths are different,
        //do a case-insensitive search for folder at the target location
        if($srcObject->getParentPath() != $tgtObject->getParentPath()){
            $existingTgtObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($tgtObject->getParentPath(),
                $tgtObject->getName(), TRUE);
            if(isset($existingTgtObject)){
                //existing folder, merge needed
                $tgtObject = $existingTgtObject;
            }else{
                //new folder... no merge needed
                $tgtObject = $this->addFolderObject($tgtObject, TRUE);
            }
        }else{
            //if source and target folder are the same
            //make a case sensitive search, as this could be rename
            $tgtObject = $this->addFolderObject($tgtObject, FALSE);
        }

        if(!isset($tgtObject)){
            StorageService::logger()->error(__FUNCTION__ . ' : ' ."Add tgt folder failed: "
                . $tgtFullPath);
            return FALSE;
        }

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Event RENAMEORMOVEFOLDER : "
            . $srcObject->getObjectFullPath() ." => ". $tgtObject->getObjectFullPath());


        $passthruArray = array('errorcount' => 0);
        $this->moveFolderObjectContents($srcObject, $tgtObject, $passthruArray);
        if($passthruArray['errorcount'] > 0){
            StorageService::logger()->error(__FUNCTION__ . ' : ' . "Move folder contents failed: "
                . $srcObject->getObjectFullPath() ." => ". $tgtObject->getObjectFullPath());
            return FALSE;
        }

        //all moved successfully. delete old folder
        $result = $this->deleteFolderObject($srcObject);
        if(!$result){
            StorageService::logger()->error(__FUNCTION__ . ' : ' . "Delete src folder failed: "
                . $srcObject->getObjectFullPath());
            return FALSE;
        }
        return TRUE;
    }

    /**
     * @param StorageObject $srcObject
     * @param StorageObject $tgtObject
     * @param $passthruArray
     */
    protected function moveFolderObjectContents(StorageObject $srcObject, StorageObject $tgtObject, &$passthruArray)  : void
    {
        

        //delete folder contents
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Moving contents of folder : "
            . $srcObject->getObjectFullPath() . "->" . $tgtObject->getObjectFullPath());

        $listQueryObject = new ListQueryObject(array(
            "parentpath" => $srcObject->getObjectFullPath(),
            "recursive" => FALSE,
            "leafFirst" => TRUE
        ));
        /* @var $childObject StorageObject*/
        foreach($this->storageDB->getLiveChildObjectsForPath($listQueryObject)
                as $childObject ){
            $tgtChildObject = clone $childObject;
            $tgtChildObject->setObjectFullPath($tgtObject->getObjectFullPath().
                $childObject->getObjectRelativePath($srcObject->getObjectFullPath()));

            if($childObject->isFolder()){
                if(!$this->moveFolderObject($childObject, $tgtChildObject)){
                    $passthruArray['errorcount']++;
                }
            }else{
                if(!$this->moveFileObject($childObject, $tgtChildObject)){
                    $passthruArray['errorcount']++;
                }
            }
        }
    }

    /**
     * @param StorageObject $srcObject
     * @param StorageObject $dstObject
     * @param bool $overwrite
     * @return bool
     */
    protected function copyFileObject(StorageObject $srcObject, StorageObject $dstObject, $overwrite = FALSE) : bool
    {

        if(!$overwrite){
            //make sure no files with case variation exists
            $existingTgtObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($dstObject->getParentPath(),
                $dstObject->getName(), TRUE);
            if(isset($existingTgtObject)){
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Target file already exists : "
                    . $existingTgtObject->getObjectFullPath());
                return FALSE;
            }
        }

        //copy file from existing object
        $additionalOptions = array("copyfrom" => $srcObject);
        $newFileObj = $this->addFileObject($dstObject, $additionalOptions);
        if(!isset($newFileObj)){
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Failed to copy file object : "
                . $srcObject->getObjectFullPath() . "->" . $dstObject->getObjectFullPath());
            return FALSE;
        }

        //copy all sidecar files as well
        $sidecarObjects = $this->storageDB->getAllSideCarFileObjects($srcObject);
        foreach ($sidecarObjects as $sidecarObject) {
            /** @var StorageObject $tgtSidecarObject */
            $tgtSidecarObject = clone $sidecarObject;
            $tgtSidecarObject->setFileParentPath($dstObject->getParentPath());
            $tgtSidecarObject->setFileName($dstObject->getName());

            $additionalOptions = array(
                "livefileobject" => $newFileObj,
                "copyfrom" => $sidecarObject
            );
            //ignore success or failure
            $this->addSidecarFileObject($tgtSidecarObject, $additionalOptions);

        }
        return TRUE;
    }

    /**
     * @param StorageObject $srcObject
     * @param StorageObject $tgtObject
     * @param bool $overwrite
     * @return bool
     */
    protected function copyFolderObject(StorageObject $srcObject, StorageObject $tgtObject, $overwrite = FALSE)  : bool
    {
        //delete folder contents
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Copying folder : "
            . $srcObject->getObjectFullPath() . "->" . $tgtObject->getObjectFullPath());
        $this->addFolderObject($tgtObject, TRUE);

        $listQueryObject = new ListQueryObject(array(
            "parentpath" => $srcObject->getObjectFullPath(),
            "recursive" => TRUE,
            "leafFirst" => FALSE
        ));
        /* @var $childObject StorageObject*/
        foreach($this->storageDB->getLiveChildObjectsForPath($listQueryObject) as $childObject ){
            $tgtChildObject = clone $childObject;
            $tgtChildObject->setObjectFullPath($tgtObject->getObjectFullPath().
                $childObject->getObjectRelativePath($srcObject->getObjectFullPath()));
            $tgtChildObject->setObjectSiteId($tgtObject->getObjectSiteId());

            if($childObject->isFolder()){
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Copying folder : "
                    . $childObject->getObjectFullPath() . "->" . $tgtChildObject->getObjectFullPath());
                $this->addFolderObject($tgtChildObject, TRUE);
            } else {
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Copying file : "
                    . $childObject->getObjectFullPath() . "->" . $tgtChildObject->getObjectFullPath());
                $this->copyFileObject($childObject, $tgtChildObject, $overwrite);
            }
        }
        return TRUE;
    }

    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function addObjectMetaTags(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_SETOBJECTMETATAGS);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $inFileObject = $requestObject->getSrcStorageObject();

        //get the DB record from the database
        $inDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($inFileObject->getParentPath(),
            $inFileObject->getName());
        if (!isset($inDBObject)) {
            $msg = "No matching live object found in DB " . $inFileObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
        $inDBObject->setMetaTagsAsString($inFileObject->getMetaTagsAsString());

        if($this->storageDB->addObjectMetaTags($inDBObject)){
            $msg = "Added metatags for object " . $inDBObject->getObjectFullPath();
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $msg = "Unable to add metatags for object " . $inDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function updateObjectMetaTags(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_UPDATEOBJECTMETATAGS);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $inFileObject = $requestObject->getSrcStorageObject();

        //get the DB record from the database
        $inDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($inFileObject->getParentPath(),
            $inFileObject->getName());
        if (!isset($inDBObject)) {
            $msg = "No matching live object found in DB " . $reqSrcStorageObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
        $inDBObject->setMetaTagsAsString($inFileObject->getMetaTagsAsString());

        if($this->storageDB->updateObjectMetaTags($inDBObject)){
            $msg = "Updated metatags for object " . $inDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $msg = "Unable to update metatags for object " . $inDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function getObjectMetaTags(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETOBJECTMETATAGS);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $inFileObject = $requestObject->getSrcStorageObject();

        //get the DB record from the database
        $inDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($inFileObject->getParentPath(),
            $inFileObject->getName());
        if (!isset($inDBObject)) {
            $msg = "No matching live object found in DB " . $inFileObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        if($this->storageDB->getMetaTagsForObject($inDBObject)){
            $msg = "Found metatags for object " . $inDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            /** @var ResponseObject $responseObject */
            $responseObject =  ResponseObject::createSuccessResponse($msg);
            $responseObject->setStorageObject($inDBObject);
            return $responseObject;
        } else{
            $msg = "Unable to find metatags for object " . $inDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function deleteObjectMetaTags(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_REMOVEOBJECTMETATAGS);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $reqSrcStorageObject */
        $inFileObject = $requestObject->getSrcStorageObject();

        //get the DB record from the database
        $inDBObject = $this->storageDB->getLiveStorageObjectForParentPathAndName($inFileObject->getParentPath(),
            $inFileObject->getName());
        if (!isset($inDBObject)) {
            $msg = "No matching live object found in DB " . $reqSrcStorageObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        return $this->deleteMetaTags($inDBObject);
    }

    protected function deleteMetaTags(StorageObject $fileObject) : ResponseObject
    {

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Deleting metatags : " . $fileObject->getObjectFullPath());
        if($this->storageDB->deleteObjectMetaTags($fileObject)){
            $msg = "Removed metatags for object " . $fileObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $msg = "Unable to remove metatags for object " . $fileObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * @param StorageObject $inFileObject
     * @param array $additionalOptions
     * @return StorageObject|null
     */
    private function addSidecarFileObject(StorageObject $inFileObject, array $additionalOptions = array()): ?StorageObject
    {
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Adding sidecar file ".$inFileObject->getObjectFullPath()
            ." with metadata ".$inFileObject->getSidecarMetadataAsString());

        //check if live object is valid
        if(isset($additionalOptions['livefileobject'])){
            $liveFileObject = $additionalOptions['livefileobject'];
        } else {
            $liveFileObject = $this->storageDB->getLiveStorageObjectForParentPathAndName(
                $inFileObject->getParentPath(), $inFileObject->getName());
            if(!isset($liveFileObject))
            {
                $msg = "Error saving sidecar file. Invalid live file ". $inFileObject->getObjectFullPath();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": " . $msg);
                return StorageObject::createErrorObject($msg);
            }
        }

        $existingSidecarFileObject = $this->storageDB->getSideCarFileObject($inFileObject);
        if(isset($existingSidecarFileObject)){
            //if there is an existing sidecar file with same sidecar metadata remove it
            if($this->deleteFileFromStore($existingSidecarFileObject)){
                if($this->storageDB->deleteStorageObject($existingSidecarFileObject)){
                    $msg = "Removed existing sidecar file from storage and db :". $inFileObject->getObjectFullPath()
                        ."|".$inFileObject->getSidecarMetadataAsString();
                    StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": " . $msg);
                } else {
                    $msg = "Removed existing sidecar file from storage, but unable to remove from db :"
                        . $inFileObject->getObjectFullPath() . "|" . $inFileObject->getSidecarMetadataAsString();
                    StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": " . $msg);
                    return StorageObject::createErrorObject($msg);
                }
            } else{
                $msg = "Unable to remove existing sidecar file :". $inFileObject->getObjectFullPath()
                    ."|".$inFileObject->getSidecarMetadataAsString();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": " . $msg);
                return StorageObject::createErrorObject($msg);
            }
        }

        //set the fileid for the sidecarfile
        //also storagezone should be same as the live file
        $tgtFileName = $liveFileObject->getFileId() . "_" . StorageServiceUtility::generateUniqueID();
        $inFileObject->setFileStorageName($tgtFileName);
        $inFileObject->setFileId($liveFileObject->getFileId());
        $inFileObject->setStorageZoneId($liveFileObject->getStorageZoneId());

        //store the sidecar file
        $copyFromObj = $additionalOptions['copyfrom']??NULL;
        $backstoreImpl = $liveFileObject->getStorageImplementation();
        $result = $backstoreImpl->storeFile($inFileObject, $copyFromObj);
        if($result){
            if($this->storageDB->addFileObject($inFileObject)){
                StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
                    .": Sidecar file object add success : ". $inFileObject->getObjectFullPath()
                    . "|" . $inFileObject->getSidecarMetadataAsString());
            }
            else{
                $msg = "Unable to add sidecar file :"
                    . $inFileObject->getObjectFullPath() . "|" . $inFileObject->getSidecarMetadataAsString();
                StorageService::logger()->error(__CLASS__.":".__FUNCTION__.$msg);
                return StorageObject::createErrorObject($msg);
            }
        } else{
            $msg = "Unable to upload sidecar file. Skipping DB entry :"
                . $inFileObject->getObjectFullPath() . "|" . $inFileObject->getSidecarMetadataAsString();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__ . $msg);
            return StorageObject::createErrorObject($msg);
        }
        return $inFileObject;
    }

    /**
     * @param StorageObject $fileObject
     * @return ResponseObject
     */
    public function getAllSidecarsForFile(RequestObject $requestObject) : ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETSIDECARLIST);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $fileObject */
        $fileObject = $requestObject->getSrcStorageObject();

        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Getting sidecar files : " . $fileObject->getObjectFullPath());
        $sidecarObjects = $this->storageDB->getAllSideCarFileObjects($fileObject);
        return ResponseObject::createGetFileListResponse($sidecarObjects);
    }

    /**
     * @param RequestObject
     * @return ResponseObject
     */
    public function getSidecarObjectInfo(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETSIDECARINFO);
        if (!$valid) {
            $msg = "Validation failed " . $requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $inFileObject */
        $inFileObject = $requestObject->getSrcStorageObject();

        //get the DB record from the database
        $inFileDBObject = $this->storageDB->getSideCarFileObject($inFileObject);
        if (!isset($inFileDBObject)) {
            $msg = "No matching sidecar object found in DB " . $inFileObject->getObjectFullPath();
            //StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //$msg = "Matching sidecar object found in DB " . $inFileObject->getObjectFullPath();
        //StorageService::logger()->debug(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
        return ResponseObject::createGetFileInfoResponse($inFileDBObject);
    }

    /**
     * @param RequestObject $requestObject
     * @return bool
     */
    public function deleteAllSidecarsForObject(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_DELETEOBJECTSIDECARS);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        //get the DB record from the database
        $inStorageObject = $requestObject->getSrcStorageObject();
        if($this->deleteAllSidecarsForFileObject($inStorageObject)){
            $msg = "Removed sidecars for object " . $inStorageObject->getObjectFullPath();
            StorageService::logger()->debug(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createSuccessResponse($msg);
        } else{
            $msg = "Unable to remove sidecars for object " . $inStorageObject->getObjectFullPath();
            //StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }

    }

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    private function deleteAllSidecarsForFileObject(StorageObject $fileObject) : bool
    {
        StorageService::logger()->debug(__CLASS__.":".__FUNCTION__
            .": Deleting all sidecar files for file : " . $fileObject->getObjectFullPath());
        $sideCarFileObjects = $this->storageDB->getAllSideCarFileObjects($fileObject);
        foreach($sideCarFileObjects as $sideCarFileObject){
            //remove from storage, no deferred deletes
            $this->deleteFileFromStore($sideCarFileObject);
        }

        return $this->storageDB->deleteAllSideCarFileObjects($fileObject);
    }

    /**
     * @param RequestObject $requestObject
     * @return bool
     */
    public function getFolderProperties(RequestObject $requestObject): ResponseObject
    {
        //validate the request
        $valid = $requestObject->validate(RequestObject::REQUEST_GETFOLDERPROPERTIES);
        if(!$valid){
            $msg = "Validation failed ".$requestObject->getErrorMessage();
            StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": ".$msg);
            return ResponseObject::createErrorResponse($msg);
        }

        /** @var StorageObject $inStorageObject */
        $inStorageObject = $requestObject->getSrcStorageObject();

        //storage root object is a virtual concept that doesnt exists in the DB
        //lookup DB only for non-storage root objects
        if(!$inStorageObject->isStorageRoot()){
            //get the DB record from the database
            $srcDBObject = $this->storageDB->getFolderObjectForParentPathAndName(
                $inStorageObject->getParentPath(), $inStorageObject->getName());
            if (!isset($srcDBObject)) {
                $msg = "No matching object found in DB " . $inStorageObject->getObjectFullPath();
                StorageService::logger()->debug(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
                return ResponseObject::createErrorResponse($msg);
            }
        } else{
            $srcDBObject = $inStorageObject;
        }

        //get the DB record from the database
        $propertiesObject = $this->storageDB->getFolderProperties($srcDBObject);
        if (!isset($propertiesObject)) {
            $msg = "Not able to calculate folder properties " . $srcDBObject->getObjectFullPath();
            StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
            return ResponseObject::createErrorResponse($msg);
        }
        return ResponseObject::createGetFolderPropertiesResponse($propertiesObject);

    }

}