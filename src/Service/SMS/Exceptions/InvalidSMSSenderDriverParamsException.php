<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS\Exceptions;

use Exception;
use Throwable;

class InvalidSMSSenderDriverParamsException extends Exception
{
    public function __construct(Throwable $previous = null)
    {
        $message = 'Invalid SMS sender params. Check your config.';
        parent::__construct($message, 0, $previous);
    }
}