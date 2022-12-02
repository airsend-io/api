<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Shared;

use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;


/**
 * Class RequestObject
 * @package CodeLathe\Service\Storage\Shared
 */
class RequestObject
{

    const REQUEST_GETFILELIST = "GetFileList";
    const REQUEST_GETFILEVERSIONLIST = "GetFileVersionList";
    const REQUEST_ADDOBJECT = "AddObject";
    const REQUEST_GETOBJECT = "GetObject";
    const REQUEST_GETOBJECTINFO = "GetObjectInfo";
    const REQUEST_DELETEOBJECT = "DeleteObject";
    const REQUEST_MOVEOBJECT = "MoveObject";
    const REQUEST_COPYOBJECT = "CopyObject";
    const REQUEST_SETOBJECTMETATAGS = "SetObjectMetaTags";
    const REQUEST_GETOBJECTMETATAGS = "GetObjectMetaTags";
    const REQUEST_UPDATEOBJECTMETATAGS = "UpdateObjectMetaTags";
    const REQUEST_REMOVEOBJECTMETATAGS = "RemoveObjectMetaTags";
    const REQUEST_GETSIDECARLIST = "GetSidecarList";
    const REQUEST_GETSIDECARINFO = "GetSidecarInfo";
    const REQUEST_DELETEOBJECTSIDECARS = "DeleteObjectSidecars";
    const REQUEST_GETFOLDERPROPERTIES = "GetFolderProperties";

    private $errorMessage = NULL;

    private $requestType = NULL;
    private $requestParameters = array();
    /** @var StorageObject $srcStorageObject  */
    private $srcStorageObject = NULL;
    /** @var StorageObject $dstStorageObject  */
    private $dstStorageObject = NULL;

    private $offset = 0;
    private $limit = 10;
    private $overwrite = FALSE;
    private $donotUpdateDstSyncVersion = FALSE;

    private $download_docconvert = FALSE;
    private $download_redirect = FALSE;
    private $download_contenttype = NULL;
    private $download_attachment = TRUE;

    private function __construct(string $operation, array $params)
    {
        $this->requestType = $operation;
        $this->requestParameters = $params;
    }

    /**
     * @return string|null
     */
    public function getRequestType(): ?string
    {
        return $this->requestType;
    }

    /**
     * @return null
     */
    public function getErrorMessage() : ?string
    {
        return $this->errorMessage;
    }

