<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class ChannelPath implements \JsonSerializable, ObjectInterface
{
    CONST CHANNEL_PATH_TYPE_FILE     = 1;
    CONST CHANNEL_PATH_TYPE_WIKI     = 2;
    CONST CHANNEL_PATH_TYPE_DELETED  = 3;

    protected $channelPath;

    public static function create(int  $channelId, int $pathType, string $path, int $createdBy) : ChannelPath
    {
        $instance = new self();
        $instance->channelPath['channel_id'] = $channelId;
        $instance->channelPath['path_type'] = $pathType;
        $instance->channelPath['path_value'] = $path;
        $instance->channelPath['created_on'] = date('Y-m-d H:i:s');
        $instance->channelPath['created_by'] = $createdBy;
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
        $this->channelPath['id']         = Convert::toIntNull($a_record['id']);
        $this->channelPath['channel_id'] = Convert::toIntNull($a_record['channel_id']);
        $this->channelPath['path_type']  = Convert::toIntNull($a_record['path_type']);
        $this->channelPath['path_value'] = Convert::toStrNull($a_record['path_value']);
        $this->channelPath['created_on'] = Convert::toStrNull($a_record['created_on']);
        $this->channelPath['created_by'] = Convert::toStrNull($a_record['created_by']);
    }

    public function getId() : int
    {
        return $this->channelPath['id'];
    }

    public function setId(int $value) : void
    {
        $this->channelPath['id'] = $value;
    }

    public function getChannelId() : int
    {
        return $this->channelPath['channel_id'];
    }

    public function setChannelId(int $value) : void
    {
        $this->channelPath['channel_id'] = $value;
    }

    public function getPathType() : int
    {
        return (int)$this->channelPath['path_type'];
    }

    public function setPathType(int $value) : void
    {
        $this->channelPath['path_type'] = $value;
    }

    public function getPath() : string
    {
        return $this->channelPath['path_value'];
    }

    public function setPath(string $value) : void
    {
        $this->channelPath['path_value'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->channelPath['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->channelPath['created_on'] = $value;
    }

    public function getArray() : array
    {
        return $this->channelPath;
    }

    public function jsonSerialize() : array
    {
        return $this->channelPath;
    }
}