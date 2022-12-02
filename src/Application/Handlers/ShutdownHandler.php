<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Handlers;

use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\ResponseEmitter;

class ShutdownHandler
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var HttpErrorHandler
     */
    private $errorHandler;

    /**
     * @var bool
     */
    private $displayErrorDetails;

    /**
     * ShutdownHandler constructor.
     *
     * @param Request       $request
     * @param $errorHandler $errorHandler
     * @param bool          $displayErrorDetails
     */
    public function __construct(
        Request $request,
        HttpErrorHandler $errorHandler,
        bool $displayErrorDetails
    ) {
        $this->request = $request;
        $this->errorHandler = $errorHandler;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    public function __invoke()
    {

        $error = error_get_last();
        if ($error) {
            $errorFile = $error['file'];
            $errorLine = $error['line'];
            $errorMessage = $error['message'];
            $errorType = $error['type'];
            $message = 'An error while processing your request. Please try again later.';

            switch ($errorType) {
                case E_USER_ERROR:
                    $message = "FATAL ERROR: {$errorMessage}. ";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;

                case E_USER_WARNING:
                    $message = "WARNING: {$errorMessage}";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;

                case E_USER_NOTICE:
                    $message = "NOTICE: {$errorMessage}";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;

                default:
                    $message = "ERROR: {$errorMessage}";
                    $message .= " on line {$errorLine} in file {$errorFile}.";
                    break;
            }

            // ... Always Log Errors
            $logger = ContainerFacade::get(LoggerInterface::class);
            $logger->error($message);

            if ($this->displayErrorDetails) {
                $message = 'An error while processing your request. Please try again later.';
            }

            $exception = new HttpInternalServerErrorException($this->request, $message);
            $response = $this->errorHandler->__invoke($this->request, $exception, $this->displayErrorDetails, false, false);

            if(ob_get_length()){
                ob_clean();
            }

            // always release all critical section locks when an exception happens (basically garbage collecting, since they should be already released)
            /** @var CriticalSection $cs */
            $cs = ContainerFacade::get(CriticalSection::class);
            $cs->releaseAllForSession($this->request->getAttribute('sessionid'));

            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}
