<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Utility\Convert;

class ChannelGroup implements \JsonSerializable, ObjectInterface
{

    protected $instance;

    public static function withDBData(array $record) : ?self
    {
        if (array_filter($record)){
            $instance = new self();
            $instance->loadWithDBData($record);
            return $instance;
        } else {
            return null;
        }
    }

    public function loadWithDBData(array $record) : void
    {
        $this->instance['id'] = Convert::toIntNull($record['id']);
        $this->instance['user_id'] = Convert::toIntNull($record['user_id']);
        $this->instance['name'] = Convert::toStr($record['name']);
        $this->instance['virtual'] = Convert::toBool($record['virtual']);
        $this->instance['created_on'] = Convert::toStrNull($record['created_on']);
    }

    public function getId(): int
    {
        return (int) $this->instance['id'];
    }

    public function getArray(): array
    {
        return $this->instance;
    }

    public function jsonSerialize()
    {
        return $this->instance;
    }

    public function isVirtual()
    {
        return (bool)($this->instance['virtual'] ?? false);
    }

    public function getName(): string
    {
        return $this->instance['name'];
    }

    public function getUserId(): int
    {
        return (int) $this->instance['user_id'];
    }
}