<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Admin;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\UserLoginEvent;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class AuthAdminManager
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var JwtServiceInterface
     */
    protected $jwt;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * AuthManager constructor.
     * @param LoggerInterface $logger
     * @param JwtServiceInterface $jwt
     * @param DataController $dataController
     * @param ConfigRegistry $config
     * @param EventManager $eventManager
     */
    public function __construct(
        LoggerInterface $logger,
        JwtServiceInterface $jwt,
        DataController $dataController,
        ConfigRegistry $config,
        EventManager $eventManager
    )
    {
        $this->logger = $logger;
        $this->jwt = $jwt;
        $this->dataController = $dataController;
        $this->config = $config;
        $this->eventManager = $eventManager;
    }

    /**
     * Log the user in using an email and password
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['email', 'password'], $params, $response)) {
            return $response;
        }

        $user = $this->dataController->getUserByEmail($params['email']);


        if (!isset($user) || !password_verify($params['password'], $user->getPassword())) {
            return JsonOutput::error("Failed to Login", 401)->write($response);
        }

        if (!User::isServiceAdmin($user->getId())) {
            return JsonOutput::error("Invalid Account", 401)->write($response);
        }

        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        $jwtToken = $this->jwt->issueToken(
            $user->getId(),
            $auth->getClientIp(),
            $auth->getUserAgent(),
            true
        );

        // Raise user logged in event
        $event = new UserLoginEvent($user->getId());
        $this->eventManager->publishEvent($event);

        if ($this->config->get('/app/authcookieenable') == '1') {
            $response = $response->withHeader('Set-Cookie', 'token=' . $jwtToken);
        }

        $normalizedUser = ContainerFacade::get(NormalizedObjectFactory::class)->normalizedObject($user, null,false);
        return JsonOutput::success()->withContent('token', $jwtToken)->withContent('user', $normalizedUser)->write($response);
    }

    /**
     * Refresh the token for an already logged user
     *
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        // create a new token (only authenticated)
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');
        $jwtToken = $this->jwt->issueToken(
            $auth->getUserId(),
            $auth->getClientIp(),
            $auth->getUserAgent(),
            true
        );

        // give it to the client
        return JsonOutput::success()->withContent('token', $jwtToken)->write($response);
    }

}