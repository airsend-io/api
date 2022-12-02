<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class Timeline implements \JsonSerializable, ObjectInterface
{

    CONST ACTIVITY_READ      = 10;
    CONST ACTIVITY_WRITE     = 20;


    protected $timeline;

    /**
     * ChannelUser constructor.
     *
     * @param int $channelId
     * @param int $userId
     * @param int $messageId
     * @param int $timelineAction
     * @return Timeline
     */
    public static function create(int $channelId, int $userId, int $messageId, int $timelineAction): Timeline
    {
        $instance = new self();
        $instance->timeline['channel_id'] = $channelId;
        $instance->timeline['user_id'] = $userId;
        $instance->timeline['message_id'] = $messageId;
        $instance->timeline['activity'] = $timelineAction;
        $instance->timeline['created_on'] = date("Y-m-d H:i:s");
        return $instance;
    }

    /***
     * @param int $channelId
     * @param int $userId
     * @param int $messageId
     * @param int $timelineAction
     * @param string $createdOn
     * @return Timeline
     */
    public static function createWithTimestamp(int $channelId, int $userId, int $messageId, int $timelineAction, string $createdOn): Timeline
    {
        $instance = new self();
        $instance->timeline['channel_id'] = $channelId;
        $instance->timeline['user_id'] = $userId;
        $instance->timeline['message_id'] = $messageId;
        $instance->timeline['activity'] = $timelineAction;
        $instance->timeline['created_on'] = $createdOn;
        return $instance;
    }

    public static function withDBData(array $a_record) : ?self
    {
        if(array_filter($a_record)){
            $instance = new self();
            $instance->loadWithDBData($a_record);
            return $instance;
        }
        else
            return null;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->timeline['channel_id']   = Convert::toIntNull($a_record['channel_id']);
        $this->timeline['user_id']      = Convert::toIntNull($a_record['user_id']);
        $this->timeline['message_id']   = Convert::toIntNull($a_record['message_id']);
        $this->timeline['activity']     = Convert::toIntNull($a_record['activity']);
        $this->timeline['created_on']   = Convert::toStrNull($a_record['created_on']);
    }

    public function getChannelId() : int
    {
        return $this->timeline['channel_id'];
    }

    public function setChannelId(int $value) : void
    {
        $this->timeline['channel_id'] = $value;
    }

    public function getUserId() : int
    {
        return $this->timeline['user_id'];
    }

    public function setUserId(int $value) : void
    {
        $this->timeline['user_id'] = $value;
    }

    public function getMessageId() : int
    {
        return $this->timeline['message_id'];
    }

    public function setMessageId(int $value) : void
    {
        $this->timeline['message_id'] = $value;
    }

    public function getActivity() : int
    {
        return $this->timeline['activity'];
    }

    public function setActivity(int $value) : void
    {
        $this->timeline['activity'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->timeline['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->timeline['created_on'] = date('Y-m-d H:i:s',$value);
    }


    public function getArray() : array
    {
        return $this->timeline;
    }

    public function jsonSerialize() : array
    {
        return $this->timeline;
    }
}