    /**
     * @param null $errorMessage
     */
    public function setErrorMessage($errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return Storage
     */
    public function getSrcStorageObject(): ?StorageObject
    {
        return $this->srcStorageObject;
    }

    /**
     * @param null $srcStorageObject
     */
    public function setSrcStorageObject(array $params): void
    {
        $this->srcStorageObject = StorageObject::createStorageObject($params);
    }

    /**
     * @return null
     */
    public function getDstStorageObject()
    {
        return $this->dstStorageObject;
    }

    /**
     * @param null $dstStorageObject
     */
    public function setDstStorageObject(array $params): void
    {
        $this->dstStorageObject = StorageObject::createStorageObject($params);
    }

    /**
     * @param string $reqtype
     * @param array $params
     * @return RequestObject
     */
    public static function createRequest(string $reqtype, array $params) : RequestObject
    {
        $requestObj = new RequestObject($reqtype, $params);
        $requestObj->setSrcStorageObject($params);
        return $requestObj;
    }

    /**
     * Function to validate incoming request
     * @param string $requestType
     * @return bool
     */
    public function validate(string $requestType) : bool
    {
        //branch to request type validation
        switch ($this->getRequestType()){
            case RequestObject::REQUEST_GETFILELIST:
                $this->validateGetFileListRequest();
                break;
            case RequestObject::REQUEST_GETFILEVERSIONLIST:
                $this->validateGetFileVersionList();
                break;
            case RequestObject::REQUEST_DELETEOBJECT:
                $this->validateDeleteRequest();
                break;
            case RequestObject::REQUEST_ADDOBJECT:
                $this->validateAddRequest();
                break;
            case RequestObject::REQUEST_GETOBJECT:
                $this->validateGetObjectRequest();
                break;
            case RequestObject::REQUEST_GETOBJECTINFO:
                $this->validateGetObjectInfoRequest();
                break;
            case RequestObject::REQUEST_MOVEOBJECT:
                $this->validateMoveRequest();
                break;
            case RequestObject::REQUEST_COPYOBJECT:
                $this->validateCopyRequest();
                break;
            case RequestObject::REQUEST_SETOBJECTMETATAGS:
                $this->validateAddAndUpdateMetaTagsRequest();
                break;
            case RequestObject::REQUEST_GETOBJECTMETATAGS:
                $this->validateGetAndRemoveMetaTagsRequest();
                break;
            case RequestObject::REQUEST_UPDATEOBJECTMETATAGS:
                $this->validateAddAndUpdateMetaTagsRequest();
                break;
            case RequestObject::REQUEST_REMOVEOBJECTMETATAGS:
                $this->validateGetAndRemoveMetaTagsRequest();
                break;
            case RequestObject::REQUEST_GETSIDECARLIST:
                $this->validateGetSidecarListRequest();
                break;
            case RequestObject::REQUEST_GETSIDECARINFO:
                $this->validateGetSidecarInfoRequest();
                break;
            case RequestObject::REQUEST_DELETEOBJECTSIDECARS:
                $this->validateSidecarsDeleteRequest();
                break;
            case RequestObject::REQUEST_GETFOLDERPROPERTIES:
                $this->validateGetFolderPropertiesRequest();
                break;
            default:
                $this->setErrorMessage("Invalid request ".$this->getRequestType());
                break;
        }

        //check for error message and return success
        $errormsg = $this->getErrorMessage();
        return (!isset($errormsg));
    }

    /**
     *
     */
    private function validateAddRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //check storage zone
        if(!$this->checkTypeAndStorageZone()) return;

        //check site id
        if(!$this->checkSiteId()) return;

        //check sidecar info (if applicable)
        if(!$this->checkSidecarInfo(FALSE)) return;

        //additional checks here

    }

