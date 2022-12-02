<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Utility\ClientApp;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class FirebaseManager extends ManagerBase
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
     * FirebaseManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     */
    public function __construct(DataController $dataController, LoggerInterface $logger)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidArgumentException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function connect(Request $request, Response $response): Response
    {

        $params = $request->getParsedBody();

        // first grab the Firebase Registration Client Token from request (token that uniquely identifies a device on Firebase)
        $fcmToken = $params['token'] ?? '';
        if (empty($fcmToken)) {
            $this->logger->debug('firebase.connect: No token defined on request. Aborting!');
            return JsonOutput::error("A token is required", 422)->write($response);
        }


        // parse the mobile app been registered, based on User-Agent
        if ($userAgent = $request->getHeader('User-Agent')[0] ?? null) {
            $mobileApp = ClientApp::identifyFromUserAgent($userAgent) ?? 'unknown';
        } else {
            $mobileApp = 'unknown';
        }
        $this->logger->debug("firebase.connect: Device identified as `$mobileApp`");

        // get the previous token if it exists (in case it's a refresh connection)
        $previousToken = $params['previous_token'] ?? null;

        // then grab the logged user
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        // connect the device to the user
        $this->dataController->connectFcmDevice($fcmToken, $user->getId(), $mobileApp);

        // return the success response
        return JsonOutput::success(201)->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function disconnect(Request $request, Response $response): Response
    {

        $params = $request->getParsedBody();

        // first grab the token/device to be disconnected from the request
        $fcmToken = $params['token'] ?? '';
        if ($fcmToken === null) {
            return JsonOutput::error("A token is required", 422)->write($response);
        }

        $this->dataController->disconnectFCMDevice($fcmToken);

        // always return success, without telling a lot to the requester
        return JsonOutput::success(201)->write($response);

    }

    /**
     * @inheritDoc
     */
    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}
