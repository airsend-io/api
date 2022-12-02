<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


use CodeLathe\Application\Middleware\ErrorHandlingMiddleware;
use CodeLathe\Application\Middleware\LogMiddleware;
use CodeLathe\Application\Middleware\NoBrowserCacheMiddleware;
use CodeLathe\Application\Middleware\RequestMiddleware;
use CodeLathe\Application\Middleware\TimerMiddleware;
use CodeLathe\Application\Middleware\CSecMiddleware;
use Slim\App;
use DI\Container as Container;
use CodeLathe\Application\ConfigRegistry;

return function (App $app, Container $container) {
    $appmode = $container->get(ConfigRegistry::class)['/app/mode'];
    if ($appmode == "dev") {
        $app->add(new TimerMiddleware($container));
    }
    $app->add(new CSecMiddleware($container));
    $app->add(RequestMiddleware::class);
    $app->add(new LogMiddleware($container));
    $app->add(ErrorHandlingMiddleware::class);
    $app->add(NoBrowserCacheMiddleware::class);
};
