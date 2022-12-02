<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging;

use CodeLathe\Core\CronTasks\ExternalNotificationTask;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidPayoadException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\MessageQueue\MQDefs;
use CodeLathe\Core\Messaging\MessageQueue\MQProducer;
use CodeLathe\Core\Messaging\Notification\NotificationFilter;
use CodeLathe\Core\Messaging\Notification\NodeCommandHandler;
use CodeLathe\Core\Serializers\JSONSerializer;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Core\Validators\InputValidator;
use CodeLathe\Service\Cron\CronService;
use CodeLathe\Service\Cron\Exceptions\InvalidCronTask;
use CodeLathe\Service\Cron\Exceptions\InvalidSchedule;
use CodeLathe\Service\Logger\LoggerService;
use Exception;
use Firebase\JWT\JWT;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use RdKafka\ProducerTopic;
use Slim\Psr7\Request as Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BackgroundDispatcher
{
    const SERIAL = "SERIAL";
    const PARALLEL = "PARALLEL";

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var MQProducer
     */
    protected $producer;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var NodeCommandHandler
     */
    protected $nodeCommandHandler;

    /**
     * BackgroundDispatcher constructor.
     * @param MQProducer $producer
     * @param LoggerInterface $logger
     * @param EventManager $eventManager
     * @param NodeCommandHandler $nodeCommandHandler
     */
    public function __construct(MQProducer $producer,
                                LoggerInterface $logger,
                                EventManager $eventManager,
                                NodeCommandHandler $nodeCommandHandler)
    {
        $this->producer = $producer;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nodeCommandHandler = $nodeCommandHandler;
    }


    /**
     * Dispatch event to be executed in background context
     *
     * @param ASEvent $payload
     * @param bool $highPriority
     */
    public function dispatch(ASEvent $payload, bool $highPriority=true): void
    {
        $topicName = MQDefs::PARALLEL_BACKGROUND;
        if (!$highPriority) {
            $topicName = MQDefs::PARALLEL_BACKGROUND_LOW_PRIORITY;
        }
        $this->producer->bgProduce($topicName, $payload);
    }

    /**
     * Entry point for processing background request
     *
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws InvalidArgumentException
     */
    public function bgprocess(Request $request, Response $response)
    {
        $params = $request->getParsedBody();

        if (!Utility::internalAuthenticate($params, $this->logger)) {
            return JsonOutput::error('Unauthorized', 401)->write($response);
        }
        //$this->logger->debug($params);
        $this->processMessage($params['message']);

        // Always return success to the kafka worker
        return JsonOutput::success()->write($response);
    }

    /**
     * @param String $message
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidPayoadException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function processMessage(String $message)
    {
        $JsonMsgId = 'JSONRTMOBJ#';

        if (StringUtility::startsWith($message, $JsonMsgId)) {
            $payloadMsg = substr($message, strlen($JsonMsgId));
            $this->handleNodeMessage($payloadMsg);
        } else {
            // Deserialize the payload and call the event notifier
            // Background messages will always go through event notifier
            $this->handleBackgroundingMessage(unserialize($message));
        }

    }

    /**
     *
     * Process a message from Node services
     *
     * @param string $payloadMsg
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidPayoadException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    private function handleNodeMessage(string $payloadMsg): void
    {
        // Message from node server
        $jsonMsg = json_decode($payloadMsg, true);
        if (!$jsonMsg) {
            $this->logger->error("Unknown JSON payload received for background dispatch");
            throw new InvalidPayoadException("Unknown JSON payload received for background dispatch!");
        }
        //$this->logger->debug("Backgrounding request received from " . $jsonMsg['source'] . "[". $jsonMsg['host'] . "] : " . $jsonMsg['command']);

        // Check which node server did this message comme from and route it
        if ($jsonMsg['source'] === 'websocket_server') {
            $this->nodeCommandHandler->handleIncomingRTMMessage($jsonMsg);
        } else if ($jsonMsg['source'] === 'node_util') {
            $this->nodeCommandHandler->handleIncomingNodeUtilMessage($jsonMsg);
        }
    }

    /**
     *
     * Process a serialized PHP object message sent to background
     *
     * @param $eventPayload
     * @throws InvalidPayoadException
     */
    private function handleBackgroundingMessage($eventPayload): void
    {

        //$this->logger->info(get_class($eventPayload));
        if (!is_subclass_of($eventPayload, ASEvent::class)) {
            $this->logger->error("Unknown payload received for background dispatch");
            throw new InvalidPayoadException("Unknown payload received for background dispatch!");
        }

        // Publish this in foreground since we are already in non browser mode (i.e background)
        $this->eventManager->publishEvent($eventPayload, true);
    }

}

