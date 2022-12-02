<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Utility\Convert;

class Team implements \JsonSerializable, ObjectInterface
{
    CONST SELF_TEAM_NAME        = "My Team";

    CONST TEAM_TYPE_SELF         = 1;
    CONST TEAM_TYPE_STANDARD     = 2;

    protected $team;

    /**
     * Team constructor.
     *
     * @param string $teamName
     * @param int $teamType
     * @param int $createdBy
     * @return Team
     */
    public static function create(string $teamName, int $teamType, int $createdBy) : Team
    {
        $instance = new self();
        $instance->team['team_name'] = $teamName;
        $instance->team['team_type'] = $teamType;
        $instance->team['created_on'] = date("Y-m-d H:i:s");
        $instance->team['created_by'] = $createdBy;
        $instance->team['updated_on'] = null;
        $instance->team['updated_by'] = null;
        return $instance;
    }

    public static function withDBData(array $a_record) : ?self
    {
        if (array_filter($a_record)) {
            $instance = new self();
            $instance->loadWithDBData($a_record);
            return $instance;
        } else {
            return null;
        }
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->team['id']       = Convert::toIntNull($a_record['id']);
        $this->team['team_name'] = Convert::toStrNull($a_record['team_name']);
        $this->team['team_type'] = Convert::toIntNull($a_record['team_type']);
        $this->team['created_on'] = Convert::toStrNull($a_record['created_on']);
        $this->team['created_by'] = Convert::toStrNull($a_record['created_by']);
        $this->team['updated_on'] = Convert::toStrNull($a_record['updated_on']);
        $this->team['updated_by'] = Convert::toStrNull($a_record['updated_by']);
    }

    public function getId() : int
    {
        return (int)$this->team['id'];
    }

    public function setId(int $id) : void
    {
        $this->team['id'] = $id;
    }

    public function getName() : string
    {
        return $this->team['team_name'];
    }

    public function setName(string $value) : void
    {
        $this->team['team_name'] = $value;
    }

    public function getTeamType() : int
    {
        return (int)$this->team['team_type'];
    }

    public function setTeamType(int $value) : void
    {
        $this->team['team_type'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->team['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->team['created_on'] = $value;
    }

    public function getCreatedBy() : int
    {
        return $this->team['created_by'];
    }

    public function setCreatedBy(string $value) : void
    {
        $this->team['created_by'] = $value;
    }

    public function getUpdatedOn() : ?string
    {
        return $this->team['updated_on'];
    }

    public function setUpdatedOn(?string $value) : void
    {
        $this->team['updated_on'] =  (empty($value) ? null : date('Y-m-d H:i:s', strtotime($value)));
    }

    public function getUpdatedBy() : ?string
    {
        return $this->team['updated_by'];
    }

    public function setUpdatedBy(?string $value) : void
    {
        $this->team['updated_by'] = $value;
    }

    public function getArray() : array
    {
        return $this->team;
    }

    public function jsonSerialize() : array
    {
        return $this->team;
    }

    public function isSelfTeam(): bool
    {
        return $this->getTeamType() === static::TEAM_TYPE_SELF;
    }
}
