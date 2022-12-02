<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Config;


use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\StorageService;

/**
 * Class StorageServiceConfig
 * @package CodeLathe\Service\Storage\Config
 * @deprecated
 */
class StorageServiceConfig
{
    //filenames related
    public const TONIDO_DISABLE_DOTDOT = false;
    public const TONIDO_DISABLE_INVALIDUTF8_UPLOAD = true;

    //storage related
    public const CLSTORAGE_NO_OF_FILEVERSIONS = 3;
    public const CLSTORAGE_DEFERRED_DELETE = FALSE;
    public const CLSTORAGE_S3_TOPFOLDERPREFIX = "airsend";
    public const CLSTORAGE_DEFAULT_DOWNLOAD_RESPONSETYPE = ResponseObject::RESPONSETYPE_DOWNLOADREDIRECT;
    public const CLSTORAGE_STOREANDFORWARD_DOWNLOAD_LIMIT = 0;
    public const CLSTORAGE_NODE_COMMON_TEMP_FOLDER = ""; //setting empty string will make system to use scratch folder
    public const CLSTORAGE_S3_MULTIPART_CHUNKSIZE_IN_MB = 5;
    public const CLSTORAGE_MYFILES_VERSION_SKIPSIZE = 1073741824;

    /**
     * @param string $zoneId
     * @return array
     */
    public static function getStorageZoneConfig(string $zoneId) : ?array
    {
        if($zoneId == "S3_US_EAST1")
        {
            $bucketName = StorageService::registry()->get("/storage/s3/bucketname");
            $key = StorageService::registry()->get("/storage/s3/key");
            $secret = StorageService::registry()->get("/storage/s3/secret");
            $region = StorageService::registry()->get("/storage/s3/region");
            return array(
                "S3" => array(
                    "bucketname" => $bucketName,
                    "key" => $key,
                    "secret" => $secret,
                    "region" => $region,
                    "endpoint" => "",
                    "proxy" => NULL,
                    "cacertpath" => NULL,
                    "encryptionenabled" => FALSE,
                    "reducedredundancy" => FALSE,
                )
            );
        }
        return NULL;
    }
    
    public static function getDefaultStorageZoneID() : string
    {
        //this will be changed to dynamic id from DB in future
        return "S3_US_EAST1";
    }
}