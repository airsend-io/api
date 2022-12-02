<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\Json;

class Alert
{
    CONST CONTEXT_TYPE_USER                 = 10;
    CONST CONTEXT_TYPE_CHANNEL              = 20;
    CONST CONTEXT_TYPE_TEAM                 = 30;
    CONST CONTEXT_TYPE_MESSAGE              = 40;
    CONST CONTEXT_TYPE_ACTION               = 50;

    CONST ALERT_TYPE_UNKNOWN = 0;
    const ALERT_TYPE_MENTION = 1;
    const ALERT_TYPE_REACTION = 2;
    const ALERT_TYPE_QUOTE = 3;
    const ALERT_TYPE_ACTION = 4;

    protected $alert;

    protected $mutedAlert = false;

    /**
     * @param int|null $userId
     * @param $contextId
     * @param $contextType
     * @param $alertText
     * @param AlertIssuer $issuer
     * @param int $alertType
     * @return Alert
     */
    public static function create(?int $userId, $contextId, $contextType, $alertText,AlertIssuer $issuer, ?int $alertType = self::ALERT_TYPE_UNKNOWN) : self
    {
        $instance = new self();
        $instance->alert['user_id']         = $userId;
        $instance->alert['context_id']      = $contextId;
        $instance->alert['context_type']    = $contextType;
        $instance->alert['alert_text']      = $alertText;
        $instance->alert['is_read']         = false;
        $instance->alert['issuers'][]       = $issuer->getArray();
        $instance->alert['alert_type']      = $alertType;
        $instance->alert['created_on']      = date('Y-m-d H:i:s');
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
        $this->alert['id']              = Convert::toIntNull($a_record['id']);
        $this->alert['user_id']         = Convert::toIntNull($a_record['user_id']);
        $this->alert['context_id']      = Convert::toIntNull($a_record['context_id']);
        $this->alert['context_type']    = Convert::toIntNull($a_record['context_type']);
        $this->alert['alert_text']      = Convert::toStrNull($a_record['alert_text']);
        $this->alert['is_read']         = Convert::toBool($a_record['is_read']);
        $this->alert['issuers']         = Convert::toStrNull($a_record['issuers']);
        $this->alert['alert_type']      = Convert::toIntNull($a_record['alert_type']);
        $this->alert['created_on']      = Convert::toStrNull($a_record['created_on']);
    }

    public function getId() : ?int
    {
        return empty($this->alert['id']) ? null : $this->alert['id'];
    }

    public function setId(int $value) : void
    {
        $this->alert['id'] = $value;
    }

    public function getUserId() : ?int
    {
        return $this->alert['user_id'];
    }

    public function setUserId(?int $value) : void
    {
        $this->alert['user_id'] = $value;
    }

    public function getContextId() : int
    {
        return $this->alert['context_id'];
    }

    public function setContextId(?int $value) : void
    {
        $this->alert['context_id'] = $value;
    }

    public function getContextType() : ?int
    {
        return $this->alert['context_type'];
    }

    public function setContextType(?int $value) : void
    {
        $this->alert['context_type'] = $value;
    }

    public function getIsRead() : bool
    {
        return ($this->alert['is_read'] == 1);
    }

    public function setIsRead(bool  $value) : void
    {
        $this->alert['is_read'] = $value;
    }

    public function getAlertText() : string
    {
        return $this->alert['alert_text'];
    }

    public function setAlertText(string $value) : void
    {
        $this->alert['alert_text'] = $value;
    }

    public function getCreatedOn() : string
    {
        return $this->alert['created_on'];
    }

    public function addIssuer(AlertIssuer $issuer) : void
    {
        $this->convertJsonTypesToArray();
        $exists = false;
        foreach($this->alert['issuers'] as $index => $item)
        {
            $uid = (int)$item['uid'];
            if ($uid === $issuer->getUserId())
            {
                $exists = true;
                break;
            }
        }
        if (!$exists)
            $this->alert['issuers'][] = $issuer->getArray();
    }

    public function deleteIssuer(AlertIssuer $issuer) : bool
    {
        $userId = $issuer->getUserId();
        $this->convertJsonTypesToArray();
        foreach($this->alert['issuers'] as $index => $item)
        {
            $uid = (int)$item['uid'];
            if ($uid === $userId)
            {
                unset($this->alert['issuers'][$index]);
                $this->alert['issuers'] = array_values($this->alert['issuers']);
                return true;
            }
        }
        return false;
    }

    public function getIssuers() : array
    {
        $this->convertJsonTypesToArray();
        $issuers = [];
        foreach ($this->alert['issuers'] as $item) {
            $issuer = AlertIssuer::create($item['uid'],$item['dn']);
            $issuers[] = $issuer;
        }
        return $issuers;
    }

    private function convertJsonTypesToArray() : void
    {
        if (!isset($this->alert['issuers'])) {
            $this->alert['issuers'] = [];
        }
        if (is_string($this->alert['issuers'])) {
            $this->alert['issuers'] = json_decode($this->alert['issuers'], true);
        }
    }

    private function convertJsonTypesToNullableString() : void
    {
        if (!isset($this->alert['issuers'])) {
            $this->alert['issuers'] = null;
        }
        if (is_array($this->alert['issuers'])) {
            $this->alert['issuers'] = Json::encode($this->alert['issuers']);
        }
    }

    public function getArray() : array
    {
        $this->convertJsonTypesToArray();
        return $this->alert;
    }

    public function getArrayWithJsonString() : array
    {
        $this->convertJsonTypesToNullableString();
        return $this->alert;
    }

    public function jsonSerialize() : array
    {
        return $this->alert;
    }

    public function setMuteAlert(bool $muted) : void
    {
        $this->mutedAlert = $muted;
    }

    public function isMutedAlert() : bool
    {
        return $this->mutedAlert;
    }

    public function getType(): int
    {
        return $this->alert['alert_type'];
    }




}