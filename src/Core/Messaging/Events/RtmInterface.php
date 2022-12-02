<?php


namespace CodeLathe\Core\Messaging\Events;


interface RtmInterface
{

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId(): int;

    /**
     * If the RTM event has to be sent to just the generator
     * @return bool
     */
    public function selfOnly(): bool;
}