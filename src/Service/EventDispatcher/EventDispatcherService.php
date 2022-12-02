<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\EventDispatcher;

use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Describes a Service instance.
 */
class EventDispatcherService implements ServiceInterface
{
    protected $dispatcher;

    public function __construct(ServiceRegistryInterface $registry)
    {
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return EventDispatcherService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "Dispatcher Service provides a thin wrapper around Symphony Event Dispatching";
    }


    /**
     * @param string $eventName
     * @param callable $listener
     * @param int $priority
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0)
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * Provide all relevant listeners with an event to process.
     *
     * @param object $event
     *   The object to process.
     *
     * @param string $eventName
     * @return void The Event that was passed, now modified by listeners.
     *   The Event that was passed, now modified by listeners.
     */
    public function dispatch($event, string $eventName)
    {
        $this->dispatcher->dispatch($event, $eventName);
    }

    /**
     * @param EventSubscriberInterface $subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);
    }
}