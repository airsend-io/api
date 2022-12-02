<?php

// test produce on kafka queue

// this file is only used for testing purposes

$conf = new RdKafka\Conf();
//$conf->set('log_level', (string) LOG_DEBUG);
//$conf->set('debug', 'all');
$conf->set('metadata.broker.list', 'kafka');

$producer = new RdKafka\Producer($conf);

$topic = $producer->newTopic("as_parallel_bg_queue");
//$topic->produce(RD_KAFKA_PARTITION_UA, 0, $argv[1] ?? 'default message');
//$producer->poll(0);


    $topic->produce(RD_KAFKA_PARTITION_UA, 0, $argv[1] ?? 'default message');
    $producer->poll(0);


for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
    $result = $producer->flush(10000);
    if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
        break;
    }
}

if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
    throw new \RuntimeException('Was unable to flush, messages might be lost!');
}