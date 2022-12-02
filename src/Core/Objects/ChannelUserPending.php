<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Utility\Convert;

class ChannelUserPending implements \JsonSerializable, ObjectInterface
{

    protected $instance;

    /**
     * ChannelUser constructor.
     *
     * @param int $channelId
     * @param int $userId
     * @return ChannelUserPending
     */
    public static function create(int $channelId, int $userId): self
    {
        $instance = new self();
        $instance->instance['channel_id'] = $channelId;
        $instance->instance['user_id'] = $userId;
        $instance->instance['created_on'] = date("Y-m-d H:i:s");
        return $instance;
    }

    public static function withDBData(array $a_record): ?self
    {
        if (array_filter($a_record)) {
            $instance = new self();
            $instance->loadWithDBData($a_record);
            return $instance;
        } else {
            return null;
        }
    }

    public function loadWithDBData(array $record): void
    {
        $this->instance['channel_id'] = Convert::toIntNull($record['channel_id']);
        $this->instance['user_id'] = Convert::toIntNull($record['user_id']);
        $this->instance['created_on'] = Convert::toStrNull($record['created_on']);
    }

    public function getChannelId(): int
    {
        return $this->instance['channel_id'];
    }

    public function getUserId(): int
    {
        return (int)$this->instance['user_id'];
    }

    public function setChannelId(int $value): void
    {
        $this->instance['channel_id'] = $value;
    }

    public function setUserId(int $value): void
    {
        $this->instance['user_id'] = $value;
    }

    public function getCreatedOn(): string
    {
        return $this->instance['created_on'];
    }

    public function jsonSerialize(): array
    {
        return $this->instance;
    }

    public function getArray(): array
    {
        return $this->instance;
    }
}