<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Exception;

use CodeLathe\Core\Utility\ContainerFacade;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class ASException
 * Core classes should throw their concrete classes derived from this base exception
 */
abstract class ASException extends Exception
{
    /**
     * ASException constructor.
     * @param string|null $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        parent::__construct(isset($message)? $message:get_class($this), $code, $previous);

        // ... Do more stuff later
        /** @var LoggerInterface $logger */
        $logger = ContainerFacade::get(LoggerInterface::class);
        $exception = get_class($this);
        $file = $this->getFile();
        $line = $this->getLine();
        $logger->warning("Exception Raised ($exception) on `$file:$line` with message: ".$message);
    }
}