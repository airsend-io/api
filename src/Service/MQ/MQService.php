<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\MQ;

use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Log\LoggerInterface;
use RdKafka\ProducerTopic;

/**
 * Describes a Service instance.
 */
class MQService implements ServiceInterface
{
    protected $rkafka;
    protected $loggerService;

    public function __construct(ServiceRegistryInterface $registry, LoggerInterface $loggerService)
    {

        $avtr = getenv('APP_AVATAR');

        $this->loggerService = $loggerService;

        $conf = new \RdKafka\Conf();
        $conf->set('socket.timeout.ms', '15'); // or socket.blocking.max.ms, depending on librdkafka version
        if (isset($avtr) && $avtr == 'worker') {
            $conf->set('queue.buffering.max.ms', '10');
            $conf->set('socket.blocking.max.ms', '15');
            $conf->set('batch.num.messages', '1');
            $conf->set('socket.nagle.disable', 'true');
            $conf->set('client.id', 'as-php-worker');

        }
        else {
            $conf->set('queue.buffering.max.ms', '5');
            $conf->set('socket.blocking.max.ms', '5');
            $conf->set('batch.num.messages', '1');
            $conf->set('socket.nagle.disable', 'true');
            $conf->set('client.id', 'as-php-api');
        }

        //$conf->setLogLevel(LOG_DEBUG);
        $this->rkafka = new \RdKafka\Producer($conf);
        $this->rkafka->addBrokers($registry->get('/mq/host'));
    }

    public function __destruct ()
    {
        // Flush queue

    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return MQService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "MQ Service provides a thin wrapper around Kafka";
    }

    public function publish(ProducerTopic $topicHandle, string $message)
    {
        $topicHandle->produce(RD_KAFKA_PARTITION_UA, 0, $message);
        // ... TODO: error out if the Q doesn't drain
        while($this->rkafka->getOutQLen() > 0) {
            $this->rkafka->poll(1);
        }
    }


    public function newTopic(string $topic)
    {
        return $this->rkafka->newTopic($topic);
    }
}