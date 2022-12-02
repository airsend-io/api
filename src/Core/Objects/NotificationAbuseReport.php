<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;

class NotificationAbuseReport implements \JsonSerializable, ObjectInterface
{

    protected $notificationAbuseReport;

    /**
     * @var Notification
     */
    protected $notification;

    /**
     * Create NotificationAbuseReport
     *
     * @param int $notificationId
     * @param string $reporterName
     * @param string $reporterEmail
     * @param string $reportText
     * @return NotificationAbuseReport
     */
    public static function create(int $notificationId, string $reporterName, string $reporterEmail, string $reportText): self
    {
        $instance = new self();
        $instance->notificationAbuseReport['notification_id'] = $notificationId;
        $instance->notificationAbuseReport['reporter_name'] = $reporterName;
        $instance->notificationAbuseReport['reporter_email'] = $reporterEmail;
        $instance->notificationAbuseReport['report_text'] = $reportText;
        $instance->notificationAbuseReport['created_on'] = null;
        return $instance;
    }

    public static function withDBData(array $record) : ?self
    {
        if(array_filter($record)){
            $instance = new self();
            $instance->loadWithDBData($record);
            return $instance;
        }
        else {
            return null;
        }
    }

    public function loadWithDBData(array $record) : void
    {
        $this->notificationAbuseReport['id'] = Convert::toIntNull($record['id']);
        $this->notificationAbuseReport['notification_id'] = Convert::toIntNull($record['notification_id']);
        $this->notificationAbuseReport['reporter_name'] = Convert::toStrNull($record['reporter_name']);
        $this->notificationAbuseReport['reporter_email'] = Convert::toStrNull($record['reporter_email']);
        $this->notificationAbuseReport['report_text'] = Convert::toStrNull($record['report_text']);
        $this->notificationAbuseReport['created_on'] = Convert::toStrNull($record['created_on']);
    }

    public function getId() : int
    {
        return $this->notificationAbuseReport['id'];
    }

    public function setId(int $id) : void
    {
        $this->notificationAbuseReport['id'] = $id;
    }

    public function getNotificationId() : int
    {
        return $this->notificationAbuseReport['notification_id'];
    }

    public function getReporterName()
    {
        return $this->notificationAbuseReport['reporter_name'];
    }

    public function getReporterEmail()
    {
        return $this->notificationAbuseReport['reporter_email'];
    }

    public function getReportText()
    {
        return $this->notificationAbuseReport['report_text'];
    }

    public function setNotification(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function getNotification(): ?Notification
    {
        return $this->notification;
    }

    public function getArray() : array
    {
        return $this->notificationAbuseReport;
    }

    public function jsonSerialize() : array
    {
        return $this->notificationAbuseReport;
    }

};
