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
use CodeLathe\Core\Utility\Utility;
use Psr\Container\ContainerInterface;

class Notification implements \JsonSerializable, ObjectInterface
{
    const NOTIFICATION_CONTEXT_TYPE_CHANNEL = 1;

    const NOTIFICATION_MEDIA_TYPE_EMAIL = 1;

    const NOTIFICATION_TYPE_MESSAGE_DIGEST = 1;
    const NOTIFICATION_TYPE_CHANNEL_INVITE= 2;

    protected $notification;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @param string|null $token
     * @param int $userId
     * @param int $contextType
     * @param int $contextId
     * @param int $mediaType
     * @param int $notificationType
     * @param string $data
     * @return Notification
     * @throws \Exception
     */
    public static function create(?string $token, int $userId, int $contextType, int $contextId, int $mediaType, int $notificationType, string $data)
    {
        if (empty($data)) {
            $data = '{}';
        }
        $instance = new self();
        $instance->notification['token'] = $token ?? Utility::uniqueToken();
        $instance->notification['user_id'] = $userId;
        $instance->notification['context_type'] = $contextType;
        $instance->notification['context_id'] = $contextId;
        $instance->notification['media_type'] = $mediaType;
        $instance->notification['created_on'] = date("Y-m-d H:i:s");
        $instance->notification['notification_type'] = $notificationType;
        $instance->notification['data'] = $data;
        return $instance;
    }

    public static function withDBData(array $record) : ?self
    {
        $record = Database::normalizeRecordForDataStore('notifications', $record);
        $instance = ContainerFacade::get(self::class);
        $instance->loadWithDBData($record);
        return $instance;
    }

    public function loadWithDBData(array $record) : void
    {
        $this->notification['id'] = Convert::toIntNull($record['id']);
        $this->notification['token'] = Convert::toStrNull($record['token']);
        $this->notification['user_id'] = Convert::toIntNull($record['user_id']);
        $this->notification['context_type'] = Convert::toIntNull($record['context_type']);
        $this->notification['context_id'] = Convert::toIntNull($record['context_id']);
        $this->notification['media_type'] = Convert::toIntNull($record['media_type']);
        $this->notification['created_on'] = Convert::toStrNull($record['created_on']);
        $this->notification['notification_type'] = Convert::toIntNull($record['notification_type']);
        $this->notification['data'] = Convert::toStrNull($record['data']);
    }

    public function setId(string $id): void
    {
        $this->notification['id'] = $id;
    }

    public function getId(): int
    {
        return (int) $this->notification['id'];
    }

    public function getUserId(): int
    {
        return $this->notification['user_id'];
    }

    public function getChannelId(): ?int
    {
        if ($this->notification['context_type'] === static::NOTIFICATION_CONTEXT_TYPE_CHANNEL) {
            return $this->notification['context_id'];
        }
        return null;
    }

    public function getToken(): string
    {
        return $this->notification['token'];
    }

    public function getContextType(): int
    {
        return $this->notification['context_type'];
    }

    public function getContextId(): int
    {
        return $this->notification['context_id'];
    }

    public function getMediaType(): int
    {
        return $this->notification['media_type'];
    }

    public function getNotificationType(): int
    {
        return $this->notification['notification_type'];
    }

    public function getData(): string
    {
        return $this->notification['data'];
    }

    public function getExpirationTime(): \DateTime
    {
        $config = ContainerFacade::get(ConfigRegistry::class);
        if (!isset($this->notification['expiration_time'])) {
            $ttl = $config->get('/auth/notifications/token/ttl');
            $expirationTime = \DateTime::createFromFormat('Y-m-d H:i:s', $this->notification['created_on']);
            $expirationTime->modify("+$ttl");
            $this->notification['expiration_time'] = $expirationTime;
        }
        return $this->notification['expiration_time'];
    }

    public function setChannel(Channel $channel): void
    {
        $this->channel = $channel;
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function getArray(): array
    {
        return $this->notification;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->getArray();
    }

};
