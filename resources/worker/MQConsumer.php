<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use Firebase\JWT\JWT;
use Katzgrau\KLogger\Logger;

require 'Logger.php';

$logFormat = "{date} {level}: {message}";
$logger = new Logger('/var/www/dev/scratch/logs/', Katzgrau\KLogger\LogLevel::DEBUG,
    array
    (
        'extension' => 'log',
        'prefix' => 'kw-',
        'appendContext' => false,
        'dateFormat' => "Y-m-d G:i:s.u",
        'logFormat' => $logFormat

    ));

$logger->info("MQ Consumer Starting Up...");

// Load the configuration
$conf = new RdKafka\Conf();

$WORKER_HOST = getenv('AIRSEND_DEPLOYMENT_HOSTNAME');

if ($WORKER_HOST == 'AWS_VPS') {
    $WORKER_HOST = system('curl http://169.254.169.254/latest/meta-data/local-ipv4');
}
if (empty($WORKER_HOST)) {
    $WORKER_HOST = "localhost";
}
$WORKER_HOST = str_replace('.','_',$WORKER_HOST);
$clientId = 'php_kafka_worker_'.$WORKER_HOST;
$logger->info("Client ID=$clientId");
echo "Client ID = $clientId\n";
$conf->set('client.id',$clientId);

$brokers = getenv("AIRSEND_KAFKA_HOST");
if (empty($brokers)) {
    $brokers = 'kafka';
}

$logger->info("CONNECTING TO KAFKA: $brokers");
echo "CONNECTING TO KAFKA: $brokers\n";

// Configure the group.id. All consumer with the same group.id will consume
// different partitions.
// We will control the number of consumers and partition via invocation
//scripts
// The default group id will be

$args = getopt("", ["groupid:", "topic:"]);
//print_r($args);
if (empty($args['groupid']))
{
    echo "ERROR: Group id is required using --groupid=name_of_group.\n";
    die();
}

$conf->set('group.id', $args['groupid']);


// Initial list of Kafka brokers. The name "kafka" will
// point to the

$conf->set('metadata.broker.list', $brokers);

// Topic to listen on
$topicConf = new RdKafka\TopicConf();

// Set where to start consuming messages when there is no initial offset in
// offset store or the desired offset is out of range.
// 'smallest': start from the beginning
$topicConf->set('auto.offset.reset', 'smallest');

// Set the configuration to use for subscribed/assigned topics
$conf->setDefaultTopicConf($topicConf);

$consumer = new RdKafka\KafkaConsumer($conf);


// Subscribe to topic passed in
if (empty($args['topic']))
{
    echo "ERROR: Topic to consume is required using --topic=name_of_topic.\n";
    die();
}

// At this point we are ready to subscribe.
// We will be automatically loadbalanced on multiple partition system
$consumer->subscribe([$args['topic']]);

echo "Waiting for partition assignment... (may take some time when\n";
echo "quickly re-joining the group after leaving it.)\n";

while (true) {
    $message = $consumer->consume(120*1000);
    switch ($message->err) {
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            processMessage($message);
            break;
        case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            //echo "No more messages; will wait for more\n";
            break;
        case RD_KAFKA_RESP_ERR__TIMED_OUT:
            //echo "Timed out\n";
            break;
        default:
            throw new \Exception($message->errstr(), $message->err);
            break;
    }
}


function processMessage(RdKafka\Message $message) {
    $url = "http://web/api/v1/internal/bgprocess";

    global $logger;
    $key = getenv('APP_INTERNAL_AUTH_TOKEN');
    if (empty($key)) {
        $key = "BG_MESSAGE_KEY_@#$%_@HGDDFFDG_@#$#$%@#$@4342234_55";
    }

    //echo "Using Key=$key\n";
    $params = "topic_name=".$message->topic_name."&message=".urlencode($message->payload) . "&auth_token=".$key;

    //echo  "$url?$params\n";
    $logger->debug("$url?$params");
    $options = array(
        CURLOPT_RETURNTRANSFER => true, // return web page
        CURLOPT_HEADER => false, // don't return headers
        CURLOPT_FOLLOWLOCATION => false, // follow redirects
        CURLOPT_ENCODING => "", // handle all encodings
        CURLOPT_USERAGENT => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36", // who am i
        CURLOPT_AUTOREFERER => true, // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
        CURLOPT_TIMEOUT => 0, // No timeout
        CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
        CURLOPT_SSL_VERIFYPEER => 0, // Disabled SSL Cert checks
        CURLOPT_CAINFO => "",
        CURLOPT_SSL_VERIFYHOST => 0
    );
    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

    //echo "MQ: $g_workerId [$g_workerType] Calling  $url$params".PHP_EOL;

    $response = curl_exec($ch);
    $err = curl_errno($ch);
    $errmsg = curl_error($ch);
    curl_close($ch);
    if ($err != 0) {
        echo ": errno $err: Failed with $errmsg sending to $url?$params".PHP_EOL;

        $logger->error("errno $err: Failed with $errmsg sending to $url?$params");
    }
    else {
        //echo " Response $response" .PHP_EOL;
        // Dont really care about the response
    }
}