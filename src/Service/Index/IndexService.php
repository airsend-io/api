<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Index;

use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;

/**
 * Describes a Service instance.
 */
class IndexService implements ServiceInterface
{
    public function __construct(ServiceRegistryInterface $registry)
    {
    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return IndexService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "Index Service provides a thin wrapper around solr";
    }

}