<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\User;

/**
 * This event is for tracking User login event
 */

class NotifyAlert extends  ASEvent
{
    const NAME = 'alert.notification';

    /**
     * @var Alert
     */
    protected $alert;

    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }

    public function getAlert(): Alert
    {
        return $this->alert;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName(): string
    {
        return self::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray (): array
    {
        return $this->alert->getArray();
    }
}