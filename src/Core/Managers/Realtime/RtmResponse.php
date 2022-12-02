<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Realtime;

/**
 * Class RtmResponse
 *
 * This class is for returning response to rtm.connect HTTP call.
 * The objective is to send information to connect to websockets
 *
 * @package CodeLathe\Core\Managers\Realtime
 */
class RtmResponse implements \JsonSerializable
{

    /**
     * @var array
     */
    protected $payloadArray;

    /**
     * RtmResponse constructor.
     * @param string $wsEndPoint
     * @param string $token
     */
    public function __construct (string $wsEndPoint, string $token)
    {
        $this->payloadArray = array();
        $this->payloadArray['ws_endpoint'] = $wsEndPoint;
        $this->payloadArray['ws_token'] = $token;
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
        return $this->payloadArray;
    }
}