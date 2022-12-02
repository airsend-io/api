<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Objects\Action;

/**
 * This event is for tracking User login event
 */

class ActionCreatedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'action.create';

    protected $action;

    public function __construct(Action $action)
    {
        $this->action = $action;
    }

    public function action():Action
    {
        return $this->action;
    }

    public function getActionId(): int
    {
        return $this->action->getId();
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName (): string
    {
        return self::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return $this->action->getArray();
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId () : int
    {
        return (int)$this->action->getChannelId();
    }
}