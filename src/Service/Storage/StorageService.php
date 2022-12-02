<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage;

use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;

use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\Unittest\QuickTest;
use Psr\Log\LoggerInterface;


/**
 * Describes a Service instance.
 *
 * @deprecated
 */
class StorageService implements ServiceInterface
{
    public static $logger;

    /** @var ServiceRegistryInterface */
    public static $registry;

    /** @var StorageWorker */
    private $worker;


    public function __construct(ServiceRegistryInterface $registry, LoggerInterface $logger, FSDatabaseService $db)
    {
        StorageService::$logger = $logger;
        StorageService::$registry = $registry;

        $this->worker = new StorageWorker($db);
    }

    /**
     * return name of the service
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return StorageService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "StorageService Service provides a thin wrapper around File Storage";
    }

    public static function logger(): LoggerService
    {
        return StorageService::$logger;
    }

    public static function registry(): ServiceRegistryInterface
    {
        return StorageService::$registry;
    }

    /**
     * generic service call to call for any request
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function service(RequestObject $requestObject): ResponseObject
    {
        //branch to request type validation
        switch ($requestObject->getRequestType()) {
            case RequestObject::REQUEST_ADDOBJECT:
                return $this->worker->addObject($requestObject);
            case RequestObject::REQUEST_DELETEOBJECT:
                return $this->worker->deleteObject($requestObject);
            case RequestObject::REQUEST_GETOBJECT:
                return $this->worker->getObject($requestObject);
            case RequestObject::REQUEST_GETOBJECTINFO:
                return $this->worker->getObjectInfo($requestObject);
            case RequestObject::REQUEST_GETFILELIST:
                return $this->worker->getObjectList($requestObject);
            case RequestObject::REQUEST_GETFILEVERSIONLIST:
                return $this->worker->getFileOldVersions($requestObject);
            case RequestObject::REQUEST_MOVEOBJECT:
                return $this->worker->moveObject($requestObject);
            case RequestObject::REQUEST_COPYOBJECT:
                return $this->worker->copyObject($requestObject);
            case RequestObject::REQUEST_SETOBJECTMETATAGS:
                return $this->worker->addObjectMetaTags($requestObject);
            case RequestObject::REQUEST_UPDATEOBJECTMETATAGS:
                return $this->worker->updateObjectMetaTags($requestObject);
            case RequestObject::REQUEST_GETOBJECTMETATAGS:
                return $this->worker->getObjectMetaTags($requestObject);
            case RequestObject::REQUEST_REMOVEOBJECTMETATAGS:
                return $this->worker->deleteObjectMetaTags($requestObject);
            case RequestObject::REQUEST_GETSIDECARLIST:
                return $this->worker->getAllSidecarsForFile($requestObject);
            case RequestObject::REQUEST_GETSIDECARINFO:
                return $this->worker->getSidecarObjectInfo($requestObject);
            case RequestObject::REQUEST_DELETEOBJECTSIDECARS:
                return $this->worker->deleteAllSidecarsForObject($requestObject);
                break;
            case RequestObject::REQUEST_GETFOLDERPROPERTIES:
                return $this->worker->getFolderProperties($requestObject);
                break;
            default:
                $msg = "Invalid request " . $requestObject->getRequestType();
                StorageService::logger()->error(__CLASS__ . ":" . __FUNCTION__ . ": " . $msg);
                return ResponseObject::createErrorResponse($msg);
        }
    }

    /**
     * API call to add a new file/folder to storage
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function addObject(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->addObject($requestObject);
    }

    /**
     * API call to delete a file/folder from storage
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function deleteObject(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->deleteObject($requestObject);
    }

    /**
     * API call to get file info from storage
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function getObjectInfo(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->getObjectInfo($requestObject);
    }

    /**
     * API call to download a file from storage
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function getObject(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->getObject($requestObject);
    }

    /**
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function getObjectList(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->getObjectList($requestObject);
    }

    /**
     * API call to move a file/folder to different location
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function moveObject(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->moveObject($requestObject);
    }

    /**
     * API call to copy a file/folder to another location
     * @param RequestObject $requestObject
     * @return ResponseObject
     */
    public function copyObject(RequestObject $requestObject): ResponseObject
    {
        return $this->worker->copyObject($requestObject);
    }

    /**
     * Function to run quick tests
     */
    public function test() : void
    {
        $quickTest = new QuickTest($this);
        $quickTest->quickTest();
    }
}