<?php
/**
 * Solr init script
 *
 * This script init the solr core used for search.
 * If it's run with an existing core, it will be deleted, and a fresh core will be created, following the definitions
 * on the skel directory.
 */

require_once __DIR__ . '/script_helpers/solrsetup.php';

echo "Initializing SOLR core `$coreName` on `$solrHost:$solrPort``" . PHP_EOL;

// create a core admin query to deal with admin actions of the core
$coreAdminQuery = $client->createCoreAdmin();

echo "Checking if the core `$coreName` already exists..." . PHP_EOL;
$statusAction = $coreAdminQuery->createStatus();
$statusAction->setCore($coreName);
$coreAdminQuery->setAction($statusAction);
$response = $client->coreAdmin($coreAdminQuery);
$statusResult = $response->getStatusResult();

if ($statusResult->getStartTime() !== null) {

    echo "Core `$coreName` exists! Unloading it..." . PHP_EOL;

    $unloadAction = $coreAdminQuery->createUnload();
    $unloadAction->setCore($coreName);
    $coreAdminQuery->setAction($unloadAction);
    $client->coreAdmin($coreAdminQuery);

} else {
    echo "No core found for `$coreName`." . PHP_EOL;
}

echo "Coping/replacing the base schema from skel..." . PHP_EOL;
$rmCommand = 'rm -rf ' . __DIR__ . '/instance/*';
shell_exec($rmCommand);
$cpCommand = 'cp -rf ' . __DIR__ . '/skel/* ' . __DIR__ . '/instance';
shell_exec($cpCommand);

echo "Creating the core `$coreName`" . PHP_EOL;
$createAction = $coreAdminQuery->createCreate();
$createAction->setCore($coreName);
$createAction->setInstanceDir($coreName);
$createAction->setDataDir('data');
$createAction->setConfig('solrconfig.xml');
$createAction->setSchema('schema.xml');
$coreAdminQuery->setAction($createAction);
$client->coreAdmin($coreAdminQuery);

echo "Core `$coreName` create successfully" . PHP_EOL;





