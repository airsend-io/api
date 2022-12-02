<?php declare(strict_types=1);
/*******************************************************************************
  Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use DI\Container as Container;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Log\LoggerInterface;

class TimerMiddleware
{
    protected $container;

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


        $response = $handler->handle($request);

        return $response; // Enable this during perf analysis

        /*
        $end = microtime(true);
        $total = round(($end - $start), 3);
        $this->container->get(LoggerInterface::class)->debug($request->getUri(). " took: ".$total.' secs');

        return $response;
        */
    }
}
