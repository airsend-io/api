<?php  declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Utility\LoggerFacade;

class ActionUpdatedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'action.update';

    protected $initialAction;

    protected $updatedAction;


    public function __construct(Action $initialAction, Action $updatedAction)
    {
        $this->initialAction = $initialAction;
        $this->updatedAction = $updatedAction;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName (): string
    {
        return self::NAME;
    }

    public function getActionId(): int
    {
        return $this->updatedAction->getId();
    }

    public function getOldChannelId(): int
    {
        return $this->initialAction->getChannelId();
    }

    public function getOldAction(): Action
    {
        return $this->initialAction;
    }

    public function getNewAction(): Action
    {
        return $this->updatedAction;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return $this->updatedAction->getArray();
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId () : int
    {
        return $this->updatedAction->getChannelId();
    }
}