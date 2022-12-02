<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use Elasticsearch\Client;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */

if(php_sapi_name() == "cli") {

    /** @var Client $elasticClient */
    $elasticClient = $container->get(Client::class);

    $results = $elasticClient->cat()->indices([
        'index' => '*'
    ]);

    foreach ($results as $item) {
        if (!preg_match('/^\./', $item['index'])) {
            echo $item['index'] . PHP_EOL;
        }
    }

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}