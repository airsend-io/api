<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;

class ChannelUser implements \JsonSerializable, ObjectInterface
{

    // 200 - It's a virtual role for owners of the channel.

    CONST CHANNEL_USER_ROLE_ADMIN              = 100;// can edit the channel,  can add user to channel, update action, file, wiki, message
    CONST CHANNEL_USER_ROLE_MANAGER            = 50; // cannot edit channel, can add user to channel, update action, file, wiki, message
    CONST CHANNEL_USER_ROLE_COLLABORATOR_WIKI  = 30; // cannot edit channel, cannot add user to channel, can update action, file, wiki, message
    CONST CHANNEL_USER_ROLE_COLLABORATOR       = 20; // cannot edit channel, cannot add user to channel, can update action, file, message
    CONST CHANNEL_USER_ROLE_VIEWER             = 10; // only read access to channel

    const FREE_MESSAGES_ON_DM = 3;

    protected $channeluser;

    /**
     * ChannelUser constructor.
     *
     * @param int $channelId
     * @param int $userId
     * @param int $userRole
     * @param int $createdBy
     * @return ChannelUser
     * @throws \Exception
     */
    public static function create(int $channelId, int $userId, int $userRole, int $createdBy) : ChannelUser
    {
        $instance = new self();
        $instance->channeluser['channel_id']        = $channelId;
        $instance->channeluser['user_id']           = $userId;
        $instance->channeluser['user_role']         = $userRole;
        $instance->channeluser['is_favorite']       = false;
        $instance->channeluser['email_tag']         = StringUtility::generateRandomString(8);
        $instance->channeluser['created_on']        = date("Y-m-d H:i:s");
        $instance->channeluser['created_by']        = $createdBy;
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
        $this->channeluser['channel_id']        = Convert::toIntNull($a_record['channel_id']);
        $this->channeluser['user_id']           = Convert::toIntNull($a_record['user_id']);
        $this->channeluser['user_role']         = Convert::toIntNull($a_record['user_role']);
        $this->channeluser['is_favorite']       = Convert::toBoolNull($a_record['is_favorite']);
        $this->channeluser['muted']             = Convert::toBoolNull($a_record['muted']);
        $this->channeluser['blocked_on']        = Convert::toStrNull($a_record['blocked_on']);
        $this->channeluser['email_tag']         = Convert::toStrNull($a_record['email_tag']);
        $this->channeluser['read_watermark_id'] = Convert::toStrNull($a_record['read_watermark_id']);
        $this->channeluser['group_id']          = Convert::toIntNull($a_record['group_id']);
        $this->channeluser['created_on']        = Convert::toStrNull($a_record['created_on']);
        $this->channeluser['created_by']        = Convert::toStrNull($a_record['created_by']);
    }

    public function getChannelId() : int
    {
        return $this->channeluser['channel_id'];
    }

    public function getUserId() : int
    {
        return (int)$this->channeluser['user_id'];
    }

    public function getUserRole() : int
    {
        return $this->channeluser['user_role'];
    }

    public function setChannelId(int $value) : void
    {
        $this->channeluser['channel_id'] = $value;
    }

    public function setUserId(int $value) : void
    {
        $this->channeluser['user_id'] = $value;
    }

    public function setUserRole(int $value) : void
    {
        $this->channeluser['user_role'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->channeluser['created_on'];
    }


    public function setCreatedOn(string $value) : void
    {
        $this->channeluser['created_on'] = $value;
    }

    public function getCreatedBy() : string
    {
        return $this->channeluser['created_by'];
    }

    public function setCreatedBy(string $value) : void
    {
        $this->channeluser['created_by'] = $value;
    }

    public function getUserRoleAsString() : string {
        switch($this->getUserRole())
        {
            case ChannelUser::CHANNEL_USER_ROLE_VIEWER:
                return "viewer";
            case ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR:
                return "collaborator";
            case ChannelUser::CHANNEL_USER_ROLE_MANAGER:
                return "manager";
            case ChannelUser::CHANNEL_USER_ROLE_ADMIN:
                return "admin";
            default:
                return "";
        }
    }

    public function getIsFavorite() : bool
    {
        return $this->channeluser['is_favorite'];
    }

    public function setIsFavorite(bool $value) : void
    {
        $this->channeluser['is_favorite'] = $value;
    }

    public function getArray() : array {
        return $this->channeluser;
    }

    public function jsonSerialize() : array
    {
        return $this->channeluser;
    }

    public function getMuted(): bool
    {
        return $this->channeluser['muted'] ?? false;
    }

    public function isMuted(): bool
    {
        return $this->getMuted() || ($this->getBlockedOn() !== null);
    }

    public function getReadWatermarkId(): ?int
    {
        return (int) $this->channeluser['read_watermark_id'];
    }

    public function getGroupId(): ?int
    {
        return $this->channeluser['group_id'] === null ? null : (int) $this->channeluser['group_id'];
    }

    public function getBlockedOn(): ?string
    {
        return $this->channeluser['blocked_on'];
    }

    public function setBlockedOn(?string $blockedOn): void
    {
        $this->channeluser['blocked_on'] = $blockedOn;
    }
}