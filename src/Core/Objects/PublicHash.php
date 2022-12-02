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

class PublicHash implements \JsonSerializable, ObjectInterface
{

    protected $publicHash;

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

    public static function withDBData(array $row): ?self
    {
        if (is_array($row)) {
            $instance = new self();
            $instance->loadWithDBData($row);
            return $instance;
        } else {
            return null;
        }
    }

    public function loadWithDBData(array $row): void
    {
        $this->publicHash['id'] = Convert::toIntNull($row['id']);
        $this->publicHash['public_hash'] = Convert::toStr($row['public_hash']);
        $this->publicHash['resource_type'] = Convert::toStr($row['resource_type']);
        $this->publicHash['resource_id'] = Convert::toStr($row['resource_id']);
        $this->publicHash['created_on'] = Convert::toStr($row['created_on']);
    }

    public function getId() : int
    {
        return $this->publicHash['id'];
    }

    public function getPublicHash(): string
    {
        return $this->publicHash['public_hash'];
    }

    public function getResourceType(): string
    {
        return $this->publicHash['resource_type'];
    }

    public function getResourceId(): string
    {
        return $this->publicHash['resource_id'];
    }

    public function getArray() : array
    {
        return $this->publicHash;
    }

    public function jsonSerialize() : array
    {
        return $this->publicHash;
    }

}
