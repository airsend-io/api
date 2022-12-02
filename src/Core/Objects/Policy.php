<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class Policy
{
    /* List policy names here */
    CONST POLICY_NAME_QUOTA                 = "Quota";

    CONST CONTEXT_TYPE_GLOBAL               = 0;
    CONST CONTEXT_TYPE_USER                 = 10;
    CONST CONTEXT_TYPE_CHANNEL              = 20;
    CONST CONTEXT_TYPE_TEAM                 = 30;
    CONST CONTEXT_TYPE_MESSAGE              = 40;
    CONST CONTEXT_TYPE_ACTION               = 50;

    protected $policy;

    public static function createGlobalPolicy(string $policyName, string $policyValue): self
    {
        return self::create(0, Policy::CONTEXT_TYPE_GLOBAL, $policyName, $policyValue);
    }

    public static function createChannelPolicy(int $channelId, string $policyName, string $policyValue): self
    {
        return self::create($channelId, Policy::CONTEXT_TYPE_CHANNEL, $policyName, $policyValue);
    }

    public static function createUserPolicy(int $userId, string $policyName, string $policyValue): self
    {
        return self::create($userId, Policy::CONTEXT_TYPE_USER, $policyName, $policyValue);
    }

    public static function createTeamPolicy(int $teamId, string $policyName, string $policyValue): self
    {
        return self::create($teamId, Policy::CONTEXT_TYPE_TEAM, $policyName, $policyValue);
    }

    public static function create(int $contextId, int $contextType, string $policyName, string $policyValue) : self
    {
        $instance = new self();
        $instance->policy['context_id'] = $contextId;
        $instance->policy['context_type'] = $contextType;
        $instance->policy['policy_name'] = $policyName;
        $instance->policy['policy_value'] = $policyValue;
        $instance->policy['updated_on'] = date('Y-m-d H:i:s');
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
        $this->policy['id']             = Convert::toIntNull($a_record['id']);
        $this->policy['context_id']     = Convert::toIntNull($a_record['context_id']);
        $this->policy['context_type']   = Convert::toIntNull($a_record['context_type']);
        $this->policy['policy_name']    = Convert::toStrNull($a_record['policy_name']);
        $this->policy['policy_value']   = Convert::toStrNull($a_record['policy_value']);
        $this->policy['updated_on']     = Convert::toStrNull($a_record['updated_on']);
    }

    public function getId() : int
    {
        return $this->policy['id'];
    }

    public function setId(int $value) : void
    {
        $this->policy['id'] = $value;
    }

    public function getContextId() : int
    {
        return $this->policy['context_id'];
    }

    public function setContextId(?int $value) : void
    {
        $this->policy['context_id'] = $value;
    }

    public function getContextType() : ?int
    {
        return $this->policy['context_type'];
    }

    public function setContextType(?int $value) : void
    {
        $this->policy['context_type'] = $value;
    }

    public function getPolicyName() : string
    {
        return $this->policy['policy_name'];
    }

    public function setPolicyName(string $value) : void
    {
        $this->policy['policy_name'] = $value;
    }

    public function getPolicyValue() : string
    {
        return $this->policy['policy_value'] ?? '';
    }

    public function setPolicyValue(string $value) : void
    {
        $this->policy['policy_value'] = $value;
    }

    public function getUpdateOn() : string
    {
        return $this->policy['updated_on'];
    }

    public function setUpdateOn(string $value) : void
    {
        $this->policy['updated_on'] = $value;
    }

    public function getArray() : array
    {
        return $this->policy;
    }
}