    /**
     *
     */
    private function validateDeleteRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //additional checks here
    }

    /**
     *
     */
    private function validateSidecarsDeleteRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //additional checks here
    }

    /**
     *
     */
    private function validateGetFileListRequest() : void
    {
        //get offset and limit
        if(isset($this->requestParameters[StorageObject::OBJECTLIST_OFFSET])){
           $this->offset = intval($this->requestParameters[StorageObject::OBJECTLIST_OFFSET]);
        }
        if(isset($this->requestParameters[StorageObject::OBJECTLIST_LIMIT])){
            $this->limit = intval($this->requestParameters[StorageObject::OBJECTLIST_LIMIT]);
        }

        //other validations
       $this->checkNameAndParentPath();
    }

    /**
     *
     */
    private function validateGetFileVersionList() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

    }

    /**
     *
     */
    private function validateGetSidecarListRequest() : void
    {
        //no offset and limit for this list

        //other validations
        $this->checkNameAndParentPath();
    }

    /**
     *
     */
    private function validateMoveRequest() : void
    {
        //check source parentpath and name
        if(!$this->checkNameAndParentPath("source")) return;

        //check destination parentpath and name
        if(!$this->checkNameAndParentPath("destination")) return;

        //additional checks here
    }

    /**
     *
     */
    private function validateCopyRequest() : void
    {
        //get overwrite flag
        if(isset($this->requestParameters["overwrite"])){
            $this->overwrite = boolval($this->requestParameters["overwrite"]);
        }
        //check source parentpath and name
        if(!$this->checkNameAndParentPath("source")) return;

        //check destination parentpath and name
        if(!$this->checkNameAndParentPath("destination")) return;

        //additional checks here
    }

    /**
     *
     */
    private function validateAddAndUpdateMetaTagsRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //check if metatags are set
        if(!$this->checkMetaTags()) return;

        //additional checks here
    }

    /**
     *
     */
    private function validateGetAndRemoveMetaTagsRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //additional checks here
    }
    /**
     *
     */
    private function validateGetObjectRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //check if download type is valid
        if(!$this->checkDownloadResponseType()) return;

        //check sidecar info (if applicable)
        if(!$this->checkSidecarInfo(FALSE)) return;

        //get params
        $this->download_docconvert = isset($this->requestParameters["docconvert"])
            ?boolval($this->requestParameters["docconvert"]): FALSE;
        $this->download_attachment = isset($this->requestParameters["attachment"])
            ?boolval($this->requestParameters["attachment"]): TRUE;
        $this->download_contenttype = $this->requestParameters["contenttype"]??NULL;

    }

    /**
     *
     */
    private function validateGetObjectInfoRequest() : void
    {
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

    }

    /**
     *
     */
    private function validateGetSidecarInfoRequest() : void
    {
        $srcStorageObject = $this->getSrcStorageObject();
        if(!$srcStorageObject->isSideCarFile()){
            $this->setErrorMessage("Missing sidecar field");
            return;
        }
        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;

        //check sidecarinfo
        if(!$this->checkSidecarInfo(TRUE)) return;
    }

    /**
     *
     */
    private function validateGetFolderPropertiesRequest() : void
    {
        //check if the request is for all entire storage system
        if($this->isStorageRoot()){
            //no further checks needed
            return;
        }

        //check parentpath and name
        if(!$this->checkNameAndParentPath()) return;
    }


    /**
     * @return int
     */
    public function getPageOffset() : int
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getPageLimit() : int
    {
        return $this->limit;
    }

    /**
     * @return bool
     */
    public function isOverwrite() : bool
    {
        return $this->overwrite;
    }

    //download request related functions

    /**
     * @return bool
     */
    public function isDownloadADocconvert() : bool
    {
        return $this->download_docconvert;
    }

    /**
     * @return bool
     */
    public function isDownloadRedirect() : bool
    {
        return $this->srcStorageObject->getObjectDLResponseType() === ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT;
    }

    /**
     * @return bool
     */
    public function isDownloadAsStream() : bool
    {
        return $this->srcStorageObject->getObjectDLResponseType() === ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM;
    }
    /**
     * @return string|null
     */
    public function getDownloadContentType() : ?string
    {
        return $this->download_contenttype;
    }

    /**
     * @return bool
     */
    public function downloadAsAttachment() : bool
    {
        return $this->download_attachment;
    }

    /**
     * @return array
     */
    public function getDownloadRequestRangeArray() : array
    {
        //check if http_range is sent by browser (or download manager)
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            if ($size_unit == 'bytes') {
                //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                $rangeArray = explode(',', $range_orig);
            } else {
                $rangeArray = array('-');
            }
        } else {
            $rangeArray = array('-');
        }
        return $rangeArray;
    }


    /**
     * @param string $objectLocation
     * @return bool
     */
    private function isStorageRoot($objectLocation = " ") : bool
    {
        $storageObject = ($objectLocation === "source" || $objectLocation === " ") ?
            $this->getSrcStorageObject() : $this->getDstStorageObject();

        return $storageObject->isStorageRoot();
    }

    /**
     * @param string $objectLocation
     * @return bool
     */
    private function checkNameAndParentPath($objectLocation = " ") : bool
    {
        $storageObject = ($objectLocation === "source" || $objectLocation === " ") ?
            $this->getSrcStorageObject() : $this->getDstStorageObject();

        $name = $storageObject->getName();
        $parentpath = $storageObject->getParentPath();
        if(!isset($name)){
            $this->setErrorMessage("Missing ".$objectLocation."name");
            return FALSE;
        } else if(!StorageServiceUtility::isValidFileName($name)){
            $this->setErrorMessage("Invalid ".$objectLocation."name $name");
            return FALSE;
        } else if(!isset($parentpath)){
            $this->setErrorMessage("Missing ".$objectLocation."parent path");
            return FALSE;
        } else if($parentpath[0] != '/'){
            $this->setErrorMessage($objectLocation."parent path $parentpath doesn't start with /.");
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Function used for ADDOBJECT validation
     * @return bool
     */
    private function checkTypeAndStorageZone() : bool
    {
        global $g_log;

        $srcStorageObject = $this->getSrcStorageObject();
        $objectType = $srcStorageObject->getType();
        if(!isset($objectType)){
            $this->setErrorMessage("Object type not set");
            return FALSE;
        }

        //check if this is a valid type
        if(!$srcStorageObject->isFile() && !$srcStorageObject->isFolder() && !$srcStorageObject->isSideCarFile() ){
            $this->setErrorMessage("Invalid object type specified :".$objectType);
            return FALSE;
        }

        //no further checks needed for sidecarfile
        if($srcStorageObject->isSideCarFile()){
            return  TRUE;
        }

        //check storage zone id
        $storageZoneId = $srcStorageObject->getStorageZoneId();
        if(!isset($storageZoneId)){
            $this->setErrorMessage("Missing storage zone");
            return FALSE;
        }

        //check storage service health
        if(!StorageServiceUtility::isStorageBackendReady($storageZoneId))
        {
            $g_log->logDebug(__CLASS__.":".__FUNCTION__.": "."Backend storage $storageZoneId not ready.");
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @return bool
     */
    private function checkSiteId() : bool
    {
        global $g_log;

        $srcStorageObject = $this->getSrcStorageObject();
        if(!$srcStorageObject->isFolder()){
            $objectSiteId = $srcStorageObject->getObjectSiteId();
            if(!isset($objectSiteId)){
                $this->setErrorMessage("Object site id not set");
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * @return bool
     */
    private function checkDownloadResponseType() : bool
    {
        global $g_log;

        $srcStorageObject = $this->getSrcStorageObject();
        $objectDLResponseType = $srcStorageObject->getObjectDLResponseType();
        if(!isset($objectDLResponseType) || ($objectDLResponseType !== ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT
                && $objectDLResponseType !== ResponseObject::RESPONSETYPE_DOWNLOADSTOREANDFORWARD
                && $objectDLResponseType !== ResponseObject::RESPONSETYPE_DOWNLOADASSTREAM)){
            $this->setErrorMessage("Object download type not valid :".$objectDLResponseType);
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @return bool
     */
    private function checkMetaTags() : bool
    {
        global $g_log;

        /** @var StorageObject $srcStorageObject */
        $srcStorageObject = $this->getSrcStorageObject();
        if(!$srcStorageObject->isFile() && !$srcStorageObject->isFolder()){
            $this->setErrorMessage("Metatags can only be set for files or folders");
            return FALSE;
        }

        $metatags = $srcStorageObject->getMetaTagsAsString();
        if(!isset($metatags)){
            $this->setErrorMessage("Object metatags not set");
            return FALSE;
        }

        $isjson = json_decode($metatags);
        if(!$isjson){
            $this->setErrorMessage("Object metatags not valid :".$metatags);
            return FALSE;
        }

        return TRUE;
    }

    private function checkSidecarInfo(bool $checkTypeSideCar): bool
    {
        global $g_log;

        /** @var StorageObject $srcStorageObject */
        $srcStorageObject = $this->getSrcStorageObject();

        //check if sidecar metadata is set if objecttype is sidecar
        if($srcStorageObject->isSideCarFile()) {
            if(empty($srcStorageObject->getSidecarMetadataAsString())) {
                $this->setErrorMessage("Missing metadata for sidecar file type");
                return FALSE;
            }
            //sidecar file, no further checks needed
            return TRUE;
        } else{
            //not sidecar file
            //return false if type check is selected
            return $checkTypeSideCar?FALSE:TRUE;
        }



    }

    /**
     * @return bool
     */
    public function isDonotUpdateDstSyncVersion(): bool
    {
        return $this->donotUpdateDstSyncVersion;
    }

    /**
     * @param bool $donotUpdateDstSyncVersion
     */
    public function setDonotUpdateDstSyncVersion(bool $donotUpdateDstSyncVersion): void
    {
        $this->donotUpdateDstSyncVersion = $donotUpdateDstSyncVersion;
    }
}