<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\Json;
use Psr\Log\LoggerInterface;

class Action implements \JsonSerializable, ObjectInterface
{
    CONST ACTION_TYPE_REMINDER              = 1;
    CONST ACTION_TYPE_REVIEW                = 2;
    CONST ACTION_TYPE_UPDATE                = 3;
    CONST ACTION_TYPE_SIGN                  = 4;

    CONST ACTION_STATUS_PENDING             = 0;
    CONST ACTION_STATUS_COMPLETE            = 1;


    protected $action;

    protected $children = [];

    /**
     * Create Action
     *
     * @param int $channelId
     * @param string $name
     * @param string $desc
     * @param int $actionType
     * @param int $actionStatus
     * @param string $dueOnDate
     * @param int $createdBy
     * @param int $orderPosition
     * @param int $parentId
     * @return Action
     */
    public static function create(int $channelId,
                                  string $name,
                                  ?string $desc,
                                  int $actionType,
                                  int $actionStatus,
                                  ?string $dueOnDate,
                                  int $createdBy,
                                  int $orderPosition,
                                  ?int $parentId)  : Action
    {
        $instance = new self();
        $instance->action['channel_id'] = $channelId;
        $instance->action['parent_id'] = $parentId;
        $instance->action['action_name'] = $name;
        $instance->action['action_desc'] = $desc;
        $instance->action['action_type'] = $actionType;
        $instance->action['action_status'] = $actionStatus;
        $instance->action['order_position'] = $orderPosition;
        $instance->action['due_on'] = empty($dueOnDate) ? null: date('Y-m-d H:i:s',strtotime($dueOnDate));
        $instance->action['created_on'] = date('Y-m-d H:i:s');
        $instance->action['created_by'] = $createdBy;
        $instance->action['updated_on'] = null;
        $instance->action['updated_by'] = null;
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
        $this->action['id']             = Convert::toIntNull($a_record['id']);
        $this->action['channel_id']     = Convert::toIntNull($a_record['channel_id']);
        $this->action['parent_id']     = Convert::toIntNull($a_record['parent_id']);
        $this->action['action_name']    = Convert::toStrNull($a_record['action_name']);
        $this->action['action_desc']    = Convert::toStrNull($a_record['action_desc']);
        $this->action['action_type']    = Convert::toIntNull($a_record['action_type']);
        $this->action['action_status']  = Convert::toIntNull($a_record['action_status']);
        $this->action['order_position']  = Convert::toIntNull($a_record['order_position']);
        $this->action['due_on']         = Convert::toStrNull($a_record['due_on']);
        $this->action['created_on']     = Convert::toStrNull($a_record['created_on']);
        $this->action['created_by']     = Convert::toStrNull($a_record['created_by']);
        $this->action['updated_on']     = Convert::toStrNull($a_record['updated_on']);
        $this->action['updated_by']     = Convert::toStrNull($a_record['updated_by']);
        if (array_key_exists('users', $a_record)) {
            $this->action['users']          = Convert::toArrayNull($a_record['users']);
        }
    }

    public function addChild(self $action): void
    {
        $this->children[] = $action;
    }

    public function getId() : int
    {
        return $this->action['id'];
    }

    public function setId(int $id) : void
    {
        $this->action['id'] = $id;
    }

    public function getChannelId() : int
    {
        return $this->action['channel_id'];
    }

    public function setChannelId(int $id) : void
    {
        $this->action['channel_id'] = $id;
    }

    public function getName() : string
    {
        return $this->action['action_name'];
    }

    public function setName(string $value) : void
    {
        $this->action['action_name'] = $value;
    }

    public function getDesc() : ?string
    {
        return $this->action['action_desc'];
    }

    public function setDesc(?string $value) : void
    {
        $this->action['action_desc'] = $value;
    }

    public function getActionType() : int
    {
        return $this->action['action_type'];
    }

    public function getActionTypeDesc(): string
    {
        switch ($this->action['action_type']) {
            case static::ACTION_TYPE_REMINDER:
                return 'reminder';
            case static::ACTION_TYPE_REVIEW:
                return 'review action';
            case static::ACTION_TYPE_UPDATE:
                return 'update action';
            case static::ACTION_TYPE_SIGN:
                return 'sign action';
            default:
                return 'action';
        }
    }

    public function setActionType(int $value) : void
    {
        $this->action['action_type'] = $value;
    }

    public function getActionStatus() : int
    {
        return $this->action['action_status'];
    }

    public function setActionStatus(int $value) : void
    {
        $this->action['action_status'] = $value;
    }

    public function getDueOn() : ?string
    {
        return $this->action['due_on'];
    }

    public function setDueOn(?string $value) : void
    {
        $this->action['due_on'] =  (empty($value) ? null : date('Y-m-d H:i:s', strtotime($value)));
    }

    public function getCreatedOn() : string
    {
        return $this->action['created_on'];
    }

    public function setCreatedOn(string $value)  : void
    {
        $this->action['created_on'] = $value;
    }

    public function getCreatedBy() : string
    {
        return $this->action['created_by'];
    }

    public function setCreatedBy(string $value) : void
    {
        $this->action['created_by'] = $value;
    }

    public function getUpdatedOn() : ?string
    {
        return $this->action['updated_on'];
    }

    public function setUpdatedOn(?string $value) : void
    {
        $this->action['updated_on'] = (empty($value) ? null : date('Y-m-d H:i:s', strtotime($value)));
    }

    public function getUpdatedBy() : ?string
    {
        return $this->action['updated_by'];
    }

    public function setUpdatedBy(?string $value) : void
    {
        $this->action['updated_by'] = $value;
    }

    public function getUsers() : array
    {
        return empty($this->action['users']) ? [] : $this->action['users'];
    }

    public function setParentId(?int $parent): void
    {
        $this->action['parent_id'] = $parent;
    }

    public function getParentId() : ?int
    {
        return isset($this->action['parent_id']) ? (int) $this->action['parent_id'] : null;
    }

    public function getOrderPosition(): int
    {
        return (int) $this->action['order_position'];
    }

    public function getArray() : array
    {
        return $this->action;
    }

    public function jsonSerialize() : array
    {
        return $this->action;
    }

    public function getUserObjects(): ?array
    {
        //$log = ContainerFacade::get(LoggerInterface::class);
        //$log->info(print_r($this->action, true));
        if (!empty($this->action['users'])) {
            $dc = ContainerFacade::get(DataController::class);
            $nusers = [];
            $ar = json_decode(Json::encode($this->action['users']), true);
            foreach ($ar as $userRec) {
                if (!empty($userRec['user_id'])) {
                    $user = $dc->getUserById($userRec['user_id']);
                    $nusers[] = $user;
                }
            }
            return $nusers;
        }
        return NULL;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

};
