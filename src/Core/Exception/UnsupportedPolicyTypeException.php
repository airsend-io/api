<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class UnsupportedPolicyTypeException extends ASException
{

    public function __construct(string $type)
    {
        parent::__construct("SERVER ERROR: Policy type `$type` is not supported");
    }

}