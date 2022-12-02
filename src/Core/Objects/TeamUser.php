<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class TeamUser implements \JsonSerializable, ObjectInterface
{
    CONST TEAM_USER_ROLE_OWNER          = 100;// can edit team
    CONST TEAM_USER_ROLE_MANAGER        = 50; // cannot edit team add/update files to team, add channel to team
    CONST TEAM_USER_ROLE_COLLABORATOR   = 20; // cannot edit team add/update files to team (no diff from manager at this point)
    CONST TEAM_USER_ROLE_MEMBER         = 10;

    protected $teamuser;

    /**
     * TeamUser constructor.
     *
     * @param int $teamId
     * @param int $userId
     * @param int $userRole
     * @param int $createdBy
     * @return TeamUser
     */
    public static function create(int $teamId, int $userId, int $userRole, int $createdBy) : TeamUser
    {
        $instance = new self();
        $instance->teamuser['team_id']  = $teamId;
        $instance->teamuser['user_id']  = $userId;
        $instance->teamuser['user_role'] = $userRole;
        $instance->teamuser['created_on'] = date('Y-m-d H:i:s');
        $instance->teamuser['created_by'] = $createdBy;
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
        $this->teamuser['team_id']  = Convert::toIntNull($a_record['team_id']);
        $this->teamuser['user_id']  = Convert::toIntNull($a_record['user_id']);
        $this->teamuser['user_role'] = Convert::toIntNull($a_record['user_role']);
        $this->teamuser['created_on'] = Convert::toStrNull($a_record['created_on']);
        $this->teamuser['created_by'] = Convert::toStrNull($a_record['created_by']);
    }

    public function getTeamId() : int
    {
        return (int)$this->teamuser['team_id'];
    }

    public function setTeamId(int $value) : void
    {
        $this->teamuser['team_id'] = $value;
    }

    public function getUserId() : int
    {
        return $this->teamuser['user_id'];
    }

    public function setUserId(int $value) : void
    {
        $this->teamuser['user_id'] = $value;
    }


    public function getUserRole() : int
    {
        return $this->teamuser['user_role'];
    }

    public function setUserRole(int $value) : void
    {
        $this->teamuser['user_role'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->teamuser['created_on'];
    }

    public function setCreatedOn(string $value) : void {
        $this->teamuser['created_on'] = $value;
    }

    public function getCreatedBy() : string
    {
        return $this->teamuser['created_by'];
    }

    public function setCreatedBy(string $value) : void
    {
        $this->teamuser['created_by'] = $value;
    }

    public function getUserRoleAsString() : string
    {
        switch ($this->getUserRole())
        {
            case TeamUser::TEAM_USER_ROLE_MEMBER:
                return "member";
                break;
            case TeamUser::TEAM_USER_ROLE_OWNER:
                return "owner";
                break;
            case TeamUser::TEAM_USER_ROLE_MANAGER:
                return "manager";
                break;
            case TeamUser::TEAM_USER_ROLE_COLLABORATOR:
                return "collaborator";
                break;
            default:
                return "none";
                break;
        }
    }


    public function getArray() : array
    {
        return $this->teamuser;
    }

    public function jsonSerialize() : array
    {
        return $this->teamuser;
    }
}