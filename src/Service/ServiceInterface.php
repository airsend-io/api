<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service;

/**
 * Describes a Service instance.
 */
interface ServiceInterface
{
    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string;

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string;

}
