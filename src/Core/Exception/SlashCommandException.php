<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

class SlashCommandException extends ASException
{

    public $httpCode = 400;

    public function __construct($httpCode = 400, $message = 'Command failed', $code = 0, \Exception $previous = null)
    {
        $this->httpCode = $httpCode;
        parent::__construct($message, $code, $previous);
    }

}