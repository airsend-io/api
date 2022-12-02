<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Messaging\MessageQueue;



class NodeUtilMessage implements \JsonSerializable
{

    protected $payload;

    public function __construct (string $command, array $payload)
    {
        $this->payload['command'] = $command;
        $this->payload['payload'] = $payload;
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