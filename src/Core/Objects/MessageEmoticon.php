<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class MessageEmoticon
{
    protected $emoticon;

    /**
     * MessageEmoticon constructor.
     *
     * @param int $userId
     * @param string $displayName
     * @param string $emojiValue
     * @return MessageEmoticon
     */
    public static function create(int $userId, string $displayName,  string $emojiValue) : MessageEmoticon
    {
        $instance = new self();
        $instance->emoticon['uid'] = $userId;
        $instance->emoticon['dn'] = $displayName;
        $instance->emoticon['ev'] = $emojiValue;
        return $instance;
    }

    public function loadWithDBData($a_record) : void
    {
        $this->emoticon['uid']  = Convert::toIntNull($a_record['uid']);
        $this->emoticon['dn']   = Convert::toStrNull($a_record['dn']);
        $this->emoticon['ev']   = Convert::toStrNull($a_record['ev']);
    }

    public static function withDBData($a_record) : self
    {
        $instance = new self();
        $instance->loadWithDBData($a_record);
        return $instance;
    }

    public function getUserId() : int
    {
        return $this->emoticon['uid'];
    }

    public function setUserId(string $value) : void
    {
        $this->emoticon['uid'] = $value;
    }

    public function getDisplayName() : string
    {
        return $this->emoticon['dn'];
    }

    public function setDisplayName(string $value)
    {
        $this->emoticon['dn'] = $value;
    }

    public function getEmojiValue() : string
    {
        return $this->emoticon['ev'];
    }

    public function setEmojiValue(string $value) : void
    {
        $this->emoticon['ev'] = $value;
    }

    public function getArray() : array
    {
        return $this->emoticon;
    }
}