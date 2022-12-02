<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class Mention implements \JsonSerializable, ObjectInterface
{

    protected $mention;


    /**
     * @param int $messageId
     * @param string $title
     * @param string $resourceType
     * @param int $resourceId
     * @return Mention
     */
    public static function create(int $messageId, string $title, string $resourceType, int $resourceId) : self
    {
        $instance = new self();
        $instance->mention['message_id'] = $messageId;
        $instance->mention['title'] = $title;
        $instance->mention['resource_type'] = $resourceType;
        $instance->mention['resource_id'] = $resourceId;
        return $instance;
    }

    public static function withDBData(array $record) : ?self
    {
        $instance = new self();
        $instance->loadWithDBData($record);
        return $instance;
    }

    public function loadWithDBData(array $record) : void
    {
        $this->mention['id'] = Convert::toIntNull($record['id']);
        $this->mention['message_id'] = Convert::toIntNull($record['message_id']);
        $this->mention['title'] = Convert::toStr($record['title']);
        $this->mention['resource_type'] = Convert::toStr($record['resource_type']);
        $this->mention['resource_id'] = Convert::toIntNull($record['resource_id']);
    }

    public function setId(int $id): void
    {
        $this->mention['id'] = $id;
    }


    public function getArray(): array
    {
        return $this->mention;
    }

    public function jsonSerialize()
    {
        return $this->mention;
    }
}