<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class ActionHistory implements \JsonSerializable, ObjectInterface
{

    protected $actionHistory;


    /**
     * @param int $actionId
     * @param int $userId
     * @param string $historyType
     * @param array|null $attachments
     * @return ActionHistory
     */
    public static function create(int $actionId, int $userId, string $historyType, ?array $attachments = null) : self
    {
        $instance = new self();
        $instance->actionHistory['action_id'] = $actionId;
        $instance->actionHistory['user_id'] = $userId;
        $instance->actionHistory['history_type'] = $historyType;
        $instance->actionHistory['attachments'] = $attachments;
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
        $this->actionHistory['id'] = Convert::toIntNull($record['id']);
        $this->actionHistory['action_id'] = Convert::toIntNull($record['action_id']);
        $this->actionHistory['user_id'] = Convert::toIntNull($record['user_id']);
        $this->actionHistory['history_type'] = Convert::toStr($record['history_type']);
        $this->actionHistory['attachments'] = Convert::toArrayNull($record['attachments']);
        $this->actionHistory['created_on'] = Convert::toStrNull($record['created_on']);
    }

    public function setId(int $id): void
    {
        $this->actionHistory['id'] = $id;
    }


    public function getArray(): array
    {
        return $this->actionHistory;
    }

    public function jsonSerialize()
    {
        return $this->actionHistory;
    }

    public function getUserId(): int
    {
        return (int) $this->actionHistory['user_id'];
    }

    public function getAttachments()
    {
        return $this->actionHistory['attachments'];
    }
}