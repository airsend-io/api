<?php declare(strict_types=1);
/*******************************************************************************
  Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use CodeLathe\Core\Utility\LoggerFacade;
use CodeLathe\Core\Utility\RequestHelper;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use DI\Container as Container;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Log\LoggerInterface;

class LogMiddleware
{
    protected $container;

    protected $skipLog = [
        '/api/v1/internal/bgprocess',
        '/api/v1/user.image.get',
        '/api/v1/channel.image.get',
        '/api/v1/file.thumb',
        '/api/v1/system.ping',
    ];

    public function __construct(Container $container) {
        $this->container = $container;
    }

     /**
     *  Middleware class
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $uriPath = $request->getURI()->getPath();
        $uriAr = explode('?', $uriPath );
        if ((count ($uriAr) > 0) && !in_array($uriAr[0], $this->skipLog)) {
            $ip = RequestHelper::getIp();
            $clientInfo = RequestHelper::getClientInfo();
            $this->container->get(LoggerInterface::class)->debug(
                '[' . $ip . '] [' . $clientInfo . '] '. $request->getMethod() . ' ' . $uriPath . '?' . $request->getURI()->getQuery()
            );
        }
        $response = $handler->handle($request);
        return $response;
    }
}
