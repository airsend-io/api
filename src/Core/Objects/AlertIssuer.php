<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Utility\Convert;

class AlertIssuer
{
    protected $issuer;

    /**
     * MessageEmoticon constructor.
     *
     * @param int $userId
     * @param string $displayName
     * @param string $emojiValue
     * @return MessageEmoticon
     */
    public static function create(int $userId, string $displayName) : self
    {
        $instance = new self();
        $instance->issuer['uid'] = $userId;
        $instance->issuer['dn'] = $displayName;
        return $instance;
    }

    public function loadWithDBData($a_record) : void
    {
        $this->issuer['uid']  = Convert::toIntNull($a_record['uid']);
        $this->issuer['dn']   = Convert::toStrNull($a_record['dn']);
    }

    public static function withDBData($a_record) : self
    {
        $instance = new self();
        $instance->loadWithDBData($a_record);
        return $instance;
    }

    public function getUserId() : int
    {
        return $this->issuer['uid'];
    }

    public function setUserId(string $value) : void
    {
        $this->issuer['uid'] = $value;
    }

    public function getDisplayName() : string
    {
        return $this->issuer['dn'];
    }

    public function setDisplayName(string $value)
    {
        $this->issuer['dn'] = $value;
    }

    public function getArray() : array
    {
        return $this->issuer;
    }

}