<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS;


use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\SMS\Drivers\DummyDriver;
use CodeLathe\Service\SMS\Drivers\SignalWireDriver;
use CodeLathe\Service\SMS\Drivers\TwilioDriver;
use CodeLathe\Service\SMS\Exceptions\InvalidSMSSenderDriverException;
use CodeLathe\Service\SMS\Exceptions\InvalidSMSSenderDriverParamsException;

/**
 * Describes a Service instance.
 */
class SMSService implements ServiceInterface, SMSServiceInterface
{

    /**
     * @var SMSDriverInterface
     */
    protected $smsSender;

    /**
     * SMSService constructor.
     * @param string $senderDriver
     * @param array $senderParams
     * @throws InvalidSMSSenderDriverException
     * @throws InvalidSMSSenderDriverParamsException
     */
    public function __construct(string $senderDriver, array $senderParams)
    {
        $drivers = $this->getDrivers();
        if (!isset($drivers[$senderDriver])) {
            throw new InvalidSMSSenderDriverException($senderDriver);
        }

        try {
            $driver = $drivers[$senderDriver]($senderParams);
        } catch (\Throwable $e) {
            throw new InvalidSMSSenderDriverParamsException($e);
        }

        $this->smsSender = $driver;

    }

    protected function getDrivers()
    {
        return [
            'twilio' => function (array $params): SMSDriverInterface {
                return new TwilioDriver($params);
            },
            'signalwire' => function (array $params): SMSDriverInterface {
                return new SignalWireDriver($params);
            },
            'dummy' => function(): SMSDriverInterface {
                return new DummyDriver();
            }
        ];
    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return SMSService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "SMS Service provides a thin wrapper around sending SMS";
    }

    public function send(string $destinationNumber, string $message)
    {
        $this->smsSender->send($destinationNumber, $message);
    }

}