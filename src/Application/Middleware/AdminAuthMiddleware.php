<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Service\Auth\Exceptions\InvalidTokenException;
use CodeLathe\Service\Auth\JwtServiceInterface;
use CodeLathe\Service\Logger\LoggerService;
use DI\Container as Container;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class AdminAuthMiddleware implements MiddlewareInterface
{
    private $container;
    private $dataController;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    public function __construct(Container $container, DataController $dataController, ConfigRegistry $config)
    {
        $this->container = $container;
        $this->dataController = $dataController;
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandler $handler): ResponseInterface
    {
        $this->container->get(LoggerInterface::class)->debug('Admin AUTH Process');
        $auth = $request->getAttribute('auth');
        if (empty($auth) || get_class($auth) != Auth::class) {
            return JsonOutput::error("Internal Error: Admin Auth not set.", 500)->write(new Response());
        }

        $userId = $auth->getUserId();
        if (empty($userId)) {
            return JsonOutput::error("Internal Error: Admin User not set in Auth", 500)->write(new Response());
        }

        if (!$auth->getIsAdmin()) {
            return JsonOutput::error("Not Authorized", 403)->write(new Response());
        }

        $user = $this->dataController->getUserById($userId);

        if (empty($user) || !User::isServiceAdmin($user->getId()) || $user->getUserRole() < User::USER_ROLE_SUB_ADMIN) {
            return JsonOutput::error("Invalid Account", 403)->write(new Response());
        }

        $response = $handler->handle($request);
        return $response;
    }
}
