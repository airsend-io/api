<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Request;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\DW\DimensionParsers\DateDimensionParser;
use CodeLathe\Core\DW\DimensionParsers\DeviceDimensionParser;
use CodeLathe\Core\DW\DimensionParsers\EndpointDimensionParser;
use CodeLathe\Core\DW\DimensionParsers\LanguageDimensionParser;
use CodeLathe\Core\DW\DimensionParsers\LocationDimensionParser;
use CodeLathe\Core\DW\DimensionParsers\TimeDimensionParser;
use CodeLathe\Core\DW\DimensionParsers\UserDimensionParser;
use CodeLathe\Core\DW\FactDataStores\RequestsFactDataStore;
use CodeLathe\Core\Messaging\Events\RequestEvent;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RequestEventHandler implements EventSubscriberInterface
{

    protected $logger;

    protected $config;

    public function __construct(LoggerInterface $logger, ConfigRegistry $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public static function getSubscribedEvents()
    {
        return [
            RequestEvent::backgroundEventName() => 'onRequest',
        ];
    }

    public function onRequest(RequestEvent $event)
    {

        // parse endpoint dimension info
        $parser = new EndpointDimensionParser($event->getUriPath());
        $endpointData = $parser->parse();

        // parse location dimension info
        $parser = new LocationDimensionParser($event->getRemoteIp());
        $locationData = $parser->parse();

        // parse date dimension info
        $parser = new DateDimensionParser($event->getDate(), $locationData['hemisphere'] ?? null);
        $dateData = $parser->parse();

        // parse the time dimension info
        $parser = new TimeDimensionParser($event->getDate(), $locationData['timezone'] ?? null);
        $timeData = $parser->parse();

        // parse the device dimension info
        $parser = new DeviceDimensionParser($event->getRequestHeaders());
        $deviceData = $parser->parse();

        // parse the user dimension info
        $parser = new UserDimensionParser($event->getLoggedUserId());
        $userData = $parser->parse();

        // parse the language dimension
        $parser = new LanguageDimensionParser($event->getRequestHeaders());
        $languageData = $parser->parse();

        // grab the current data version
        $version = $this->config->get('/dw/requests_cube/version');

        // handle the request fact
        /** @var RequestsFactDataStore $handler */
        $handler = ContainerFacade::get(RequestsFactDataStore::class);
        $handler->insert($endpointData, $locationData, $dateData, $timeData, $deviceData, $userData, $languageData, $event->getTimeTaken(), $event->getDate(), $version);
    }
}