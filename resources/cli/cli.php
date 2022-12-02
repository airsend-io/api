<?php declare(strict_types=1);
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Service\Logger\LoggerService;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;

if(php_sapi_name() != "cli") {
    exit(1);
}

function usage()
{
    echo "USAGE: AirSend CLI "." -r path -c 'configoptions'\n";
    echo "Example: cli.php -r /path/file.php -c 'key^val~key^val'\n\n";
}

if (in_array('--tests', $argv)) {
    putenv('APP_ENV=tests');
}

$options = getopt("r:c:");
if (!isset($options["r"]))
{
    echo "Error: Missing Optional Script to Require\n\n";
    usage();
    exit(1);
}

$configArray = array();
if (isset($options["c"]))
{
    $optionsList = explode('~', $options['c']);
    foreach ($optionsList as $option)
    {
        $config = explode('^', $option);
        $configArray[$config[0]] = $config[1];
    }
}

// ... Create our canonical directory path to the document root
define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

require CL_AS_ROOT_DIR . '/vendor/autoload.php';

// ... Init Container
$configRegistry = new ConfigRegistry();
foreach($configArray as $key => $value)
{
    $configRegistry->set($key,$value);
}

$containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
$container = $containerIniter($configRegistry);

\CodeLathe\Core\Utility\ContainerFacade::setUp($container);

if (!in_array('--hide-script-info', $argv)) {
    echo "AirSend CLI: " . $container->get(ConfigRegistry::class)->get('/app/version') . "\n";
    echo "Options: " . print_r($options, true) . "\n";
    echo "Config Options: " . print_r($configArray, true) . "\n";
}

$container->get(LoggerInterface::class)->debug("AirSend CLI: v".$container->get(ConfigRegistry::class)->get('/app/version'));
$container->get(LoggerInterface::class)->debug( "Options: ".print_r($options, true));

echo "Running: ".$options["r"] . PHP_EOL;
require $options["r"];


