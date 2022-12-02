<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Utility\ObjectFactory;


use CodeLathe\Core\Objects\ObjectInterface;

class NormalizedEvent implements \JsonSerializable
{
    protected $array;

    public function __construct (string $eventName, array $payload)
    {
        $this->array = [
            'meta' => [
                'api' => 'v1',
                'emitted_on' => microtime(true),
            ],
            'event' => $eventName, 'payload' => $payload
        ];
    }

    public function setEventName(string $eventName): void
    {
        $this->array['event'] = $eventName;
    }

    public function getArray () : array
    {
        return $this->array;
    }

    public function addObjectPayload (string $key, ObjectInterface $payload) : void
    {
        $this->array['payload'][$key] = $payload;
    }

    public function addArray (string $key, array $payload) : void
    {
        $this->array['payload'][$key] = $payload;
    }

    public function addPOD (string $key, string $payload) : void
    {
        $this->array['payload'][$key] = $payload;
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
        return $this->array;
    }


}