<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use JsonSerializable;
use Symfony\Contracts\EventDispatcher\Event;


/**
 * Class ASEvent
 * Base class for events in Airsend subsystem.
 * All events must derive from this class.
 * @package CodeLathe\Core\Messaging\Events
 */
abstract class ASEvent extends Event implements JsonSerializable
{

    protected $selfNotificationOnly = false;
    /**
     * This method is for subscribing to this event in background mode.
     *
     * @return string background version of the event
     */
    static function backgroundEventName(): string
    {
        return "bg_".static::eventName();
    }

    /**
     * This method is for subscribing to this event in foreground mode.
     * @return string foreground version of the event
     */
    static function foregroundEventName(): string
    {
        return "fg_".static::eventName();
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    abstract static function eventName(): string;

    /**
     * Get array representation of the event payload
     * @return array
     */
    abstract function getPayloadArray(): array;

    /**
     * Function to serialize PHP object to serialized object to
     * send via kafka message queue
     *
     * @return string
     */
    public function toPSO()
    {
        return serialize($this);
    }

    /**
     * Array version to json_encode on
     * @return array|mixed
     */
    public function jsonSerialize ()
    {
        $dataArray = array();
        $dataArray['event_name']  = static::eventName();
        $dataArray['payload'] = $this->getPayloadArray();
        return $dataArray;
    }


    public function setSelfOnly(bool $selfOnly)
    {
        $this->selfNotificationOnly = $selfOnly;
    }
    /**
     * @return bool
     */
    public function selfOnly(): bool
    {
        return $this->selfNotificationOnly;
    }


}