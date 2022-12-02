<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

/**
 * Describes a Object instance.
 */
interface ObjectInterface
{
    // ...Must implement a getArray interface method
    public function getArray() : array;

}
