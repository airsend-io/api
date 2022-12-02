<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


class MessageParent
{
    protected $parentMessage;

    /**
     * MessageAttachment constructor.
     *
     * @param string $content
     * @param string $contentType
     * @return MessageAttachment
     */
    public static function create(int $messageId, int $userId, string $contentText, string $createdOn) : self
    {
        $instance = new self();
        $instance->parentMessage['mid'] = $messageId;
        $instance->parentMessage['uid'] = $userId;
        $instance->parentMessage['txt'] = $contentText;
        $instance->parentMessage['ts'] = $createdOn;
        return $instance;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->parentMessage['mid'] = Convert::toIntNull($a_record['mid']);
        $this->parentMessage['uid'] = Convert::toIntNull($a_record['uid']);
        $this->parentMessage['txt'] = Convert::toStrNull($a_record['txt']);
        $this->parentMessage['ts'] = Convert::toStrNull($a_record['ts']);
    }

    public static function withDBData(array $a_record) : self
    {
        $instance = new self();
        $instance->loadWithDBData($a_record);
        return $instance;
    }

    public function getContentText() : string
    {
        return $this->parentMessage['txt'];
    }

    public function setContentText(string $value) : void
    {
        $this->parentMessage['txt'] = $value;
    }

    public function getMessageId() : int
    {
        return $this->parentMessage['mid'];
    }

    public function setMessageId(int $value) : void
    {
        $this->parentMessage['mid'] = $value;
    }

    public function getUserId() : int
    {
        return $this->parentMessage['uid'];
    }

    public function setUserId(int $value) : void
    {
        $this->parentMessage['uid'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->parentMessage['ts'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->parentMessage['ts'] = $value;
    }

    public function getArray() : array
    {
        return $this->parentMessage;
    }
}