<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer\Exceptions;

use Exception;
use Throwable;

class InvalidEmailHandlerDriverParamsException extends Exception
{
    public function __construct(Throwable $previous = null)
    {
        $message = 'Invalid emails sender params. Check your config.';
        parent::__construct($message, 0, $previous);
    }
}