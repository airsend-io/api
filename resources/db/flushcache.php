<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/*
 * Run this script to clear redis cache
 *
 */

use CodeLathe\Core\Data\CacheDataController;
use CodeLathe\Service\Cache\CacheService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Service\ServiceRegistryInterface;

/** @var ContainerInterface $container */

if(php_sapi_name() == "cli") {

    echo PHP_EOL;


    $cacheService = $container->get(CacheService::class);
    $cache = new CacheDataController($container);
    $cacheService->flush();

    echo "Clear Cache" . addPeriods(60 - strlen("Clear Cache")) . "DONE \n";
}


function addPeriods($count) {
    $str = ".";
    for($i=0; $i<$count; $i++){
        $str .= ".";
    }
    return $str;
}