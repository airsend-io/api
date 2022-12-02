<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidJsonBodyException;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\RequestEvent;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Service\Logger\LoggerService;
use MaxMind\Db\Reader;
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
class RequestMiddleware implements MiddlewareInterface
{

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var EventManager
     */
    protected $eventManager;

    public function __construct(ConfigRegistry $config, EventManager $eventManager)
    {
        $this->config = $config;
        $this->eventManager = $eventManager;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return Response
     * @throws InvalidFailedHttpCodeException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // drop methods that are not supported (we just support GET and POST)
        if (!in_array($request->getMethod(), ['GET', 'POST', 'OPTIONS'])) {
            return JsonOutput::error("Only GET, POST and OPTIONS are supported", 405)->write(new Response());
        }

        // store the start timestamp of the request
        $startts = microtime(true);

        // parses the body of the request (only for posts)
        // first we check if the body is already parsed (i.e. for form-requests, it will be already parsed)
        if ($request->getMethod() === 'POST' && $request->getParsedBody() === null && !empty($request->getBody()->getContents())) {
            $contentType = $request->getHeader('content-type');
            $contentType = trim($contentType[0]) ?? '';
            if (preg_match('/^([^\s;]+)/', $contentType, $matches)) {
                $contentType = $matches[1];
            }

            if (!empty($contentType)) {
                // This switch could be refactored on a strategy pattern, having a RequestParserInterface, and creating a
                // a JsonRequestParser implementing it. It would bring flexibility for additional formatters in the future,
                // but sounded too overkill for this simple task. If we need additional formats in the future, it would be
                // an good idea.
                switch ($contentType) {

                    // json support
                    case 'application/json':
                        try {
                            $request = $this->parseJsonBody($request);
                        } catch (InvalidJsonBodyException $e) {
                            return JsonOutput::error("Invalid json payload", 415)->write(new Response());
                        }
                        break;

                    // allow text plain requests
                    case 'text/plain':

                    // allow markdown preview support
                    case 'text/markdown':
                        break;

                    // ... other formats (like xml, yml, etc)

                    // format not supported
                    default:
                        return JsonOutput::error("Unsupported media type: $contentType", 415)->write(new Response());
                }
            }
        }

        $remoteIp = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // create the auth object and included it on the request
        $auth = new Auth($remoteIp, $userAgent);
        $request = $request->withAttribute('auth', $auth);

        I18n::setLocale($request->getHeader('accept-language')[0] ?? null);

        $response = $handler->handle($request);

        // find the request time in ms
        $time = (int)((microtime(true) - $startts) * 1000);

        // log the request time if biggen than the limit
        if ($time > $this->config->get('/app/slow_request_log_threshold')) {
            $now = CarbonImmutable::now();
            $file = 'slow_request_' . $now->format('Y-m-d') . '.log';
            $entry = '# Time: ' . $now->format('c') . ' - Request time: ' . $time . ' ms ' . PHP_EOL;
            $entry .= '    URI: ' . $request->getUri()->getPath() . PHP_EOL;
            if (!empty($query = $request->getUri()->getQuery())) {
                $entry .= '    QUERY: ' . $query . PHP_EOL;
            }
            if (!empty($body = $request->getBody()->getContents())) {
                $entry .= '    FORM: ' . $request->getBody()->getContents() . PHP_EOL;
            }
            file_put_contents(Directories::scratch("/logs/$file"), $entry, FILE_APPEND);
        }

        // raise the request event to store request stats
        $event = new RequestEvent($request, $remoteIp, $time);
        $this->eventManager->publishEvent($event);

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     * @throws InvalidJsonBodyException
     */
    protected function parseJsonBody(ServerRequestInterface $request): ServerRequestInterface
    {
        $body = $request->getBody()->getContents();
        $decoded = json_decode($body, true);
        if ($decoded === null) {
            throw new InvalidJsonBodyException();
        }
        return $request->withParsedBody($decoded);
    }
}