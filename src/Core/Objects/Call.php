<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\Database;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use phpDocumentor\Reflection\Types\Integer;
use Ramsey\Uuid\Uuid;

class Call implements \JsonSerializable, ObjectInterface
{

    protected $call;

    /**
     * Channel constructor.
     *
     * @param int $userId
     * @param int $channelId
     * @param bool $isPublic
     * @param string $allowedUsers
     * @return Call
     */
    public static function create(int $userId, int $channelId = 0, bool $isPublic = false, string $allowedUsers = '') : Call
    {
        $instance = new self();
        $instance->call['user_id'] = $userId;
        $instance->call['channel_id'] = $channelId;
        $instance->call['is_public'] = $isPublic;
        $instance->call['allowed_users'] = $allowedUsers;
        $instance->call['server_address'] = ContainerFacade::get(ConfigRegistry::class)->get('/app/wrtc_server_address');
        $instance->call['call_hash'] = md5((string)Uuid::uuid4());

        return $instance;
    }

    public static function withDBData(array $record) : ?self
    {
        $instance = new self();
        $instance->loadWithDBData($record);
        return $instance;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->call = $a_record;
    }

    public function getId() : int
    {
        return (int)$this->call['id'];
    }

    public function setId(int $id): void
    {
        $this->call['id'] = $id;
    }

    public function getCreatorId(): int
    {
        return (int)$this->call['user_id'];
    }

    public function getChannelId(): int
    {
        return  (int)$this->call['channel_id'] ?? 0;
    }

    public function setChannelId(int $channelId): void
    {
        $this->call['channel_id'] = $channelId;
    }

    public function callHash(): string
    {
        return (string)$this->call['call_hash'];
    }

    public function isPublic(): bool
    {
        return (bool)$this->call['is_public'];
    }

    public function setIsPublic(bool $is_public):void
    {
        $this->call['is_public'] = $is_public;
        if ($is_public) {
            $this->call['allowed_users'] = '';
        }
    }

    public function allowedUsers(): string
    {
        return $this->call['allowed_users'];
    }

    public function setAllowedUsers(string $allowedUsers):void
    {
        $this->call['allowed_users'] = $allowedUsers;
    }


    public function serverAddress(): string
    {
        return (string)$this->call['server_address'];
    }

    public function getArray() : array
    {
        return $this->call;
    }

    public function jsonSerialize() : array
    {
        return $this->call;
    }

};
