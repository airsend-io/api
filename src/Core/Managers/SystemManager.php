<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\PingEvent;
use CodeLathe\Core\Utility\App;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\RequestValidator;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class SystemManager extends ManagerBase
{

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var RtmOperations
     */
    private $rtmOPs;
    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * SystemManager constructor.
     * @param LoggerInterface $logger
     * @param DataController $dataController
     * @param RtmOperations $rtmOperations
     * @param EventManager $eventManager
     */
    public function __construct(LoggerInterface $logger,
                                DataController $dataController,
                                RtmOperations $rtmOperations,
                                EventManager $eventManager
    )
    {
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->eventManager = $eventManager;
        $this->rtmOPs = $rtmOperations;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function info(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $info = [
            'name' => 'AirSend',
            'version' =>  ContainerFacade::get(App::class)->version()

        ];

        return JsonOutput::success()->withContent('info', $info)->write($response);
    }

    /**
     * Send a ping back via websocket with the supplied payload. The intent is to test the full path to make sure
     * nothing is broken in the chain. Do not bypass to send response via WS
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws DatabaseException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidArgumentException
     */
    public function ping(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };
        $params = $request->getParsedBody();

        // send a round trip to a specific user
        if (!RequestValidator::validateRequest(['finger_print', 'ping_token'], $params, $response)) {
            return $response;
        }

        if (empty($rtmToken = $this->rtmOPs->getRtmTokenForFP($user->getId(), $params['finger_print']))) {
            $this->logger->debug(__FUNCTION__ . ": NOT FOUND: Finger print ".$params['finger_print'] ." for user  : ". $user->getDisplayName());
            return JsonOutput::error('finger print not found', 404)->write($response);
        }

        if ($rtmToken->userId() != $user->getId()) {
            $this->logger->debug(__FUNCTION__ . $rtmToken->userId() . " != " . $user->getId() ." for Fingerprint " . $params['finger_print']);
            return JsonOutput::error('Unauthorized', 401)->write($response);
        }

        // Now we are ready to send the ping
        $event = new PingEvent($rtmToken, $params['ping_token']);
        $this->eventManager->publishEvent($event);

        return JsonOutput::success()->write($response);
    }

        /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }
}