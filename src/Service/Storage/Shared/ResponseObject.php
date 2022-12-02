<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Shared;


use CodeLathe\Core\Objects\Folder;
use CodeLathe\Service\Storage\Util\StorageServiceUtility;
use \GuzzleHttp\Psr7\Stream;
/**
 * Class ResponseObject
 * @package CodeLathe\Service\Storage\Shared
 * @deprecated
 */
class ResponseObject
{
    public const RESPONSETYPE_GETFILEINFO = "GetFileInfo";
    public const RESPONSETYPE_GETFILELIST = "GetFileList";
    public const RESPONSETYPE_DOWNLOADREDIRECT = "DownloadRedirect";
    public const RESPONSETYPE_DOWNLOADSTOREANDFORWARD = "DownloadStoreAndForward";
    public const RESPONSETYPE_DOWNLOADASSTREAM = "DownloadAsStream";
    public const RESPONSETYPE_GETFOLDERPROPERTIES = "GetFolderProperties";

    private $success = TRUE;
    private $message = NULL;
    private $responseType = NULL;
    private $storageObjects = array();
    private $folderPropertiesObject = NULL;
    private $downloadinfo = NULL;
    private $totalCount = 0;

    /**
     * ResponseObject constructor.
     * @param string $type
     */
    private function __construct(string $type)
    {
        $this->responseType = $type;
    }

    /**
     * @param string $message
     * @return ResponseObject
     */
    public static function createErrorResponse(string $message) : ResponseObject
    {
        $requestObj = new ResponseObject("");
        $requestObj->setMessage($message);
        $requestObj->setSuccess(FALSE);
        return $requestObj;
    }

    /**
     * @param string $message
     * @return ResponseObject
     */
    public static function createSuccessResponse(string $message) : ResponseObject
    {
        $requestObj = new ResponseObject("");
        $requestObj->setMessage($message);
        return $requestObj;
    }

    /**
     * @param array $storageObjects
     * @return ResponseObject
     */
    public static function createGetFileListResponse(array $storageObjects, int $count = -1) : ResponseObject
    {
        $requestObj = new ResponseObject(self::RESPONSETYPE_GETFILELIST);
        $requestObj->setStorageObjects($storageObjects);
        $requestObj->setTotalCount($count);
        return $requestObj;
    }

    /**
     * @param array $storageObjects
     * @return ResponseObject
     */
    public static function createGetFileInfoResponse(StorageObject $storageObject) : ResponseObject
    {
        $requestObj = new ResponseObject(self::RESPONSETYPE_GETFILEINFO);
        $requestObj->setStorageObject($storageObject);
        return $requestObj;
    }

    /**
     * @param FolderPropertiesObject
     * @return ResponseObject
     */
    public static function createGetFolderPropertiesResponse(FolderPropertiesObject $folderPropertiesObject) : ResponseObject
    {
        $requestObj = new ResponseObject(self::RESPONSETYPE_GETFOLDERPROPERTIES);
        $requestObj->setFolderPropertiesObject($folderPropertiesObject);
        return $requestObj;
    }
    /**
     * @param string location
     * @return ResponseObject
     */
    public static function createDownloadRedirectResponse(string $location) : ResponseObject
    {
        $requestObj = new ResponseObject(self::RESPONSETYPE_DOWNLOADREDIRECT);
        $requestObj->setDownloadinfo($location);
        return $requestObj;
    }
    /**
     * @param GuzzleHttp\Psr7\Stream $stream
     * @return ResponseObject
     */
    public static function createDownloadAsStreamResponse(Stream $stream) : ResponseObject
    {
        $requestObj = new ResponseObject(self::RESPONSETYPE_DOWNLOADASSTREAM);
        $requestObj->setDownloadinfo($stream);
        return $requestObj;
    }


    /**
     * @param array
     * @return ResponseObject
     */
    public static function createDownloadRangesResponse(array $rangeArray) : ResponseObject
    {
        $requestObj = new ResponseObject(self::RESPONSETYPE_DOWNLOADSTOREANDFORWARD);
        $requestObj->setDownloadinfo($rangeArray);
        return $requestObj;
    }

    /**
     * @return bool
     */
    public function isSuccess() : bool
    {
        return $this->success;
    }

    /**
     * @param mixed $success
     */
    public function setSuccess($success): void
    {
        $this->success = $success;
    }

    /**
     * @return null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param null $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * @return string|null
     */
    public function getResponseType(): ?string
    {
        return $this->responseType;
    }

    /**
     * @param string|null $responseType
     */
    public function setResponseType(?string $responseType): void
    {
        $this->responseType = $responseType;
    }

    /**
     * @return StorageObject[]
     */
    public function getStorageObjects(): array
    {
        return $this->storageObjects;
    }

    /**
     * @param array $storageObjects
     */
    public function setStorageObjects(array $storageObjects): void
    {
        $this->storageObjects = $storageObjects;
    }

    /**
     * @return StorageObject
     */
    public function getStorageObject(): ?StorageObject
    {
        return $this->storageObjects[0] ?? null;
    }

    /**
     * @param FolderPropertiesObject
     */
    public function setFolderPropertiesObject(FolderPropertiesObject $propertiesObject): void
    {
        $this->folderPropertiesObject = $propertiesObject;
    }

    /**
     * @return FolderPropertiesObject
     */
    public function getFolderPropertiesObject(): ?FolderPropertiesObject
    {
        return $this->folderPropertiesObject;
    }

    /**
     * @param array $storageObjects
     */
    public function setStorageObject(StorageObject $storageObject): void
    {
        $storageObjects = array();
        $storageObjects[] = $storageObject;
        $this->storageObjects = $storageObjects;
    }
    /**
     * @param StorageObject $fileObject
     * @param $seek_start
     * @param $seek_end
     * @param bool $isattachment
     */
    public static function getDownloadHeaders(StorageObject $fileObject, float $seek_start,
                                              float $seek_end, $isattachment = TRUE) : array
    {
        $headerArray = array();

        //Only send partial content header if downloading a piece of the file (IE workaround)
        if ($seek_start > 0 || $seek_end < ($fileObject->getFileSize() - 1)) {
            $headerArray[]  = 'HTTP/1.1 206 Partial Content';
        }

        $headerArray[]  = 'Accept-Ranges: bytes';
        $headerArray[]  = 'Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $fileObject->getFileSize();
        $headerArray[]  = 'Content-Type: ' . $fileObject->getHTTPContentType();
        if ($isattachment){
            $headerArray[]  = 'Content-Disposition: attachment; filename="'
                . StorageServiceUtility::getBrowserSpecificFileNameForDownload($fileObject->getName()) . '"';
        }
        else{
            $headerArray[]  = "Content-Disposition: filename=\""
                . StorageServiceUtility::getBrowserSpecificFileNameForDownload($fileObject->getName()) . "\"";
        }
        $headerArray[]  = 'Content-Length: ' . ($seek_end - $seek_start + 1);

        return $headerArray;

    }

    /**
     * @return string
     */
    public function getDownloadInfo()
    {
        return $this->downloadinfo;
    }

    /**
     * @param $downloadinfo
     */
    public function setDownloadinfo($downloadinfo): void
    {
        $this->downloadinfo = $downloadinfo;
    }

    /**
     * @return int
     */
    public function getTotalRowCount(): int
    {
        return $this->totalCount;
    }

    /**
     * @param int $totalCount
     */
    public function setTotalCount(int $totalCount): void
    {
        $this->totalCount = $totalCount;
    }
}