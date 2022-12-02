<?php declare(strict_types=1);
/*******************************************************************************
  Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
use CodeLathe\Application\Handlers\HttpErrorHandler;
use CodeLathe\Application\Handlers\ShutdownHandler;
use CodeLathe\Application\ResponseEmitter\ResponseEmitter;
use CodeLathe\Application\ConfigRegistry;

use CodeLathe\Core\Messaging\EventCallbacks;
use CodeLathe\Service\Logger\LoggerService;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

//
define('REQUEST_DEBUG_ID', uniqid('req_'));
define('REQUEST_DEBUG_TIMER', microtime(true));

// ... Create our canonical directory path to the document root
define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/..'));

require CL_AS_ROOT_DIR . '/vendor/autoload.php';

// ... Setup Temp Error Handlers before Slim Error Handler Takes over
require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'TmpErrorHandlers.php';

// ... Init Container
$containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
$container = $containerIniter();

\CodeLathe\Core\Utility\ContainerFacade::setUp($container);
\CodeLathe\Core\Utility\LoggerFacade::setUp($container);
\CodeLathe\Core\Utility\I18n::setUp($container);


// ... Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();
$callableResolver = $app->getCallableResolver();

// ... Register middleware
$middleware = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Middleware.php';
$middleware($app, $container);

// ... Register routes
$routes = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Routes.php';
$routes($app);

$appmode = $container->get(ConfigRegistry::class)['/app/mode'];
$displayErrors  = ($appmode == "dev") ? true: false;

// ... Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// ... Create Error Handler
$responseFactory = $app->getResponseFactory();
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

// ... Create Shutdown Handler
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrors);
register_shutdown_function($shutdownHandler);

// ... Made it this far, switch to framework's error handlers
restore_exception_handler();
restore_error_handler();

// ... Add the cors Middleware before routing
$app->add(\CodeLathe\Application\Middleware\CorsMiddleware::class);

// ... Add Routing Middleware
$app->addRoutingMiddleware();

// ... Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrors, false, false);
$errorMiddleware->setDefaultErrorHandler($errorHandler); // Our Error Handler is used all the time

// ... Install Subscribers
$container->get(EventCallbacks::class)->registerSubscribers();

// ... Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
