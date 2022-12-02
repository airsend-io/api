<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Core\Objects\Action;

/**
 * This event is for tracking User login event
 */

class ActionMovedEvent extends  ASEvent implements RtmInterface
{
    public const NAME = 'action.move';

    /**
     * @var Action
     */
    protected $action;

    /**
     * @var int|null
     */
    protected $after;

    /**
     * @var int|null
     */
    protected $parent;

    /**
     * @var int|null
     */
    protected $previousParent;

    /**
     * @var int
     */
    protected $movedBy;

    public function __construct(Action $action, int $movedBy, ?int $after, ?int $newParent = null, ?int $oldParent = null)
    {
        $this->action = $action;
        $this->movedBy = $movedBy;
        $this->after = $after;
        $this->parent = $newParent;
        $this->previousParent = $oldParent;
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
        return [
            'channel_id' => $this->action->getChannelId(),
            'action_id' => $this->action->getId(),
            'after' => $this->after,
            'parent' => $this->parent,
            'previous_parent' => $this->previousParent,
        ];
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId () : int
    {
        return $this->action->getChannelId();
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    public function getParentId(): ?int
    {
        return $this->parent;
    }

    public function getPreviousParentId(): ?int
    {
        return $this->previousParent;
    }

    public function getMovedBy(): int
    {
        return $this->movedBy;
    }
}