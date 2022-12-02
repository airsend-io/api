<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class UnprocessableEntityException extends HttpException
{

    public function __construct ($message = null, $code = 0, Exception $previous = null)
    {
        parent::__construct(422, $message, $code, $previous);
    }
}