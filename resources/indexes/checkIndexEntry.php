<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


use Carbon\Carbon;
use CodeLathe\Core\Indexers\MessageIndexer;
use CodeLathe\Service\Database\DatabaseService;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Psr\Container\ContainerInterface;

const CHUNK_SIZE = 10;

/** @var ContainerInterface $container */

if(php_sapi_name() == "cli") {

    echo PHP_EOL;

    /** @var Client $elasticClient */
    $elasticClient = $container->get(Client::class);

    // parse options
    $index = null;
    $id = null;
    foreach ($argv as $arg) {
        if (preg_match('/^--index=(.*)$/', $arg, $matches)) {
            $index = $matches[1];
        }

        if (preg_match('/^--id=(.*)$/', $arg, $matches)) {
            $id = $matches[1];
        }
    }
    if (!preg_match('/_index$/', $index)) {
        $index .= '_index';
    }

    if (empty($index) || empty($id)) {
        echo "Index and id options are required." . PHP_EOL;
        echo "Usage" . PHP_EOL;
        echo "composer run index_entry -- --index=<index-name> --id=<entry-id>" . PHP_EOL;
        exit(1);
    }

    try {
        $result = $elasticClient->get(['index' => $index, 'id' => $id]);
    } catch (Missing404Exception $e) {
        $message = json_decode($e->getMessage(), true);
        $message = $message['error']['reason'] ?? "Entry not found on index!";
        echo $message . PHP_EOL;
        exit(2);
    }

    print_r($result);

    exit(0);

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
