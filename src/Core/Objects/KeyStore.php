<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class KeyStore implements \JsonSerializable, ObjectInterface
{

    protected $store;

    public static function create(string $key, string $value)
    {
        $instance = new self();
        $instance->store['key']    = $key;
        $instance->store['value']  = $value;
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
        $this->store['key'] = Convert::toStrNull($a_record['key']);
        $this->store['value'] = Convert::toStrNull($a_record['value']);
    }

    public function getKey() : string
    {
        return $this->store['key'];
    }

    public function setKey(string $key) : void
    {
        $this->store['key'] = $key;
    }

    public function getValue() : string
    {
        return $this->store['value'];
    }

    public function setValue(string $value) : void
    {
        $this->store['value'] = $value;
    }

    public function getArray() : array
    {
        return $this->store;
    }

    public function jsonSerialize() : array
    {
        return $this->store;
    }
}