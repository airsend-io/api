<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use CodeLathe\Core\Infrastructure\CriticalSection;
use DI\Container as Container;
use DI\DependencyException;
use DI\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Class GlobalRequestMiddleware
 *
 * This middleware set some convenience request attributes, that can be used inside the managers methods.
 * It's an application middleware.
 *
 * @package CodeLathe\Application\Middleware
 */
class CSecMiddleware implements MiddlewareInterface
{

    /**
     * @var Container
     */
    protected $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return Response
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // ... Initialize the Session ID
        $sessionid = microtime(true);
        $request = $request->withAttribute('sessionid', $sessionid);

        $response = $handler->handle($request);

        // ... Release all Pending Critical Section
        $this->container->get(CriticalSection::class)->releaseAllForSession($sessionid);

        return $response;
    }
}