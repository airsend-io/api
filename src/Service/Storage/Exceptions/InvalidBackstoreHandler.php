<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Exceptions;

class InvalidBackstoreHandler extends StorageServiceException
{
    public function __construct(string $class, string $previousMessage = '')
    {
        $message = "Backstore handler is not valid: `$class`. ";
        $message .= empty($previousMessage) ? '' : "Previous message: $previousMessage";
        parent::__construct($message);
    }
}