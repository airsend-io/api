<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware\Auth;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\OAuth\ClientDataStore;
use CodeLathe\Core\Data\OAuth\ScopeDataStore;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\LoggerFacade;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Exception;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Class AuthMiddleware
 *
 * All routes that requires authentication should include this middleware
 *
 * We're intentionally using the ContainerFacade in place of constructor injection here, because we need the constructor
 * to receive the parameters set on the route for the middleware.
 *
 * TODO - Try to find a way to pass the parameters through middleware without locking the constructor (like Laravel does)
 * TODO - It's more about wild-cards on the container DI.
 *
 * @package CodeLathe\Application\Middleware\Auth
 */
class AuthMiddleware implements MiddlewareInterface
{

    use AuthMiddlewareTrait;

    /**
     * @var bool
     */
    protected $defaultAuth;

    /**
     * @var bool
     */
    protected $notificationAuth;

    /**
     * @var bool
     */
    protected $publicAuth;

    /**
     * @var bool
     */
    protected $checkTTL;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var ResourceServer
     */
    protected $oauthResourceServer;

    /**
     * @var ClientDataStore
     */
    protected $oauthClientDataStore;
    /**
     * @var bool
     */
    private $allowCallHash;

    /**
     * AuthMiddleware constructor.
     * @param bool $allowDefaultAuth
     * @param bool $allowNotificationAuth
     * @param bool $allowPublicAuth
     * @param bool $checkTTL
     */
    public function __construct(bool $allowDefaultAuth = true, bool $allowNotificationAuth = false, bool $allowPublicAuth = false, bool $checkTTL = true, bool $allowCallHash = false)
    {
        $this->defaultAuth = $allowDefaultAuth;
        $this->notificationAuth = $allowNotificationAuth;
        $this->publicAuth = $allowPublicAuth;
        $this->checkTTL = $checkTTL;
        $this->config = ContainerFacade::get(ConfigRegistry::class);
        $this->oauthResourceServer = ContainerFacade::get(ResourceServer::class);
        $this->oauthClientDataStore = ContainerFacade::get(ClientDataStore::class);
        $this->allowCallHash = $allowCallHash;
    }

    /**
     *
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandler $handler PSR-15 request handler
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandler $handler): ResponseInterface
    {

        // first try public channel "virtual" auth (authenticates using the public user)
        if ($this->publicAuth) {
            $authResult = $this->authenticateUsingPublicHash($request, ContainerFacade::get(DataController::class));

            // if success, handle...
            if ($authResult->isValid()) {
                return $handler->handle($request);
            }
        }

        // then try default auth (jwt)
        if ($this->defaultAuth) {

            // default auth allowed.., try it...
            $authResult = $this->authenticateUsingJwt($request, ContainerFacade::get(JwtServiceInterface::class), $this->checkTTL);

            // if success, handle...
            if ($authResult->isValid()) {
                return $handler->handle($request);
            }


            // try oauth server auth
            $authResult = $this->authenticateUsingOauthServer(
                $request,
                ContainerFacade::get(ResourceServer::class),
                ContainerFacade::get(ScopeDataStore::class)
            );

            // if success, handle...
            if ($authResult->isValid()) {
                return $handler->handle($request);
            } else {
                LoggerFacade::debug("Oauth authentication failed: `{$authResult->getMessage()}`");
            }
        }

        // try notification auth
        if ($this->notificationAuth) {
            $authResult = $this->authenticateUsingNotificationToken($request, ContainerFacade::get(DataController::class), $this->checkTTL);

            // if success, handle...
            if ($authResult->isValid()) {
                return $handler->handle($request);
            }
        }

        if ($this->allowCallHash) {
            $authResult = $this->authenticateUsingCallHash($request, ContainerFacade::get(DataController::class));
            // if success, handle...
            if ($authResult->isValid()) {
                return $handler->handle($request);
            }
        }

        // nothing worked, halt with a 401
        return JsonOutput::error('Unauthorized', 401)->write(new Response());

    }

    /**
     * @return ConfigRegistry
     */
    protected function getConfig(): ConfigRegistry {
        return $this->config;
    }

    protected function oauthClientDataStore(): ClientDataStore
    {
        return $this->oauthClientDataStore;
    }
}
