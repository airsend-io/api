<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class Version
{
    private $version;

    public static function create(string $notes)
    {
        $instance = new self();
        $instance->version['notes']         = $notes;
        $instance->version['created_on']    = date('Y-m-d H:i:s');
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
        $this->version['id']            = Convert::toIntNull($a_record['id']);
        $this->version['notes']         = Convert::toStrNull($a_record['notes']);
        $this->version['created_on']    = Convert::toStrNull($a_record['created_on']);
    }

    public function getId() : int
    {
        return $this->version['id'];
    }

    public function getNotes() : string
    {
        return $this->version['notes'];
    }

    public function setNotes(string $notes) : void
    {
        $this->version['notes'] = $notes;
    }

    public function getCreatedOn() : string
    {
        return $this->version['created_on'];
    }

    public function getArray()
    {
        return $this->version;
    }
}