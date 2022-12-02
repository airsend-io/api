<?php

/**
 * Cron entrypoint
 *
 * This script MUST be schedule using the OS schedule tool, to run every minute.
 * It just calls the internal/cron endpoint on the API, that takes care of the execution of the scheduled jobs.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use GuzzleHttp\Client as HttpClient;

$client = new HttpClient([
    'base_uri' => getenv('API_BASE_URI') ?: 'http://web/api/v1/'
]);

echo 'Running CRON jobs at ' . date('Y-m-d H:i:s') . PHP_EOL;

$response = $client->post('internal/cron', [
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode([
        'auth_token' => getenv('APP_INTERNAL_AUTH_TOKEN') ?: "BG_MESSAGE_KEY_@#$%_@HGDDFFDG_@#$#$%@#$@4342234_55"
    ])
]);

//

