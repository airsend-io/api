<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class NotFoundException extends HttpException
{

    public function __construct ($message = null, $code = 0, Exception $previous = null)
    {
        parent::__construct(404, $message, $code, $previous);
    }
}