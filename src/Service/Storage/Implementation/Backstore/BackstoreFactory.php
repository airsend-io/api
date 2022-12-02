<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Implementation\Backstore;

use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\Exceptions\InvalidBackstoreHandler;
use CodeLathe\Service\Storage\Shared\RequestObject;
use CodeLathe\Service\Storage\Shared\ResponseObject;
use CodeLathe\Service\Storage\Shared\StorageObject;


/**
 * Base class for file storage
 * Class AbstractBackstore
 * @package CodeLathe\Service\Storage\Backstore
 */
class BackstoreFactory
{
    protected $singletonMap = [];
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
     * @return BackstoreInterface
     * @throws InvalidBackstoreHandler
     */
    public function create(string $storageZoneId): BackstoreInterface
    {

        //check if storage handler is already instantiated
        if (!isset($this->singletonMap[$storageZoneId])) {

            // no instance found, instantiate it
            $handlerClass = $this->storageConfig[$storageZoneId]['handler'];

            try {
                $handler = new $handlerClass($this->storageConfig[$storageZoneId]);
            } catch (\Exception $e) {
                throw new InvalidBackstoreHandler($handlerClass, $e->getMessage());
            }

            if (!($handler instanceof BackstoreInterface)) {
                throw new InvalidBackstoreHandler($handlerClass);
            }

            $handler->makeConnection();

            $this->singletonMap[$storageZoneId] = $handler;

        }

        return $this->singletonMap[$storageZoneId];

    }

}