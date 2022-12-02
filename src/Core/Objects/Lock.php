<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class Lock implements \JsonSerializable, ObjectInterface
{
    private $lock;

    public static function create(string $path, int $user_id, string $context, string $expiry=NULL)
    {
        $instance = new self();
        $instance->lock['path']         = $path;
        $instance->lock['user_id']         = $user_id;
        $instance->lock['context']         = $context;

        $instance->lock['created_on']    = date('Y-m-d H:i:s');
        if (!empty($expiry)) {
            $instance->lock['expiry'] = $expiry;
        }
        else {
            $instance->lock['expiry'] = NULL;
        }

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
        $this->lock['id']         = Convert::toIntNull($a_record['id']);
        $this->lock['path']       = Convert::toStrNull($a_record['path']);
        $this->lock['user_id']    = Convert::toIntNull($a_record['user_id']);
        $this->lock['context']    = Convert::toStrNull($a_record['context']);
        $this->lock['created_on'] = Convert::toStrNull($a_record['created_on']);
        $this->lock['expiry']     = Convert::toStrNull($a_record['expiry']);
    }

    public function id() : int
    {
        return $this->lock['id'];
    }

    public function setId(int $id) : void
    {
        $this->lock['id'] = $id;
    }

    public function userId(): int
    {
        return $this->lock['user_id'];
    }

    public function path() : string
    {
        return $this->lock['path'];
    }

    public function createdOn() : string
    {
        return $this->lock['created_on'];
    }

    public function expiry() : string
    {
        return $this->lock['expiry'];
    }

    public function setExpiry(string $expiry) : void
    {
        $this->lock['expiry'] = $expiry;
    }

    public function context() : string
    {
        if (!empty($this->lock['context']))
            return $this->lock['context'];
        else
            return "";
    }

    public function getArray(): array
    {
        return $this->lock;
    }

    public function jsonSerialize ()
    {
        return $this->lock;
    }


}