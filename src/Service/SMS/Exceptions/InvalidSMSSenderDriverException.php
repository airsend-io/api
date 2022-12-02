<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS\Exceptions;

use Exception;
use Throwable;

class InvalidSMSSenderDriverException extends Exception
{
    public function __construct(string $driver)
    {
        $message = "Invalid SMS sender driver: `$driver`. Check your config.";
        parent::__construct($message);
    }
}