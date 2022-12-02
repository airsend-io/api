<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Service\EventDispatcher\EventDispatcherService;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * This is the main application event manager. 
 * All event processing is done from here. This depends on the
 * Event dispatcher service
 */
Class EventManager
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    protected $container;

    public function __construct(LoggerInterface $logger, ConfigRegistry $config, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * Publish an event.
     *
     * @param ASEvent $event should contain the event name and any optional payload to publish to
     * event subscriber
     * @param bool $inBackgroundMode This is meant for background dispatcher to publish a background
     * version of the event. Others should not be use this flag.
     * @return void
     */
    public function publishEvent(ASEvent $event, bool $inBackgroundMode=false): void
    {

        /**
         * If in Background mode, then publish background version of the event
         */
        if ($inBackgroundMode || $this->config->get('/app/serialize_events')) {
            $this->publish($event, $event::backgroundEventName());
        } else {
            // Publish this in foreground (The foreground version of the event)
            $this->publish($event, $event::foregroundEventName());

            // Send it to be publish in background
            $bg = $this->container->get(BackgroundDispatcher::class);
            $bg->dispatch($event);
        }
    }

    /**
     * Method to subscribe for an event
     *
     * @param string $eventName
     * @param callable $callback
     */
    public function addListener(string $eventName, callable $callback): void
    {
        $this->container->get(EventDispatcherService::class)->addListener($eventName, $callback);
    }

    /**
     * Publish an event using event dispatcher. All the subscribers will be receiving this
     * event.
     * @param ASEvent $event
     * @param string $eventName
     */
    protected function publish(ASEvent $event, string $eventName): void
    {
        //$this->logger->debug("EVENT ==> $eventName");
        $this->container->get(EventDispatcherService::class)->dispatch($event, $eventName);
    }

}