<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Shared;

use CodeLathe\Service\Storage\Backstore\AbstractBackstore;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;
use CodeLathe\Service\Storage\StorageService;

/**
 * Class StorageObject
 * @package CodeLathe\Service\Storage\Shared
 * @deprecated
 */
class StorageObject
{
    //object properties
    const OBJECT_NAME = "objectname";
    const OBJECT_EXT = "objectext";
    const OBJECT_TYPE = "objecttype";
    const OBJECT_SIZE = "objectsize";
    const OBJECT_OWNER = "objectowner";
    const OBJECT_PARENTPATH = "objectparentpath";
    const OBJECT_CREATIONDATE = "objectcreationdate";
    const OBJECT_MODIFICATIONDATE = "objectmodificationdate";
    const OBJECT_LASTACCESSDATE = "objectlastaccessdate";
    const OBJECT_DELETEDATE = "objectdeletedate";
    const OBJECT_CHUNKSTART = "objectchunkstart";
    const OBJECT_COMPLETE = "objectcomplete";
    const OBJECT_FILEID = "objectfileid";
    const OBJECT_STORAGEZONEID = "objectstoragezoneid";
    const OBJECT_STORAGEPATH = "objectstoragepath";
    const OBJECT_SRCPATH = "objectsrcpath";
    const OBJECT_SRCSTREAM = "objectsrcstream";
    const OBJECT_BACKSTOREDATA = "objectbackstoredata"; //field store storage implementation specific data
    const OBJECT_SIDECARMETADATA = "objectsidecarmetadata"; //field store storage implementation specific data
    const OBJECT_SITEID = "objectsiteid";
    const OBJECT_DL_RESPONSETYPE = "objectdlresponsetype";
    const OBJECT_DL_HTTPCONTENTTYPE_OVERRIDE = "objectdlhttpcontenttypeoverride";
    const OBJECT_VERSIONIDENTFIER = "objectversionidentifier";
    const OBJECT_METATAGS = "objectmetatags";
    
    //object list properties
    const OBJECTLIST_OFFSET = "objectlistoffset";
    const OBJECTLIST_LIMIT = "objectlistlimit";

    //object types
    public const OBJECTTYPE_FILE = "file";
    public const OBJECTTYPE_SIDECARFILE = "sidecarfile";
    public const OBJECTTYPE_FOLDER = "folder";

    
    //other fields
    private $validity;
    private $errorMessage;
    private $alreadyExists = FALSE;
    private $filePathToRead;
    private $fileStreamToRead;
    private $fileChunkStart;
    private $storagename;
    private $tempIncomingFile;
    //attribute that marks the site to which this object belongs
    //used only while storing object in the backstore
    private $objectSiteId;
    //attribute to store type of response ie., directdownload or storeandforward 
    private $objectDLResponseType;
    //attribute to store content type override during direct downloads
    private $objectDLHTTPContentTypeOverride;
    //attribute to store object properties
    private $objectMetaTags;

    //variable mapped to DB table columns
    public $id;
    public $storagezoneid;
    public $name;
    public $extension;
    public $parentpath;
    public $type;
    public $size;
    public $complete;
    public $creationdate;
    public $modificationdate;
    public $lastaccessdate;
    public $versioneddate;
    public $owner;
    public $storagepath;
    public $fileid;
    public $deleteddate;
    public $backstoredata;
    public $sidecarmetadata;
    public $syncversion;

