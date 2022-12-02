<?php declare(strict_types=1);
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use CodeLathe\Core\Managers\MqConsumerManager;
use CodeLathe\Core\Messaging\EventCallbacks;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\LoggerFacade;

// ... Create our canonical directory path to the document root
define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/..'));

require CL_AS_ROOT_DIR . '/vendor/autoload.php';

// ... Setup Temp Error Handlers before Slim Error Handler Takes over
require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'TmpErrorHandlers.php';

// ... Init Container
$containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
$container = $containerIniter();

ContainerFacade::setUp($container);
LoggerFacade::setUp($container);
I18n::setUp($container);

// ... Install Subscribers
$container->get(EventCallbacks::class)->registerSubscribers();

// setup the consumer parameters
$args = getopt('w:g:t:p:', ['workerid:', 'groupid:', 'topic:', 'priority:']);

// if no worker id is provided, generate a uuid for it
$workerId = $args['w'] ?? $args['workerid'] ?? uniqid();

$priority = $args['p'] ?? $args['priority'] ?? 'high';
$clientId = "kw_{$priority}_{$workerId}";
$groupId = $args['g'] ?? $args['groupid'] ?? 'parallelbackgrounders';
$broker = getenv("AIRSEND_KAFKA_HOST") ?: 'kafka';
$topic = $args['t'] ?? $args['topic'] ?? 'as_parallel_bg_queue';

/** @var MqConsumerManager $mqManager */
$mqManager = $container->get(MqConsumerManager::class);

echo "Starting $clientId ..." . PHP_EOL;
$mqManager->execute($clientId, $groupId, $broker, [$topic]);