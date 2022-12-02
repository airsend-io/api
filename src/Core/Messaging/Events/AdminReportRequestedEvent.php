<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;


use CodeLathe\Service\Database\DatabaseService;

/**
 * This event is for tracking User login event
 */

class AdminReportRequestedEvent extends ASEvent
{
    public const NAME = 'admin.report.requested';

    /**
     * @var string
     */
    protected $reportSql;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $reportName;

    public function __construct(string $reportSql, string $email, string $reportName)
    {
        $this->reportSql = $reportSql;
        $this->email = $email;
        $this->reportName = $reportName;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName(): string
    {
        return self::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray(): array
    {
        return [
            'email' => $this->email,
            'reportName' => $this->reportName,
            'sql' => $this->reportSql
        ];
    }
}

