<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Auth\Exceptions;

use CodeLathe\Core\Utility\ContainerFacade;
use Exception;
use Psr\Log\LoggerInterface;

class InvalidTokenException extends Exception
{
    public function __construct(string $internalMessage)
    {
        // TODO - Concatenate the internal message to the exception when on development mode

        /** @var LoggerInterface $logger */
        $logger = ContainerFacade::get(LoggerInterface::class);
        $logger->debug($internalMessage);
        parent::__construct('Invalid token');
    }
}