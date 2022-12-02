<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Objects\Notification;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\ServiceRegistryInterface;

class NotificationDataStore
{

    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    /**
     * @var ServiceRegistryInterface
     */
    protected $registry;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param ServiceRegistryInterface $registry
     */
    public function __construct(DatabaseService $dbs, ServiceRegistryInterface $registry)
    {
        $this->registry = $registry;
        $this->dbs = $dbs;
    }

    /**
     * @param string $token
     * @return Notification|null
     */
    public function getNotificationByToken(string $token): ?Notification
    {

        $sql = <<<SQL
            SELECT *
            FROM notifications
            WHERE context_type = :context_type
                AND token = :token;
SQL;

        $result = $this->dbs->selectOne($sql, [
            ':context_type' => Notification::NOTIFICATION_CONTEXT_TYPE_CHANNEL,
            ':token' => $token,
        ]);

        if ($result === null) {
            return null;
        }

        return Notification::withDBData($result);
    }

    /**
     * @param Notification $notification
     * @param int[] $messageIds
     * @return Notification|null
     */
    public function createNotification(Notification $notification, ?array $messageIds = []): ?Notification
    {

        // TODO and/or discuss - Put everything inside a transaction...
        // first save the notification
        $sql = <<<SQL
            INSERT INTO notifications
            SET                
                token = :token, 
                user_id = :user_id, 
                context_type = :context_type,
                context_id = :context_id,
                media_type = :media_type,
                notification_type = :notification_type,
                data = :data
SQL;

        $data = $notification->getData();
        $data = empty($data) ? '{}' : $data;

        $count = $this->dbs->insert($sql, [
            ':token' => $notification->getToken(),
            ':user_id' => $notification->getUserId(),
            ':context_type' => $notification->getContextType(),
            ':context_id' => $notification->getContextId(),
            ':media_type' => $notification->getMediaType(),
            ':notification_type' => $notification->getNotificationType(),
            ':data' => $data,
        ]);

        // stop if the record was not saved
        if ($count !== 1) {
            return null;
        }

        $notification->setId($this->dbs->lastInsertId());

        // then save the timeline linking the notification to the messages if there is messages
        if (!empty ($messageIds)) {
            // ... map the messages to the SQL (lists separated by ,)
            $messagesSql = array_map(function (int $messageId) use ($notification) {
                return "({$notification->getId()}, $messageId)";
            }, array_unique($messageIds));
            $messagesSql = implode(',', $messagesSql);

            $sql = <<<SQL
            INSERT INTO notifications_timeline (notification_id, message_id)
            VALUES {$messagesSql}
SQL;
            $this->dbs->insert($sql);
        }

        return $notification;

    }

    /**
     * @param int $interval Lookback interval in minutes
     * @return int
     */
    public function getEmailsCount(int $interval): int
    {
        $sql = <<<SQL
            SELECT COUNT(*) as count
            FROM notifications 
            WHERE notification_type = :notificationType
                AND media_type = :mediaType
                AND DATE_SUB(current_timestamp, INTERVAL :interval MINUTE) < created_on;
SQL;
        $result = $this->dbs->selectOne($sql, [
            'notificationType' => Notification::NOTIFICATION_MEDIA_TYPE_EMAIL,
            'mediaType' => Notification::NOTIFICATION_MEDIA_TYPE_EMAIL,
            'interval' => $interval,
        ]);
        return (int) $result['count'];
    }

    public function findInvite(int $channelId, int $userId)
    {
        $sql = <<<SQL
            SELECT *
            FROM notifications
            WHERE context_type = :context_type
                AND context_id = :channel_id
                AND notification_type = :notification_type
                AND user_id = :user_id;
SQL;

        $result = $this->dbs->selectOne($sql, [
            ':context_type' => Notification::NOTIFICATION_CONTEXT_TYPE_CHANNEL,
            ':channel_id' => $channelId,
            ':notification_type' => Notification::NOTIFICATION_TYPE_CHANNEL_INVITE,
            ':user_id' => $userId,
        ]);

        if ($result === null) {
            return null;
        }

        return Notification::withDBData($result);
    }

}