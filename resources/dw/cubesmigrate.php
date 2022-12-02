<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/*
 * Run this script to migrate asstoragedb and corresponding tables
 *
 * Usage:
 * From the local system go to docker api container with the command
 *      docker-compose exec api bash
 * Ensure you are in /var/ww/dev folder
 * Run `composer run dbmigrate` (this will only check for available database upgrades)
 * To execute the upgrade run `composer run dbmigrate exec`
 * For further details, please refer to the readme file located under `src/Core/Data/Migrations/readme.md`
 */

use CodeLathe\Core\Data\Migrations\AbstractMigration;
use CodeLathe\Service\Cache\CacheService;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;
use Psr\Container\ContainerInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use CodeLathe\Core\Data\MigrationController;

/** @var ContainerInterface $container */

if(php_sapi_name() == "cli") {

    // cli output functions
    require_once dirname(__FILE__) . '/output.php';

    function _echo(string $text, int $lineBreaks = 1) {
        global $quiet;

        if ($quiet ?? false) {
            return;
        }

        echo $text;
        for ($i =0; $i < $lineBreaks; $i++) {
            echo PHP_EOL;
        }
    }

    $quiet = in_array('--quiet', $argv);

    // cli arguments / caller pre-defined vars
    $shouldExecute = $shouldExecute ?? in_array('--exec', $argv);

    /** @var ServiceRegistryInterface $config */
    $config = $container->get(ServiceRegistryInterface::class);

    $elasticClient = $container->get(\Elasticsearch\Client::class);

    // find all cubes
    $cubes = [];
    foreach (glob(\CodeLathe\Core\Utility\Directories::src('Core/DW/FactDataStores') . '/*DataStore.php') as $file) {
        if (preg_match('/(([^\/]+)DataStore)\.php$/', $file, $matches)) {
            $class = "\\CodeLathe\\Core\\DW\\FactDataStores\\$matches[1]";
            $dataStore = $container->get($class);
            $cubes[$dataStore->getIndexName()] = $matches[2];
        }
    }
    $indices = array_keys($cubes);

    // check if the cubes_versions index exists...
    if (!$elasticClient->indices()->exists(['index' => \CodeLathe\Core\DW\FactMigrations\AbstractMigration::CUBES_VERSIONS_INDEX_NAME])) {
        _echo('Index cubes_versions doesn\'t exists. Creating...');
        $elasticClient->indices()->create(['index' => \CodeLathe\Core\DW\FactMigrations\AbstractMigration::CUBES_VERSIONS_INDEX_NAME]);
    }

    // get the current versioning for all cubes
    $result = $elasticClient->search([
        'index' => \CodeLathe\Core\DW\FactMigrations\AbstractMigration::CUBES_VERSIONS_INDEX_NAME,
        'body'  => [
            'query' => [
                'match_all' => new \stdClass()
            ],
        ]
    ]);
    $currentVersions = [];
    foreach ($result['hits']['hits'] as $hit) {
        $currentVersions[$hit['_id']] = $hit['_source']['version'];
    }

    // go through all the indices, migrating when necessary
    foreach ($indices as $index) {

        // find the current version of the index
        $currentVersion = $currentVersions[$index] ?? 0;

        // find the actual version of the index
        $actualVersion = $config->get("/dw/$index/version");

        if ($actualVersion === $currentVersion) {
            _echo("Cube $index: Up to date...");
            continue;
        }

        _echo("Cube $index: Current version: $currentVersion. Must be updated to version $actualVersion...");

        if (!$shouldExecute) {
            continue;
        }

        for ($version = $currentVersion+1; $version <= $actualVersion; $version++) {

            // find the migration handler for this version
            $migrationHandler = $container->get("\\CodeLathe\\Core\DW\\FactMigrations\\$cubes[$index]\\Version$version");

            if ($migrationHandler instanceof \CodeLathe\Core\DW\FactMigrations\AbstractMigration) {

                $message = "Running $index [version $version]: {$migrationHandler->getDescription()}";
                output($migrationHandler->execute($index), $message);
                continue;
            }

            _echo('Error: Invalid migration handler...');
        }
    }

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
