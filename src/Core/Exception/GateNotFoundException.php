<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

use Exception;

class GateNotFoundException extends ASException
{
    public function __construct(string $gateClass)
    {
        parent::__construct("Gate class not found for `$gateClass`. Have you forget to create the gate and link it to the class on AuthorizationHandler?");
    }
}