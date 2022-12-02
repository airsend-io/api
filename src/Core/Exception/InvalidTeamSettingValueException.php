<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class InvalidTeamSettingValueException extends ASException
{

    public function __construct(string $setting, string $value)
    {
        $message = "Invalid value `$value` for `$setting`.";
        parent::__construct($message);
    }
}