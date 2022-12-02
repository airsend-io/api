<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Realtime;


/**
 * Class RtmToken
 *
 * Store information about an RTM token
 *
 * @package CodeLathe\Core\Managers\Realtime
 */
class RtmToken implements \JsonSerializable
{
    protected $rtmToken;

    /**
     * Create RtmToken
     *
     * @param int $userId
     * @param string $userName
     * @param string $clientIp Client IP
     * @param string $userAgent User Agent
     * @param string $expiry
     * @param string $fingerPrint Unique key
     * @param string $topic
     * @return RtmToken
     */
    public static function create(int $userId,
                                  string $userName,
                                  string $clientIp,
                                  string $userAgent,
                                  string $expiry,
                                  string $fingerPrint,
                                  string $topic)  : RtmToken
    {
        $instance = new self();
        $instance->rtmToken['user_id'] = $userId;
        $instance->rtmToken['user_name'] = $userName;
        $instance->rtmToken['client_ip'] = $clientIp;
        $instance->rtmToken['user_agent'] = $userAgent;
        $instance->rtmToken['expiry'] = $expiry;
        $instance->rtmToken['finger_print'] = $fingerPrint;
        $instance->rtmToken['topic'] = $topic;
        $instance->rtmToken['created_on'] = time();
        return $instance;
    }

    public static function withDBData(array $a_record) : self
    {
        $instance = new self();
        $instance->loadWithDBData($a_record);
        return $instance;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->rtmToken = $a_record;
    }


    public function userId(): int
    {
        return (int)$this->rtmToken['user_id'];
    }

    public function clientIp(): string
    {
        return $this->rtmToken['client_ip'];
    }

    public function userAgent(): string
    {
        return $this->rtmToken['user_agent'];
    }

    public function fingerPrint(): string
    {
        return $this->rtmToken['finger_print'];
    }

    public function expiry(): string
    {
        return $this->rtmToken['expiry'];
    }

    public function getArray() : array
    {
        return $this->rtmToken;
    }

    public function jsonSerialize() : array
    {
        return $this->rtmToken;
    }

    public function topic(): string
    {
        return $this->rtmToken['topic'];
    }

    public function createdOn(): ?int
    {
        return isset($this->rtmToken['created_on']) ? (int)$this->rtmToken['created_on'] : null;
    }


}