<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Objects\NotificationAbuseReport;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Service\Database\DatabaseService;
use phpDocumentor\Reflection\Types\Iterable_;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class NotificationAbuseReportDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    private $logger;

    /**
     * UserDataStore constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * @param NotificationAbuseReport $report
     * @return bool
     * @throws DatabaseException
     */
    public function createReport(NotificationAbuseReport $report): bool
    {
        try {
            $sql = <<<SQL
                INSERT INTO notification_abuse_reports
                SET
                    notification_id = :notification_id,
                    reporter_name = :reporter_name,
                    reporter_email = :reporter_email,
                    report_text = :report_text;
SQL;

            $count = $this->dbs->insert($sql, [
                ':notification_id' => $report->getNotificationId(),
                ':reporter_name' => $report->getReporterName(),
                ':reporter_email' => $report->getReporterEmail(),
                ':report_text' => $report->getReportText(),
            ]);
            $report->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    public function getAllReports(int $start, int $limit): \Generator
    {
        $sql = <<<SQL
            SELECT 
                   nar.*,
                   n.id as "notifications.id",
                   n.token as "notifications.token",
                   n.user_id as "notifications.user_id",
                   n.context_type as "notifications.context_type",
                   n.context_id as "notifications.context_id",
                   n.media_type as "notifications.media_type",
                   n.created_on as "notifications.created_on",
                   n.notification_type as "notifications.notification_type",
                   n.data as "notifications.data",
                   c.id as "channels.id",
                   c.team_id as "channels.team_id",
                   c.channel_name as "channels.channel_name",
                   c.channel_email as "channels.channel_email",
                   c.channel_status as "channels.channel_status",
                   c.is_auto_closed as "channels.is_auto_closed",
                   c.close_after_days as "channels.close_after_days",
                   c.last_active_on as "channels.last_active_on",
                   c.has_logo as "channels.has_logo",
                   c.has_background as "channels.has_background",
                   c.created_on as "channels.created_on",
                   c.owned_by as "channels.owned_by",
                   c.updated_on as "channels.updated_on",
                   c.updated_by as "channels.updated_by"
                   
            FROM notification_abuse_reports nar
                INNER JOIN notifications n ON n.id = nar.notification_id
                INNER JOIN channels c ON c.id = n.context_id
            LIMIT :start, :limit
SQL;

        $generator = $this->dbs->cursor($sql, [
            'start' => $start,
            'limit' => $limit,
        ]);
        while (($row = $generator->current()) !== null) {
            $report = NotificationAbuseReport::withDBData($row);
            $notification = Notification::withDBData($row);
            $channel = Channel::withDBData($row);
            $notification->setChannel($channel);
            $report->setNotification($notification);
            yield $report;
            $generator->next();
        }

    }

    public function deleteReport(int $id): int
    {
        $sql = 'DELETE FROM notification_abuse_reports WHERE id = :id';
        return $this->dbs->delete($sql, ['id' => $id]);
    }

}
