<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Exception;

class GateActionNotFoundException extends ASException
{
    public function __construct(string $gateClass, string $action)
    {
        parent::__construct("Gate action `$action` was not found on gate `$gateClass`. Have you forget to create the action method `$action` inside `$gateClass`");
    }
}