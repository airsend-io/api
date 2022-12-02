<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging;

use CodeLathe\Core\Managers\Files\FileEventHandler;
use CodeLathe\Core\Managers\Password\PasswordManager;
use CodeLathe\Core\Managers\Channel\ChannelEventHandler;
use CodeLathe\Core\Managers\Request\RequestEventHandler;
use CodeLathe\Core\Managers\User\UserEventHandler;
use CodeLathe\Core\Messaging\Notification\NotificationManager;
use CodeLathe\Service\EventDispatcher\EventDispatcherService;

class EventCallbacks
{

    /**
     * @var EventDispatcherService
     */
    protected $eventDispatcher;

    /**
     * @var NotificationManager
     */
    protected $notificationManager;

    /**
     * @var PasswordManager
     */
    protected $passwordManager;

    /**
     * @var FileEventHandler
     */
    protected $fileEventHandler;

    /**
     * @var UserEventHandler
     */
    protected $userEventHandler;

    /**
     * @var ChannelEventHandler
     */
    protected $channelEventHandler;

    /**
     * @var RequestEventHandler
     */
    protected $requestEventHandler;

    /**
     * EventCallbacks constructor.
     * @param EventDispatcherService $eventDispatcher
     * @param NotificationManager $notificationManager
     * @param PasswordManager $passwordManager
     * @param FileEventHandler $fileEventHandler
     * @param UserEventHandler $userEventHandler
     * @param ChannelEventHandler $channelEventHandler
     * @param RequestEventHandler $requestEventHandler
     */
    public function __construct(EventDispatcherService $eventDispatcher,
                                NotificationManager $notificationManager,
                                PasswordManager $passwordManager,
                                FileEventHandler $fileEventHandler,
                                UserEventHandler $userEventHandler,
                                ChannelEventHandler $channelEventHandler,
                                RequestEventHandler $requestEventHandler)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->notificationManager = $notificationManager;
        $this->passwordManager = $passwordManager;
        $this->fileEventHandler = $fileEventHandler;
        $this->userEventHandler = $userEventHandler;
        $this->channelEventHandler = $channelEventHandler;
        $this->requestEventHandler = $requestEventHandler;
    }

    /**
     * Register all subscribers
     */
    public function registerSubscribers()
    {
        //... Register all subscribers here with the event notifier

        // Notification Manager
        $this->eventDispatcher->addSubscriber($this->notificationManager);

        $this->eventDispatcher->addSubscriber($this->passwordManager);

        $this->eventDispatcher->addSubscriber($this->fileEventHandler);

        $this->eventDispatcher->addSubscriber($this->userEventHandler);

        $this->eventDispatcher->addSubscriber($this->channelEventHandler);

        $this->eventDispatcher->addSubscriber($this->requestEventHandler);

    }
}