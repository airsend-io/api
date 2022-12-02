<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Cron\CronServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class CronManager
{

    /**
     * @var CronServiceInterface
     */
    protected $cronService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * BackgroundDispatcher constructor.
     * @param CronServiceInterface $cronService
     * @param LoggerInterface $logger
     */
    public function __construct(CronServiceInterface $cronService, LoggerInterface $logger)
    {
        $this->cronService = $cronService;
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     * @throws InvalidFailedHttpCodeException
     */
    public function dispatch(Request $request, Response $response): Response
    {

        $params = $request->getParsedBody();

        if (!Utility::internalAuthenticate($params, $this->logger)) {
            return JsonOutput::error('Unauthorized', 401)->write($response);
        }

        $executedTasks = $this->cronService->dispatch();
        return JsonOutput::success()->withContent('executed_tasks', $executedTasks)->write($response);
    }

}

