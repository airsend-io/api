<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Exceptions;

class NotAFolderException extends StorageServiceException
{
    public function __construct(string $path)
    {
        $message = "Path `$path` is not a folder.";
        parent::__construct($message);
    }
}