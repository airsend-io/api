<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage;

use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;

class StorageServiceFactory
{

    /**
     * Instantiates the Storage service class.
     *
     * The first parameter is any class that implements StorageServiceInterface
     * The second are the parameters passed to it's constructor. Is up to the caller to
     * send the necessary params.
     *
     * @param string $implementationClass
     * @param array $params
     * @return StorageServiceInterface
     */
    public function create(string $implementationClass, ...$params): StorageServiceInterface
    {
        return new $implementationClass(...$params);
    }
}