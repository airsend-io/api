<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Backstore;

use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;


/**
 * Base class for file storage
 * Class AbstractBackstore
 * @package CodeLathe\Service\Storage\Backstore
 * @deprecated Moved inside the MysqlS3 implementation
 */
abstract class AbstractBackstore
{
    protected static $backstoreMap = array();
    protected $storageConfig;

    /**
     * AbstractBackstore constructor.
     * @param $storageConfig
     */
    public function __construct($storageConfig)
    {
        $this->storageConfig = $storageConfig;
    }

    /**
     * @param string $storageZoneId
     * @return AbstractBackstore|null
     */
    public static function getInstance(string $storageZoneId): ?AbstractBackstore
    {

        //check if the instance is cached
        if(isset(AbstractBackstore::$backstoreMap[$storageZoneId]))
        {
            return AbstractBackstore::$backstoreMap[$storageZoneId];
        }

        //Not available in cache, create one
        /**@var AbstractBackstore $backstoreImpl*/
        $backstoreImpl;
        $storageZoneConfig = StorageServiceConfig::getStorageZoneConfig($storageZoneId);
        if(isset($storageZoneConfig['S3']))
        {
            $backstoreImpl = new S3BackstoreImpl($storageZoneConfig['S3']);
            $success = $backstoreImpl->makeConnection();
            if(!$success)
            {
                return NULL;
            }
        }
        else
        {
            $backstoreImpl = NULL;
        }
        AbstractBackstore::$backstoreMap[$storageZoneId] = $backstoreImpl;
        return $backstoreImpl;
    }

    /**
     * @return bool
     */
    abstract public function makeConnection() : bool ;

    /**
     * @param string $siteId
     * @param StorageObject $fileObject
     * @param StorageObject|null $copyFromObject
     * @return bool
     */
    abstract public function storeFile(StorageObject $fileObject, StorageObject $copyFromObject = null) : bool ;

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    abstract public function addToExistingFile(StorageObject $fileObject) : bool ;

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    abstract public function deleteFile(StorageObject $fileObject): bool;

    /**
     * @param StorageObject $fileObject
     * @param RequestObject $requestObject
     */
    abstract public function createDownloadResponse(StorageObject $fileObject, RequestObject $requestObject): ResponseObject;
}