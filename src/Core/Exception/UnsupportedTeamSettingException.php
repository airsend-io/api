<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class UnsupportedTeamSettingException extends ASException
{

    public function __construct(string $setting)
    {
        $message = "Unsupported setting $setting.";
        parent::__construct($message);
    }
}