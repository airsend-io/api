<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Messaging\BackgroundDispatcher;
use CodeLathe\Service\Database\DatabaseService;
use PDOException;
use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\KafkaConsumer;
use RdKafka\TopicConf;
use Throwable;

class MqConsumerManager
{

    const RETRY = 3;

    public static $eventDebugId = null;

    /**
     * @var float|null
     */
    public static $eventDebugStart = null;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var BackgroundDispatcher
     */
    protected $backgroundDispatcher;

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var DatabaseService
     */
    protected $dbs;

    public function __construct(LoggerInterface $logger, BackgroundDispatcher $backgroundDispatcher, DatabaseService $dbs)
    {
        $this->logger = $logger;
        $this->backgroundDispatcher = $backgroundDispatcher;
        $this->dbs = $dbs;
    }

    public function execute(string $clientId, string $groupId, string $broker, array $topics)
    {
        $this->clientId = $clientId;

        $this->log('Worker starting Up...', 'info');

        $conf = new KafkaConf();
        $conf->set('client.id', $clientId);
        $conf->set('group.id', $groupId);
        $conf->set('metadata.broker.list', $broker);
        $topicConf = new TopicConf();
        $topicConf->set('auto.offset.reset', 'smallest');
        $conf->setDefaultTopicConf($topicConf);

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe($topics);

        $this->log('Starting consumer...', 'info');

        while (true) {

            $message = $consumer->consume(120*1000);
            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    static::$eventDebugId = uniqid('evt_');
                    static::$eventDebugStart = microtime(true);
                    $this->log("Received message: " . $message->payload);
                    $this->dispatchMessage($message->payload);
                    static::$eventDebugId = null;
                    static::$eventDebugStart = null;
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    $this->log("No more messages; will wait for more");
                    break;
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    $this->log("Timed out");
                    break;
                default:
                    $this->log($message->errstr(), 'error');
            }
        }
    }

    protected function dispatchMessage(string $message): void
    {

        try {

            $startTime = microtime(true);
            $this->backgroundDispatcher->processMessage($message);
            $timeElapsed = microtime(true) - $startTime;

            if ($timeElapsed > 500) {
                $timeElapsed = floor($timeElapsed);
                $this->log("Processing took $timeElapsed ms. Message: $message");
            }

        } catch (DatabaseException | PDOException $e) {

            // if we have a database exception, try to reconnect to database, and keep trying to process the message
            $exceptionClass = get_class($e);
            $this->log("Worker database error: {$exceptionClass}: `{$e->getMessage()}`. Restarting the connection...", 'error');

            // renew the connection
            $this->dbs->setConnection();

            usleep(100);

        } catch (Throwable $e) {

            // if we have any other exception...
            // ... we just log it
            $this->log("Worker error: {$e->getMessage()}.", 'error');

        }

    }

    protected function log(string $message, string $level = 'debug')
    {
        $msg = "{$this->clientId} -> $message";

        // log to docker logs
        echo "[$level] $msg" . PHP_EOL;

        // log to app logs
        $this->logger->$level($msg, ['EXT' => 'WORKER']);
    }
}