<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

/**
 * Class CorsMiddleware
 *
 * This middleware set the CORS headers
 *
 * @package CodeLathe\Application\Middleware
 */
class RateLimitMiddleware implements MiddlewareInterface
{

    /**
     * @var int
     */
    protected $hits = 0;

    /**
     * @var int
     */
    protected $expiresAt;

    /**
     * @var int
     */
    protected $maxHits;

    /**
     * @var int
     */
    protected $decaySeconds;

    /**
     * This array defines the list of query params that should be considered to generate the unique request hash
     *
     * @var array
     */
    protected $includedQueryParams;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    public function __construct(int $maxHits = 60, int $decaySeconds = 60, array $includedQueryParams = [])
    {
        $this->maxHits = $maxHits;
        $this->decaySeconds = $decaySeconds;
        $this->includedQueryParams = $includedQueryParams;
        $this->cache = ContainerFacade::get(CacheItemPoolInterface::class);
        $this->expiresAt = time();
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $key = 'rate_limit.' . $this->resolveRequestSignature($request);

        if ($this->tooManyAttempts($key)) {
            $response = JsonOutput::error('Too Many Requests', 429)->write(new Response());
        } else {
            $response = $handler->handle($request);
        }

        return $this->addHeaders($response);

    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function resolveRequestSignature(ServerRequestInterface $request)
    {

        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        $routeContext = RouteContext::fromRequest($request);

        $key = $routeContext->getRoutingResults()->getMethod() . '|' . $routeContext->getRoutingResults()->getUri() . '|' . $auth->getClientIp();
        if (!empty($this->includedQueryParams)) {
            $params = $request->getQueryParams();
            $includedParams = array_intersect_key($params, array_flip($this->includedQueryParams));
            $key .= '|' . implode('|', $includedParams);
        }
        return sha1($key);

    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function tooManyAttempts(string $key): bool
    {

        $hitsCacheItem = $this->cache->getItem($key);
        $timerCacheItem = $this->cache->getItem("$key.timer");

        if ($hitsCacheItem->isHit() && $timerCacheItem->isHit()) {

            // it's a hit, so the rate limiter is already initialized, so just increment it and the the expiration time
            $this->hits = $hitsCacheItem->get() + 1;
            $this->expiresAt = $timerCacheItem->get();
            $hitsCacheItem->set($this->hits)->expiresAfter($this->expiresAt - time());

        } else {

            // not a hit, so the rate limiter have to be initialized
            $this->hits = 1;
            $hitsCacheItem->set($this->hits)->expiresAfter($this->decaySeconds);

            // set the expiration time based on the decay time
            $this->expiresAt =  time() + $this->decaySeconds;
            $timerCacheItem->set($this->expiresAt);
        }

        $this->cache->save($hitsCacheItem);
        $this->cache->save($timerCacheItem);

        // check the limit
        if ($this->hits > $this->maxHits) {
            return true;
        }

        return false;
    }

    protected function addHeaders(ResponseInterface $response): ResponseInterface
    {

        $retryAfter = $this->expiresAt - time();
        $remaining = $this->maxHits - $this->hits;

        $response = $response
            ->withHeader('X-RateLimit-Max', $this->maxHits)
            ->withHeader('X-RateLimit-Hits', $this->hits)
            ->withHeader('X-RateLimit-Remaining', $remaining >= 0 ? $remaining : 0)
            ->withHeader('X-RateLimit-ResetAt', $this->expiresAt)
            ->withHeader('X-RateLimit-ResetAfter', $retryAfter);
        if ($this->maxHits < $this->hits) {
            $response = $response->withHeader('RetryAfter', $retryAfter);

        }

        return $response;
    }

}