    public static function createStorageObject(array $objectOptions) : StorageObject
    {
        $storageObject = new StorageObject();

        //initialize
        $storageObject->validity = true;
        $storageObject->name = $objectOptions[self::OBJECT_NAME];
        $storageObject->type = $objectOptions[self::OBJECT_TYPE]??null;
        $storageObject->size = $objectOptions[self::OBJECT_SIZE]??0;
        $storageObject->owner = $objectOptions[self::OBJECT_OWNER]??"";
        $storageObject->parentpath = $objectOptions[self::OBJECT_PARENTPATH];
        $storageObject->creationdate = $objectOptions[self::OBJECT_CREATIONDATE]??null;
        $storageObject->modificationdate = $objectOptions[self::OBJECT_MODIFICATIONDATE]??null;
        $storageObject->lastaccessdate = $objectOptions[self::OBJECT_LASTACCESSDATE]??null;
        $storageObject->filePathToRead = $objectOptions[self::OBJECT_SRCPATH]??null;
        $storageObject->fileStreamToRead = $objectOptions[self::OBJECT_SRCSTREAM]??null;
        $storageObject->fileChunkStart = $objectOptions[self::OBJECT_CHUNKSTART]??0;
        $storageObject->complete = $objectOptions[self::OBJECT_COMPLETE]??1;
        $storageObject->storagepath = $objectOptions[self::OBJECT_STORAGEPATH]??null;
        $storageObject->storagezoneid = $objectOptions[StorageObject::OBJECT_STORAGEZONEID]??null;
        $storageObject->sidecarmetadata = $objectOptions[StorageObject::OBJECT_SIDECARMETADATA]??null;
        $storageObject->objectSiteId = $objectOptions[StorageObject::OBJECT_SITEID]??null;
        $storageObject->objectDLResponseType = $objectOptions[StorageObject::OBJECT_DL_RESPONSETYPE]??null;
        $storageObject->objectDLHTTPContentTypeOverride = $objectOptions[StorageObject::OBJECT_DL_HTTPCONTENTTYPE_OVERRIDE]??null;
        $storageObject->versioneddate = $objectOptions[StorageObject::OBJECT_VERSIONIDENTFIER]??null;
        $storageObject->objectMetaTags = $objectOptions[StorageObject::OBJECT_METATAGS]??null;

        //segmented upload not allowed for sidecar files
        if($storageObject->isSideCarFile()){
            //override values specified
            $storageObject->fileChunkStart = 0;
            $storageObject->complete = 1;
        }

        return $storageObject;
    }


    public static function createErrorObject(string $errorMessage) : StorageObject
    {
        $errorObject = new StorageObject();
        $errorObject->validity = false;
        $errorObject->errorMessage = $errorMessage;
        return $errorObject;
    }

    // ******************Getter functions start**********

    /**
     * @return string
     */
    public function getObjectId() : string
    {
        return strval($this->id);
    }

