<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class ExternalIdentity  implements \JsonSerializable, ObjectInterface
{
    const IDENTITY_PROVIDER_GOOGLE      = 1;
    const IDENTITY_PROVIDER_LINKEDIN    = 2;
    const IDENTITY_PROVIDER_APPLE       = 3;

    /**
     * User object as array
     *
     * @var array
     */
    protected $identity;

    /**
     * Create external Identity
     *
     * @param int $userId
     * @param string $email
     * @param string $displayName
     * @param int $provider
     * @param string $externalId
     * @return ExternalIdentity
     */
    public static function create(int $userId, string $email, string $displayName, int $provider, string $externalId) : self
    {
        $instance = new self();
        $instance->identity['external_id']   = $externalId;
        $instance->identity['provider']      = $provider;
        $instance->identity['email']         = $email;
        $instance->identity['phone']         = null;
        $instance->identity['user_id']       = $userId;
        $instance->identity['display_name']  = $displayName;
        $instance->identity['created_on']    = date('Y-m-d H:i:s');
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

    public function loadWithDBData(array $record) : void
    {
        $this->identity['external_id']      = Convert::toStrNull($record['external_id']);
        $this->identity['provider']         = Convert::toIntNull($record['provider']);
        $this->identity['email']            = Convert::toStrNull($record['email']);
        $this->identity['phone']            = Convert::toStrNull($record['phone']);
        $this->identity['display_name']     = Convert::toStrNull($record['display_name']);
        $this->identity['user_id']          = Convert::toIntNull($record['user_id']);
        $this->identity['created_on']       = Convert::toStrNull($record['created_on']);
    }

    public function getProvider() : int
    {
        return $this->identity['provider'];
    }

    public function setProvider(int $value) : void
    {
        $this->identity['provider'] = $value;
    }

    public function getUserId() : int
    {
        return $this->identity['user_id'];
    }

    public function setUserId(int $userId) : void
    {
        $this->identity['user_id'] = $userId;
    }

    public function getEmail() :?string
    {
        return $this->identity['email'];
    }

    public function setEmail(?string $email) : void
    {
        $this->identity['email'] =  $email;
    }

    public function setDisplayName(string $name) : void
    {
        $this->identity['display_name'] = $name;
    }

    public function getDisplayName() : string
    {
        return $this->identity['display_name'];
    }

    public function getPhone() : ?string
    {
        return $this->identity['phone'];
    }

    public function setPhone(?string $phone) : void
    {
        $this->identity['phone'] = $phone;
    }

    public function getExternalId() : ?string
    {
        return $this->identity['external_id'];
    }

    public function setExternalId(?string $value) : void
    {
        $this->identity['external_id'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->identity['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->identity['created_on'] = $value;
    }

    public function getArray() : array
    {
        return $this->identity;
    }

    public function jsonSerialize() : array
    {
        return $this->identity;
    }

}