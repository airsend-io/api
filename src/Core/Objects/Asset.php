<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;
use phpDocumentor\Reflection\Types\Mixed_;

class Asset implements \JSONSerializable, ObjectInterface
{

    CONST CONTEXT_TYPE_USER                 = 10;
    CONST CONTEXT_TYPE_CHANNEL              = 20;

    CONST ASSET_TYPE_PROFILE_IMAGE                = 1;
    CONST ASSET_TYPE_BACKGROUND_IMAGE             = 2;
    CONST ASSET_TYPE_CHANNEL_LOGO                 = 3;


    CONST ATTR_SIZE_XSMALL             = 1;
    CONST ATTR_SIZE_SMALL              = 2;
    CONST ATTR_SIZE_MEDIUM             = 3;
    CONST ATTR_SIZE_LARGE              = 4;

    protected $assets;

    /**
     * Create Asset
     *
     * @param int $contextId
     * @param int $contextType
     * @param int $assetType
     * @param int $attribute
     * @param string $mime
     * @param mixed $dataBlob
     * @param int $createdBy
     * @return Asset
     */
    public static function create(int $contextId, int $contextType, int $assetType, int $attribute, string $mime, $dataBlob, int $createdBy)  : self
    {
        $instance = new self();
        $instance->assets['context_id'] = $contextId;
        $instance->assets['context_type'] = $contextType;
        $instance->assets['asset_type'] = $assetType;
        $instance->assets['attribute'] = $attribute;
        $instance->assets['mime'] = $mime;
        $instance->assets['asset_data'] = $dataBlob;
        $instance->assets['created_on'] = date('Y-m-d H:i:s');
        $instance->assets['created_by'] = $createdBy;
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
        $this->assets['id']             = Convert::toIntNull($a_record['id']);
        $this->assets['context_id']     = Convert::toIntNull($a_record['context_id']);
        $this->assets['context_type']   = Convert::toIntNull($a_record['context_type']);
        $this->assets['asset_type']     = Convert::toIntNull($a_record['asset_type']);
        $this->assets['attribute']      = Convert::toIntNull($a_record['attribute']);
        $this->assets['mime']           = Convert::toStrNull($a_record['mime']);
        $this->assets['asset_data']     = Convert::toStrNull($a_record['asset_data']);
        $this->assets['created_on']     = Convert::toStrNull($a_record['created_on']);
        $this->assets['created_by']     = Convert::toIntNull($a_record['created_by']);
    }

    public function getId() : int
    {
        return $this->assets['id'];
    }

    public function setId(int $value) : void
    {
        $this->assets['id'] = $value;
    }


    public function getContextId() : int
    {
        return $this->assets['context_id'];
    }

    public function setContextId(int $value) : void
    {
        $this->assets['context_id'] = $value;
    }

    public function getContextType() : int
    {
        return $this->assets['context_type'];
    }

    public function setContextType(int $value) : void
    {
        $this->assets['context_type'] = $value;
    }

    public function getAssetType() : int
    {
        return $this->assets['asset_type'];
    }

    public function setAssetType(int $value) : void
    {
        $this->assets['asset_type'] = $value;
    }

    public function getAttribute() : int
    {
        return $this->assets['attribute'];
    }

    public function setAttribute(int $value) : void
    {
        $this->assets['attribute'] = $value;
    }

    public function getData()
    {
        return $this->assets['asset_data'];
    }

    public function setData($value) : void
    {
        $this->assets['asset_data'] = $value;
    }

    public function getMime() : string
    {
        return $this->assets['mime'];
    }

    public function setMime($value) : void
    {
        $this->assets['mime'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->assets['created_on'];
    }

    public function setCreatedOn(string $value)  : void
    {
        $this->assets['created_on'] = $value;
    }

    public function getArray() : array
    {
        return $this->assets;
    }

    public function jsonSerialize() : array
    {
        return $this->assets;
    }
}