    /**
     * @return string|null
     */
    public function getStorageZoneId() : ?string
    {
        return $this->storagezoneid;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getExtension() : ?string
    {
        if(!$this->isFolder()){
            if(isset($this->extension)){
                return $this->extension;
            }
            $pathinfo = StorageServiceUtility::getPathInfo($this->name);
            return isset($pathinfo['extension'])?mb_strtolower($pathinfo['extension']):"";
        }
        return NULL;
    }

    /**
     * @return string|null
     */
    public function getType() : ?string
    {
        return $this->type;
    }

    /**
     * @return float
     */
    public function getSize() : float
    {
        return doubleval($this->size);
    }

    /**
     * @return string
     */
    public function getParentPath() : string
    {
        return $this->parentpath;
    }

    /**
     * @return string
     */
    public function getOwner() : string
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getFileStoragePath() : string
    {
        return $this->storagepath;
    }

    //DB creation date

    /**
     * @return string
     * @throws \Exception
     */
    public function getCreationDate() : string
    {
        if(isset($this->creationdate))
        {
            return $this->creationdate;
        }
        else
        {
            $now = new \DateTime();
            $this->creationdate = $now->format('Y-m-d H:i:s');
            return $this->creationdate;
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getModificationDate() : string
    {
        if(isset($this->modificationdate))
        {
            if(is_string($this->modificationdate)) return $this->modificationdate;
            else return date('Y-m-d H:i:s', $this->modificationdate);
        }
        else {
            return $this->getCreationDate();
        }
    }

    /**
     * @return float
     */
    public function getFileChunkStart() : float
    {
        return doubleval($this->fileChunkStart);
    }

    /**
     * @return bool
     */
    public function isFileStart() : bool
    {
        return $this->getFileChunkStart() == 0;
    }

    /**
     * @return bool
     */
    public function isComplete() : bool
    {
        return boolval($this->complete);
    }

    /**
     * @return bool
     */
    public function isSegmented() : bool
    {
        return !boolval($this->complete);
    }

    /**
     * @return string
     */
    public function getFileId() : string
    {
        return $this->fileid;
    }

    /**
     * @return float
     */
    public function getFileSize() : float
    {
        return doubleval($this->size);
    }

    /**
     * @return string
     */
    public function getFileStorageName() : string
    {
        return $this->storagename??$this->getFileId();
    }

    /**
     * @return string
     */
    public function getInDataFilePath() : ?string
    {
        return $this->filePathToRead;
    }

    /**
     * @return string
     */
    public function getSyncVersionId() : ?string
    {
        return $this->syncversion;
    }

    /**
     * @return resource
     */
    public function getInDataFileStream() : ?resource
    {
        return $this->fileStreamToRead;
    }

    // ******************Getter functions end**********

    // ******************Setter functions start**********
    /**
     * @param string $id
     */
    public function setObjectId(string $id) : void
    {
        $this->id = $id;
    }

    /**
     * @param string $fileid
     */
    public function setFileId(string $fileid) : void
    {
        $this->fileid = $fileid;
    }

    /**
     * @param string $filename
     */
    public function setFileName(string $filename) : void
    {
        $this->name = $filename;
    }

    /**
     * @param string $parentPath
     */
    public function setFileParentPath(string $parentPath) : void
    {
        $this->parentpath = $parentPath;
    }

    /**
     * @param string $name
     */
    public function setFileStorageName(string $name) : void
    {
        $this->storagename = $name;
    }

    /**
     * @param string $path
     */
    public function setFileStoragePath(string $path) : void
    {
        $this->storagepath = $path;
    }

    /**
     * @param float $size
     */
    public function setFileSize(float $size) : void
    {
        $this->size = $size;
    }

    /**
     * @param string $storagezoneid
     */
    public function setStorageZoneId(string $storagezoneid) : void
    {
        $this->storagezoneid = $storagezoneid;
    }

    /**
     * @param string $owner
     */
    public function setOwner(string $owner) : void
    {
        $this->owner = $owner;
    }

    /**
     * @param string $type
     */
    public function setType(string $type) : void
    {
        $this->type = $type;
    }

    /**
     * @param string $fullPath
     */
    public function setObjectFullPath(string $fullPath) : void
    {
        $parentpath = "";
        $name = "";
        StorageServiceUtility::splitPaths($fullPath, $parentpath, $name);
        $this->setFileParentPath($parentpath);
        $this->setFileName($name);
    }

    // ******************Setter functions end**********

    // ******************Utility functions start**********
    /**
     * @return string
     */
    public function getObjectFullPath() : string
    {
        if($this->isStorageRoot()){
            //return empty path for storage root full path
            //as this is used only in storage calculations
            return "";
        }
        return StorageServiceUtility::convertToFullPath($this->parentpath, $this->name);
    }

    /**
     * @param string $basePath
     * @return string
     */
    public function getObjectRelativePath(string $basePath) : string
    {
        return str_replace($basePath, "", $this->getObjectFullPath());
    }

    /**
     * Check if this storage object represents the full storage subsystem
     * @return bool
     */
    public function isStorageRoot() : bool
    {
        return $this->parentpath === "" && $this->name === "/";
    }

    /**
     * @return bool
     */
    public function isRootFolder() : bool
    {
        return $this->parentpath == "/";
    }

    /**
     * @return bool
     */
    public function isFolder() : bool
    {
        return $this->type == self::OBJECTTYPE_FOLDER;
    }

    /**
     * @return bool
     */
    public function isFile() : bool
    {
        return $this->type == self::OBJECTTYPE_FILE;
    }

    /**
     * @return bool
     */
    public function isSideCarFile() : bool
    {
        return $this->type == self::OBJECTTYPE_SIDECARFILE;
    }

    /**
     * @return bool
     */
    public function isValid() : bool
    {
        return $this->validity??TRUE;
    }

    /**
     * @return bool
     */
    public function isLiveVersion() : bool
    {
        return $this->isComplete() && !isset($this->versioneddate);
    }

    /**
     * @return bool
     */
    public function isPartialVersion() : bool
    {
        return !$this->isComplete();
    }

    /**
     *
     */
    public function setAlreadyExists() : void
    {
        $this->alreadyExists = true;
    }

    /**
     * @return bool
     */
    public function alreadyExists(): bool
    {
        return $this->alreadyExists;
    }

    /**
     * @return string|null
     */
    public function errorMessage() : ?string
    {
        return $this->errorMessage;
    }

    /**
     * Copy attributes from incoming storage object
     * @param StorageObject $storageObject
     * 
     */
    public function prepareUsingRequestStorageObject(StorageObject $storageObject) : void
    {
        $this->fileStreamToRead = $storageObject->getInDataFileStream();
        $this->filePathToRead = $storageObject->getInDataFilePath();
        $this->fileChunkStart = $storageObject->getFileChunkStart();
        $this->complete = $storageObject->isComplete();
    }
    /**
     * @return string|null
     */
    public function getIncomingDataAsFile() : ?string
    {
        
        if(isset($this->filePathToRead))
        {
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Incoming file ".$this->filePathToRead."-->".$this->getObjectFullPath());
            return $this->filePathToRead;
        }
        else if(isset($this->fileStreamToRead))
        {
            StorageService::logger()->debug(__CLASS__.":".__FUNCTION__.": Incoming stream -->".$this->getObjectFullPath());
            $this->tempIncomingFile = StorageServiceUtility::getTempFolder().DIRECTORY_SEPARATOR.$this->getFileId();
            $fileHandle = fopen($this->tempIncomingFile, 'w'); // Create a new file
            stream_copy_to_stream($this->fileStreamToRead, $fileHandle);
            fclose($fileHandle);
            return $this->tempIncomingFile;
        }
        StorageService::logger()->error(__CLASS__.":".__FUNCTION__.": Incoming file or stream not found for file object ".$this->getName());
        return NULL;
    }

    public function getUploadPartCacheFile() : ?string
    {
        return StorageServiceUtility::getTempFolder().DIRECTORY_SEPARATOR.$this->getFileStorageName().".uploadpart";
    }

    public function removeUploadPartCacheFile() : void
    {
        $cacheFile = $this->getUploadPartCacheFile();
        if(file_exists($cacheFile)){
            gc_collect_cycles();
            unlink($cacheFile);
        }
    }
    /**
     *
     */
    public function closeIncomingDataStream() : void
    {
        if(isset($this->tempIncomingFile)){
            gc_collect_cycles();
            unlink($this->tempIncomingFile);
        }
    }

    /**
     * @return AbstractBackstore
     */
    public function getStorageImplementation() : AbstractBackstore
    {
        return AbstractBackstore::getInstance($this->storagezoneid);
    }

    /**
     * @return string
     */
    public function getHTTPContentType(): string
    {
        //if override is set, use that.
        //override will be set typically for thumb downloads
        if(isset($this->objectDLHTTPContentTypeOverride)){
            return $this->objectDLHTTPContentTypeOverride;
        }
        return StorageServiceUtility::getHTTPContentType($this->getName());
    }

    // ******************Utility functions end**********

    // ******************DB helper functions start**********
    /**
     * @return array
     * @throws \Exception
     */
    public function getNewObjectArray() : array
    {
        if($this->isFolder()){
            return $this->getNewFolderObjectArray();
        } elseif($this->isSideCarFile()){
            return $this->getNewSideCarFileObjectArray();
        } else{
            return $this->getNewFileObjectArray();
        }
    }
    
    /**
     * @return array
     * @throws \Exception
     */
    private function getNewFolderObjectArray() : array
    {
        return array(
            ":".self::OBJECT_STORAGEZONEID => $this->getStorageZoneId(),
            ":".self::OBJECT_NAME => $this->getName(),
            ":".self::OBJECT_EXT => $this->getExtension(),
            ":".self::OBJECT_PARENTPATH => $this->getParentPath(),
            ":".self::OBJECT_TYPE => $this->getType(),
            ":".self::OBJECT_SIZE => $this->getSize(),
            ":".self::OBJECT_OWNER => $this->getOwner(),
            ":".self::OBJECT_CREATIONDATE => $this->getCreationDate(),
            ":".self::OBJECT_MODIFICATIONDATE => $this->getModificationDate(),
            ":".self::OBJECT_FILEID => $this->getFileId(),
        );
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getNewFileObjectArray() : array
    {
        $fileObject = $this->getNewFolderObjectArray();
        return $fileObject + array(
                ":".self::OBJECT_COMPLETE => $this->isComplete(),
                ":".self::OBJECT_STORAGEPATH => $this->getFileStoragePath(),
                ":".self::OBJECT_BACKSTOREDATA => $this->getBackstoredataAsString(),
            );
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getNewSideCarFileObjectArray() : array
    {
        $fileObject = $this->getNewFileObjectArray();
        return $fileObject + array(
                ":".self::OBJECT_SIDECARMETADATA => $this->getSidecarMetadataAsString(),
            );
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getNewDefferedDeleteObjectArray() : array
    {
        $deletedate = StorageServiceUtility::getNowDateAsString();
        $fileObject = $this->getNewFileObjectArray();
        return $fileObject + array(
                ":".self::OBJECT_DELETEDATE => $deletedate,
            );
    }

    /**
     * @return array
     */
    public function getSyncUpdateArrayForAddFile() : array
    {
        $parentPaths = array(
            "file" => array()
        );
    }

    /**
     * Function that returns storage implementation specific data
     * @return mixed
     */
    public function getBackstoredata() : ?array
    {
        if(isset($this->backstoredata)){
            return json_decode($this->backstoredata, TRUE);
        } else{
            return NULL;
        }
    }

    /**
     * Function that returns storage implementation specific data
     * @return string
     */
    public function getBackstoredataAsString() : ?string
    {
        return $this->backstoredata;
    }


    /**
     * Function used by storage implementation to store its own data
     * This data will not be used by any classes except the respective implementation
     * @param mixed $backstoredata
     */
    public function setBackstoredata(array $backstoredata): void
    {
        $this->backstoredata = json_encode($backstoredata);
    }

    /**
     * Function that returns sidecar metadata
     * @return string
     */
    public function getSidecarMetadataAsString() : ?string
    {
        return $this->sidecarmetadata;
    }

    /**
     * Function that sets sidecar metadata
     * @return void
     */
    public function setSidecarMetadataString(string $sidecarmetadata) : void
    {
        $this->sidecarmetadata = $sidecarmetadata;
    }

    /**
     * Function that returns object metatags in string format
     * @return string
     */
    public function getMetaTagsAsString() : ?string
    {
        return $this->objectMetaTags;
    }


    /**
     * @param mixed $metatags
     */
    public function setMetaTagsAsString(string $metatags): void
    {
        $this->objectMetaTags = $metatags;
    }


    /**
     * @return string
     */
    public function getObjectSiteId() : ?string
    {
        return $this->objectSiteId;
    }

    /**
     * @param string $objectSiteId
     */
    public function setObjectSiteId(string $objectSiteId): void
    {
        $this->objectSiteId = $objectSiteId;
    }

    /**
     * @return string
     */
    public function getObjectDLResponseType() : string
    {
        return $this->objectDLResponseType??StorageServiceConfig::CLSTORAGE_DEFAULT_DOWNLOAD_RESPONSETYPE;
    }

    /**
     * @param string $objectDLResponseType
     */
    public function setObjectDLResponseType(string $objectDLResponseType): void
    {
        $this->objectDLResponseType = $objectDLResponseType;
    }

    /**
     * @return string
     */
    public function getObjectDLHTTPContentTypeOverride() : ?string
    {
        return $this->objectDLHTTPContentTypeOverride;
    }

    /**
     * @param string $objectDLResponseType
     */
    public function setObjectDLHTTPContentTypeOverride(string $objectDLHTTPContentTypeOverride): void
    {
        $this->objectDLHTTPContentTypeOverride = $objectDLHTTPContentTypeOverride;
    }

    /**
     * @return string
     */
    public function getObjectVersionIdentifier() : ?string
    {
        return $this->versioneddate;
    }

    /**
     * @param string $objectVersionIdentifier
     */
    public function setObjectVersionIdentifier(string $objectVersionIdentifier): void
    {
        $this->versioneddate = $objectVersionIdentifier;
    }

    /**
     * @return string
     */
    public function getLastAccessDate() : ?string
    {
        return $this->lastaccessdate;
    }
}