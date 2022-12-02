<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class UserAction
{
    protected $userAction;

    /**
     * UserAction constructor.
     *
     * @param int $actionId
     * @param int $userId
     * @param int $createdBy
     * @return UserAction
     */
    public static function create(int $actionId, int $userId, int $createdBy) : UserAction
    {
        $instance = new self();
        $instance->userAction['action_id']   = $actionId;
        $instance->userAction['user_id']      = $userId;
        $instance->userAction['created_on']   = date("Y-m-d H:i:s");
        $instance->userAction['created_by']   = $createdBy;
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

    public function loadWithDBData($a_record) : void
    {
        $this->userAction['action_id']   = Convert::toIntNull($a_record['action_id']);
        $this->userAction['user_id']      = Convert::toIntNull($a_record['user_id']);
        $this->userAction['created_on']   = Convert::toStrNull($a_record['created_on']);
        $this->userAction['created_by']   = Convert::toStrNull($a_record['created_by']);
    }

    public function getActionId() : int
    {
        return $this->userAction['action_id'];
    }

    public function setActionId(int $value) : void
    {
        $this->userAction['action_id'] = $value;
    }

    public function getUserId() : int
    {
        return $this->userAction['user_id'];
    }

    public function setUserId(int $value) : void
    {
        $this->userAction['user_id'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->userAction['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->userAction['created_on'] = $value;
    }

    public function getCreatedBy() : string
    {
        return $this->userAction['created_by'];
    }

    public function setCreatedBy(string $value) : void
    {
        $this->userAction['created_by'] = $value;
    }

    public function getArray() : array
    {
        return $this->userAction;
    }

    public function jsonSerialize() : array
    {
        return $this->userAction;
    }
}