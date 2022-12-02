<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Realtime;


use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\RtmInterface;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedEvent;

class RtmMessage implements \JsonSerializable
{

    protected $payload;

    public function __construct (NormalizedEvent $event, RtmToken $rtmToken)
    {
        $this->payload['rtm_payload'] = $event;
        $this->payload['token'] = $rtmToken;
    }

    /**
     * Associated kafka topic to publish to reach this client
     * @return stringA
     */
    public function topic() : string
    {
        return $this->payload['token']->topic();
    }
    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize ()
    {
        return $this->payload;
    }
}