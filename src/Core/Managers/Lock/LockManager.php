<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Lock;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChatAuthorizationException;
use CodeLathe\Core\Exception\ChatInvalidAttachmentException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Channel\ChannelManager;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class LockManager extends ManagerBase
{
    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    protected $lockOps;

    /**
     * ChatManager constructor.
     * @param DataController $dataController
     * @param ChannelOperations $channelOps
     * @param LoggerService $logger
     * @param LockOperations $lockOperations
     */
    public function __construct(DataController $dataController,
                                ChannelOperations $channelOps,
                                LoggerService $logger,
                                LockOperations $lockOperations)
    {
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->channelOps = $channelOps;
        $this->lockOps = $lockOperations;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    /**
     * Lock a file
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function acquire (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; }

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fspath', 'expires_after_sec'], $params, $response)) {
            return $response;
        }

        try {
            $expiry ="";
            if (isset($params['expires_after_sec'])) {
                $expiry = date('Y-m-d H:i:s', strtotime("+".$params['expires_after_sec']." seconds"));
            }
            $lock = $this->lockOps->lock($user->getId(), $params['fspath'] , "", $expiry);
            if (empty($lock)) {
                return JsonOutput::error("Lock cannot be acquired", 400)->write($response);
            }
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->withContent('lock_id', $lock->id())->write($response);
    }

    /**
     *
     * Release a lock
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function release (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        try {
            $success = $this->lockOps->unlock($user->getId(), $params['fspath']);
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        if (!$success) {
            return JsonOutput::error()->write($response);
        }
        return JsonOutput::success()->write($response);
    }


    /**
     * Refresh a lock owned by the client
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function refresh (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['lock_id', 'expires_after_sec'], $params, $response)) {
            return $response;
        }

        try {
            $expiry ="";
            if (isset($params['expires_after_sec'])) {
                $expiry = date('Y-m-d H:i:s', strtotime("+".$params['expires_after_sec']." seconds"));
            }

            $lock = $this->lockOps->getLockById((int)$params['lock_id']);
            if (empty($lock)) {
                $this->logger->error(__FUNCTION__ . " Invalid lock id : ". $params['lock_id']);
                return JsonOutput::error("Invalid Lock Id", 404)->write($response);
            }

            if ((int)$lock->userId() != (int)$user->getId()) {
                $this->logger->error(__FUNCTION__ . " Unauthorized lock access - User does not own lock : ". $params['lock_id']);
                return JsonOutput::error("Unauthorized", 401)->write($response);
            }

            $lock->setExpiry($expiry);

            $success = $this->lockOps->update($lock);
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        if (!$success) {
            return JsonOutput::error()->write($response);
        }
        return JsonOutput::success()->write($response);
    }






}