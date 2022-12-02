<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service;

use Exception;

/**
 * Class ServiceException
 * Services should throw their concrete classes derived from this base exception
  */
abstract class ServiceException extends Exception
{
    /**
     * ServiceException constructor.
     * @param string|null $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);

        // ... Do more stuff later
    }
}