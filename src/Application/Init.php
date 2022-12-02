<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

// ... Instantiate PHP-DI ContainerBuilder
use CodeLathe\Application\ConfigRegistry;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

return function() {
    $containerBuilder = new ContainerBuilder();

    if (getenv('COMPILE_DI') ?: false) { // ... Should be set to true in production and cleaned up after upgrades
        $containerBuilder->enableCompilation(CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'container');
    }

    // ... Create Dependencies and load them into the Container
    $dependencies = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Dependencies.php';
    $dependencies($containerBuilder);

    // ... Build PHP-DI Container instance
    $container = $containerBuilder->build();
    \CodeLathe\Core\Utility\ContainerFacade::setUp($container);
    return $container;
};