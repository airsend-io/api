<?php

/**
 * Set's up Solr based on the env variables
 */

// load the autoloader
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// prevent direct execution of this file.
if (preg_match('/' . preg_quote($_SERVER['SCRIPT_FILENAME'], '/') .'$/', __FILE__)) {
    echo "Don't run this script directly." . PHP_EOL;
    exit(1);
}

$solrHost = getenv('SOLR_HOST') ?: 'solr';
$solrPort = getenv('SOLR_PORT') ?: 8983;
$coreName = getenv('SOLR_SEARCH_CORE') ?: 'airsendsearch';

$config = [
    'endpoint' => [
        'localhost' => [
            'host' => $solrHost,
            'port' => $solrPort,
            'path' => '/',
            'core' => $coreName,
        ]
    ]
];

// instantiate solarium client
$client = new Solarium\Client($config);