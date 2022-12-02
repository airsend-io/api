<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\Database;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use phpDocumentor\Reflection\Types\Integer;

class Channel implements \JsonSerializable, ObjectInterface
{
    CONST CHANNEL_STATUS_OPEN                   = 1;
    CONST CHANNEL_STATUS_CLOSED                 = 2;

    protected $channel;

    /**
     * Channel constructor.
     *
     * @param int $teamId
     * @param string $name
     * @param string $email
     * @param int $status
     * @param int $ownedBy
     * @param string|null $blurb
     * @param bool $requireJoinApproval
     * @param bool|null $allowExternalRead
     * @param bool $openTeamJoin
     * @param int|null $contactFormId
     * @param int|null $contactFormFillerId
     * @return Channel
     */
    public static function create(int $teamId,
                                  string $name,
                                  string $email,
                                  int $status,
                                  int $ownedBy,
                                  ?string $blurb = '',
                                  ?bool $requireJoinApproval = true,
                                  ?bool $allowExternalRead = false,
                                  ?int $contactFormId = null,
                                  ?int $contactFormFillerId = null,
                                  bool $openTeamJoin = false) : Channel
    {
        $instance = new self();
        $instance->channel['team_id']           = $teamId;
        $instance->channel['channel_name']      = $name;
        $instance->channel['channel_email']     = $email;
        $instance->channel['blurb']             = $blurb;
        $instance->channel['locale']            = null;
        $instance->channel['default_joiner_role'] = null;
        $instance->channel['default_invitee_role'] = null;
        $instance->channel['channel_status']    = $status;
        $instance->channel['is_auto_closed']    = false;
        $instance->channel['close_after_days']  = null;
        $instance->channel['last_active_on']    = date("Y-m-d H:i:s");
        $instance->channel['has_logo']          = false;
        $instance->channel['has_background']    = false;
        $instance->channel['require_join_approval'] = $requireJoinApproval ?? true;
        $instance->channel['allow_external_read'] = $allowExternalRead ?? false;
        $instance->channel['open_team_join'] = $openTeamJoin ?? false;
        $instance->channel['contact_form_id']   = $contactFormId;
        $instance->channel['contact_form_filler_id'] = $contactFormFillerId;
        $instance->channel['one_one']           = false;
        $instance->channel['one_one_approved']  = false;
        $instance->channel['created_on']        = date("Y-m-d H:i:s");
        $instance->channel['owned_by']        = $ownedBy;
        $instance->channel['updated_on']        = null;
        $instance->channel['updated_by']        = null;
        return $instance;
    }

    public static function withDBData(array $record) : ?self
    {
        $record = Database::normalizeRecordForDataStore('channels', $record);
        $instance = new self();
        $instance->loadWithDBData($record);
        return $instance;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->channel['id']                = Convert::toIntNull($a_record['id']);
        $this->channel['team_id']           = Convert::toIntNull($a_record['team_id']);
        $this->channel['channel_name']      = Convert::toStrNull($a_record['channel_name']);
        $this->channel['channel_email']     = Convert::toStrNull($a_record['channel_email']);
        $this->channel['blurb']             = Convert::toStr($a_record['blurb']);
        $this->channel['locale']             = Convert::toStr($a_record['locale']);
        $this->channel['default_joiner_role'] = Convert::toIntNull($a_record['default_joiner_role']);
        $this->channel['default_invitee_role'] = Convert::toIntNull($a_record['default_invitee_role']);
        $this->channel['channel_status']    = Convert::toIntNull($a_record['channel_status']);
        $this->channel['is_auto_closed']    = Convert::toBool($a_record['is_auto_closed']);
        $this->channel['close_after_days']  = Convert::toIntNull($a_record['close_after_days']);
        $this->channel['last_active_on']    = Convert::toStrNull($a_record['last_active_on']);
        $this->channel['has_logo']          = Convert::toBool($a_record['has_logo']);
        $this->channel['has_background']    = Convert::toBool($a_record['has_background']);
        $this->channel['require_join_approval'] = Convert::toBool($a_record['require_join_approval'] ?? true);
        $this->channel['allow_external_read'] = Convert::toBool($a_record['allow_external_read'] ?? true);
        $this->channel['open_team_join'] = Convert::toBool($a_record['open_team_join'] ?? false);
        $this->channel['contact_form_id']   = Convert::toIntNull($a_record['contact_form_id']);
        $this->channel['contact_form_filler_id']   = Convert::toIntNull($a_record['contact_form_filler_id']);
        $this->channel['one_one']           = Convert::toBool($a_record['one_one']);
        $this->channel['one_one_approved']  = Convert::toBool($a_record['one_one_approved']);
        $this->channel['created_on']        = Convert::toStrNull($a_record['created_on']);
        $this->channel['owned_by']        = Convert::toIntNull($a_record['owned_by']);
        $this->channel['updated_on']        = Convert::toStrNull($a_record['updated_on']);
        $this->channel['updated_by']        = Convert::toStrNull($a_record['updated_by']);
    }

    public function getId() : int
    {
        return (int)$this->channel['id'];
    }

