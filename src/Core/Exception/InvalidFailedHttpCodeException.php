<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class InvalidFailedHttpCodeException extends ASException
{
    public function __construct(int $code)
    {
        parent::__construct("Invalid failure http code: $code");
    }
}