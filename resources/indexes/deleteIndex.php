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

    $indexName = array_reduce($argv, function($carry, $item) {
        if (preg_match('/^--index=(.+)$/', $item, $matches)) {
            return $matches[1];
        }
        return $carry;
    }, null);

    if (empty($indexName)) {
        echo "ERROR: `index` param is required!" . PHP_EOL;
        exit(1);
    }

    if (!$elasticClient->indices()->exists(['index' => $indexName])) {
        echo "ERROR: Index `$indexName` doesn't exists..." . PHP_EOL;
        exit(2);
    }

    $response = readline("Dropping index `$indexName`... Confirm? (n): ");

    if (!preg_match('/^y$|^yes$/i', $response)) {
        echo 'Canceled by the user. Abortting...' . PHP_EOL;
        exit(3);
    }

    echo 'DROP THE INDEX `' . $indexName . '` ---> ';
    try {
        $elasticClient->indices()->delete(['index' => [$indexName]]);
    } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
        echo 'Index not found. Skipping...' . PHP_EOL;
    } catch (Throwable $e) {
        echo 'Failed' . PHP_EOL;
        echo $e->getMessage();
        exit(1);
    }
    echo 'SUCCESS' . PHP_EOL;


} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}