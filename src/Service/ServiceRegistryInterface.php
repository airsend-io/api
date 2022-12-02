<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service;

/**
 * Describes a Service Registry instance.
 */
interface ServiceRegistryInterface extends \ArrayAccess
{
    /**
     * return value for provided key
     *
     * @param string $key
     * @return mixed value associated with key
     */
    public function get(string $key);
}
