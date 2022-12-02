<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ImageCacheMiddleware
 *
 * This middleware force the browser to cache the response
 *
 * @package CodeLathe\Application\Middleware
 */
class ImageCacheMiddleware implements MiddlewareInterface
{

    protected $decaySeconds;

    /**
     * ImageCacheMiddleware constructor.
     * @param int $decaySeconds By default is a giant value, so we keep the cache forever. It's up to the client to send a cache id value (based on the updated on timestamp to renew the resource).
     */
    public function __construct(int $decaySeconds = 31536000)
    {
        $this->decaySeconds = $decaySeconds;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $response = $response->withHeader('Cache-Control', "private, max-age={$this->decaySeconds}");

        return $response;

    }

}