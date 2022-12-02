<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\MessageQueue;


use CodeLathe\Core\Managers\Realtime\RtmMessage;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\MQ\MQService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class MQProducer
{
    const JSON = 'JSON';
    const PSO = 'PSO';
    public static $SUPPORTED_TYPES = array(MQProducer::JSON, MQProducer::PSO);

    protected $logger;
    protected $mqService;

    public function __construct(LoggerInterface $logger, MQService $mqService)
    {
        $this->logger = $logger;
        $this->mqService = $mqService;
    }

    /**
     * Publish to kafka
     * @param string $topicName The name of topic to publish
     * @param ASEvent $payload The event payload
     * @param string $type The target to transform the data. Data can only be sent
     * as a string. So the data has to be flattened out to string and reconstituted at
     * the other end.
     */
    public function bgProduce(string $topicName, ASEvent $payload, $type=MQProducer::PSO)
    {
        $ser = $this->transform($payload, $type);

        //$sobj = unserialize($ser);

        //$this->logger->info(__FUNCTION__ ." : " . print_r($sobj->getPayloadArray(),true));

        $this->mqService->publish($this->mqService->newTopic($topicName), $this->transform($payload, $type));
    }

    /**
     * Send a websocket message using Kafka publish
     *
     * @param RtmMessage $payload
     */
    public function rtmMQProduce(RtmMessage $payload)
    {
        //$this->logger->info(__FUNCTION__ ." : Publishing to : " . $payload->topic() );

        if (($message = Json::encode($payload->jsonSerialize())) !== false) {
            $this->mqService->publish($this->mqService->newTopic($payload->topic()), $message);
        } else {
            $this->logger->warning("Object could not be json_encoded: " . print_r($payload->jsonSerialize(), true));
        }
    }

    /**
     * Send message to Node util using its Kafka queue
     *
     * @param NodeUtilMessage $payload
     */
    public function utilNodeProduce(NodeUtilMessage $payload)
    {
        //$this->logger->info(__FUNCTION__ ." : Publishing to MQDefs::NODE_UTIL : " . json_encode($payload->jsonSerialize()));

        $this->mqService->publish($this->mqService->newTopic(MQDefs::NODE_UTIL), Json::encode($payload->jsonSerialize()));
    }


    /**
     * @param ASEvent $event
     * @param string $type
     * @return false|string
     */
    protected function transform(ASEvent $event,  $type=MQProducer::PSO)
    {
        if (!in_array($type, MQProducer::$SUPPORTED_TYPES)) {
            throw new Exception('Unsupported transform.');
        }

        if ($type == MQProducer::JSON) {
            return Json::encode($event->jsonSerialize());
        }

        if ($type ==  MQProducer::PSO) {
            return $event->toPSO();
        }
    }
}