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

    function _echo($text) {
        global $quiet;

        if ($quiet ?? false) {
            return;
        }

        echo $text;
    }

    $quiet = in_array('--quiet', $argv);

    // cli arguments / caller pre-defined vars
    $shouldExecute = $shouldExecute ?? in_array('--exec', $argv);
    $onlyCloudDb = $onlyCloudDb ?? in_array('--cloud', $argv);
    $onlyStorageDb = $onlyStorageDb ?? in_array('--storage', $argv);

    /** @var ServiceRegistryInterface $config */
    $config = $container->get(ServiceRegistryInterface::class);

    /** @var CacheService $cache */
    $cache = $container->get(CacheService::class);

    // current versions
    /** @var DatabaseService $dbs */
    $dbs = $container->get(DatabaseService::class);
    $sql = 'SELECT * FROM versions ORDER BY created_on DESC, id DESC';
    $result = $dbs->selectOne($sql);
    $currentDBVersion = $result['id'];
    $currentDBVersionMessage = $result['notes'];

    // latest versions
    $latestDBVersion = $config->get('/db/core/version');
    $latestFSVersion = $config->get('/db/fs/version');

    _echo(PHP_EOL . PHP_EOL);
    if (!$onlyStorageDb) {
        _echo('=======================' . PHP_EOL);
        _echo("Current DB Version: {$currentDBVersion}" . PHP_EOL);
        _echo("Current DB Version Message: {$currentDBVersionMessage}" . PHP_EOL);
        _echo("Latest DB Version: {$latestDBVersion}" . PHP_EOL);

        /** @var AbstractMigration[] $migrations */
        $migrations = [];
        if ($latestDBVersion > $currentDBVersion) {
            _echo("DB upgrade available:" . PHP_EOL);
            foreach (range($currentDBVersion + 1, $latestDBVersion) as $i) {
                $class = "\CodeLathe\Core\Data\Migrations\DBVersion$i";

                /** @var AbstractMigration $migration */
                $migration = $container->get($class);
                _echo("$i - {$migration->getDescription()}" . PHP_EOL);

                $migrations[$i] = $migration;
            }
        } else {
            _echo("Cloud Database is up to date!" . PHP_EOL);
        }
        _echo('=======================' . PHP_EOL);
    }

    if (!$onlyCloudDb) {
        /** @var FSDatabaseService $fsdbs */
        $fsdbs = $container->get(FSDatabaseService::class);
        $result = $fsdbs->selectOne($sql);
        $currentFSVersion = $result['id'];
        $currentFSVersionMessage = $result['notes'];
        _echo('=======================' . PHP_EOL);
        _echo("Current Storage DB Version: {$currentFSVersion}" . PHP_EOL);
        _echo("Current Storage DB Version Message: {$currentFSVersionMessage}" . PHP_EOL);
        _echo("Latest Storage DB Version: {$latestFSVersion}" . PHP_EOL);

        /** @var AbstractMigration[] $fsMigrations */
        $fsMigrations = [];
        if ($latestFSVersion > $currentFSVersion) {
            _echo("Storage DB upgrade available:" . PHP_EOL);
            foreach (range($currentFSVersion + 1, $latestFSVersion) as $i) {
                $class = "\CodeLathe\Core\Data\Migrations\FSDBVersion$i";

                /** @var AbstractMigration $migration */
                $migration = $container->get($class);
                _echo("$i - {$migration->getDescription()}" . PHP_EOL);

                $fsMigrations[$i] = $migration;
            }
        } else {
            _echo("Storage Database is up to date!" . PHP_EOL);
        }
        _echo('=======================' . PHP_EOL);
    }

    if (!$shouldExecute) {
        _echo('By default this script only check for available migrations. To execute the upgrade, run with `exec` param.' . PHP_EOL);
        exit(0);
    }

    $flushExcludePatterns = [
        'jwt.*',
        'refresh.token.*',
        'airsend_ws_stats',
    ];

    $count = count($migrations ?? []);
    if ($count && !$onlyStorageDb) {
        _echo("Executing $count Database migration(s):" . PHP_EOL);
        output($cache->softFlush($flushExcludePatterns), 'Flushing cache');
        foreach ($migrations as $key => $migration) {
            output($migration->execute(), "$key - {$migration->getDescription()}");
        }
    }

    $count = count($fsMigrations ?? []);
    if ($count && !$onlyCloudDb) {
        _echo("Executing $count Storage Database migration(s):" . PHP_EOL);
        output($cache->softFlush($flushExcludePatterns), 'Flushing cache');
        foreach ($fsMigrations as $key => $migration) {
            output($migration->execute(), "$key - {$migration->getDescription()}");
        }
    }

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
