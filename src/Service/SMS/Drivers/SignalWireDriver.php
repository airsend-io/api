<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS\Drivers;

use CodeLathe\Service\SMS\SMSDriverInterface;
use SignalWire\Rest\Client;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;

/**
 * This interface represents the sms delivery sender driver used to send the sms.
 */
class SignalWireDriver implements SMSDriverInterface
{

    /**
     * @var Client
     */
    protected $signalWireClient;

    /**
     * @var string
     */
    protected $fromNumber;

    /**
     * TwilioDriver constructor.
     * @param array $driverParams
     * @throws ConfigurationException
     */
    public function __construct(array $driverParams)
    {
        $this->signalWireClient = new Client($driverParams['project'], $driverParams['token'], ['signalwireSpaceUrl' => $driverParams['spaceurl']]);
        $this->fromNumber = $driverParams['from_number'];
    }

    /**
     * @param string $destinationNumber
     * @param string $message
     * @throws TwilioException
     */
    public function send(string $destinationNumber, string $message)
    {
        $this->signalWireClient->messages
            ->create($destinationNumber,
                [
                    "from" => $this->fromNumber,
                    "body" => $message,
                ]
            );
    }
}