<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS\Drivers;

use CodeLathe\Service\SMS\SMSDriverInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * This interface represents the sms delivery sender driver used to send the sms.
 */
class TwilioDriver implements SMSDriverInterface
{

    /**
     * @var Client
     */
    protected $twilioClient;

    /**
     * @var string
     */
    protected $fromNumber;

    /**
     * TwilioDriver constructor.
     * @param array $driverParams
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    public function __construct(array $driverParams)
    {
        $this->twilioClient = new Client($driverParams['sid'], $driverParams['token']);
        $this->fromNumber = $driverParams['from_number'];
    }

    /**
     * @param string $destinationNumber
     * @param string $message
     * @throws TwilioException
     */
    public function send(string $destinationNumber, string $message)
    {
        $this->twilioClient->messages
            ->create($destinationNumber,
                [
                    "from" => $this->fromNumber,
                    "body" => $message,
                ]
            );
    }
}