    public function setId(int $id) : void
    {
        $this->channel['id'] = $id;
    }

    public function getTeamId() : int
    {
        return $this->channel['team_id'];
    }

    public function setTeamId(int $id) : void
    {
        $this->channel['team_id'] = $id;
    }

    public function getEmail() : string
    {
        return $this->channel['channel_email'];
    }

    public function setEmail(string $value) : void
    {
        $this->channel['channel_email'] = $value;
    }

    public function getName() : string
    {
        return $this->channel['channel_name']??"Invalid Name";
    }

    public function setName(string $value) : void
    {
        $this->channel['channel_name'] = $value;
    }

    public function getChannelStatus() : int
    {
        return $this->channel['channel_status'] ;
    }

    public function setChannelStatus(int $value) : void
    {
        $this->channel['channel_status'] = $value;
    }

    public function getIsAutoClosed() : bool
    {
        return $this->channel['is_auto_closed'];
    }

    public function setIsAutoClosed(bool $value) : void
    {
        $this->channel['is_auto_closed'] = $value;
    }

    public function getCloseAfterDays() : ?int
    {
        return $this->channel['close_after_days'];
    }

    public function setCloseAfterDays(?int $value) : void
    {
        $this->channel['close_after_days'] = $value;
    }

    public function getLastActiveOn() : string
    {
        return $this->channel['last_active_on'];
    }

    public function setLastActiveOn(string $value) : void
    {
        $this->channel['last_active_on'] = date('Y-m-d H:i:s', strtotime($value));
    }

    public function setHasLogo(bool $value) : void
    {
        $this->channel['has_logo'] = $value;
    }

    public function getHasLogo() : bool
    {
        return (empty($this->channel['has_logo']) ? false : boolval($this->channel['has_logo']));
    }

    public function setHasBackground(bool $value) : void
    {
        $this->channel['has_background'] = $value;
    }

    public function getHasBackground() : bool
    {
        return (empty($this->channel['has_background']) ? false : boolval($this->channel['has_background']));
    }



    public function getCreatedOn() : string
    {
        return $this->channel['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->channel['created_on'] = $value;
    }

    public function getOwnedBy() : int
    {
        return (int) $this->channel['owned_by'];
    }

    public function setOwnedBy(int $value) : void
    {
        $this->channel['owned_by'] = $value;
    }

    public function getUpdatedOn() : ?string
    {
        return $this->channel['updated_on'];
    }

    public function setUpdatedOn(?string $value) : void
    {
        $this->channel['updated_on'] =  (empty($value) ? null : date('Y-m-d H:i:s', strtotime($value)));
    }

    public function getUpdatedBy() : ?string
    {
        return $this->channel['updated_by'];
    }

    public function setUpdatedBy(?string $value) : void
    {
        $this->channel['updated_by'] = $value;
    }

    public function getArray() : array
    {
        return $this->channel;
    }

    public function jsonSerialize() : array
    {
        return $this->channel;
    }

    public function setBlurb(string $blurb): void
    {
        $this->channel['blurb'] = $blurb;
    }

    public function getBlurb(): string
    {
        return $this->channel['blurb'] ?? '';
    }

    public function setRequireJoinApproval(bool $value): void
    {
        $this->channel['require_join_approval'] = $value;
    }

    public function getRequireJoinApproval(): bool
    {
        return $this->channel['require_join_approval'] ?? true;
    }

    public function setOneOnOne(bool $value)
    {
        $this->channel['one_one'] = $value;
    }

    public function getOneOnOne(): bool
    {
        return $this->channel['one_one'];
    }

    public function setOneOnOneApproved(bool $value)
    {
        $this->channel['one_one_approved'] = $value;
    }

    public function getOneOnOneApproved(): bool
    {
        return $this->channel['one_one_approved'];
    }

    public function getOneOnOneMuted(): bool
    {
        return $this->getOneOnOne() && !$this->getOneOnOneApproved();
    }

    public function getLocale(): ?string
    {
        return $this->channel['locale'];
    }

    public function setLocale(string $locale): void
    {
        $this->channel['locale'] = $locale;
    }

    public function getDefaultJoinerRole(): int
    {
        return !empty($this->channel['default_joiner_role']) ? $this->channel['default_joiner_role'] : ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR;
    }

    public function getDefaultInviteeRole(): int
    {
        return !empty($this->channel['default_invitee_role']) ? $this->channel['default_invitee_role'] : ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI;
    }

    public function setDefaultJoinerRole(int $role): void
    {
        $this->channel['default_joiner_role'] = $role;
    }

    public function setDefaultInviteeRole(int $role): void
    {
        $this->channel['default_invitee_role'] = $role;
    }

    public function setAllowExternalRead(bool $allowExternalRead): void
    {
        $this->channel['allow_external_read'] = $allowExternalRead;
    }

    public function getAllowExternalRead(): bool
    {
        return $this->channel['allow_external_read'];
    }

    public function isOpenForTeamJoin(): bool
    {
        return (bool)$this->channel['open_team_join'];
    }

    public function setOpenForTeamJoin(bool $open): void
    {
        $this->channel['open_team_join'] = $open;
    }


};
