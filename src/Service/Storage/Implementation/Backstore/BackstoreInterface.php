<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Implementation\Backstore;

use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;



interface BackstoreInterface
{


    /**
     * @return bool
     */
    public function makeConnection(): bool;

    /**
     * @param string $siteId
     * @param StorageObject $fileObject
     * @param StorageObject|null $copyFromObject
     * @return bool
     */
    public function storeFile(StorageObject $fileObject, StorageObject $copyFromObject = null): bool;

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function addToExistingFile(StorageObject $fileObject): bool;

    /**
     * @param StorageObject $fileObject
     * @return bool
     */
    public function deleteFile(StorageObject $fileObject): bool;

    /**
     * @param StorageObject $fileObject
     * @param RequestObject $requestObject
     */
    public function createDownloadResponse(string $type): ResponseObject;

    public function getRegion(): string;
}