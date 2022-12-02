<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer\Exceptions;

use Exception;
use Throwable;

class InvalidEmailHandlerDriverException extends Exception
{
    public function __construct(string $driver)
    {
        $message = "Invalid emails sender driver: `$driver`. Check your config.";
        parent::__construct($message);
    }
}