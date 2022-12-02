<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class ChannelsAgeReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'Channels Age';
    }

    public static function description(): string
    {
        return "Average, Range and Std. Dev of age of engaged channels (in number of days) Age = Now() - Channel Created (see definition above)";
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT 
                CAST(AVG(DATEDIFF(NOW(), c.created_on)) AS UNSIGNED) AS average_channel_age, -- average
                DATEDIFF(NOW(), MAX(c.created_on)) AS min_chammel_age, -- range(min)
                DATEDIFF(NOW(), MIN(c.created_on)) AS max_channel_age, -- range(max)
                CAST(STDDEV(DATEDIFF(NOW(), c.created_on)) AS UNSIGNED) AS std_dev -- Std. dev
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
                INNER JOIN asclouddb.messages m ON m.channel_id = c.id
            WHERE (o.email NOT LIKE '%@codelathe.co' AND o.email NOT LIKE '%@airsend.io') -- owner is not someone with @codelathe.com address
            AND c.created_on < DATE_SUB(NOW(), INTERVAL 2 WEEK) -- Creation Date > 2 weeks ago
            AND m.created_on > DATE_SUB(NOW(), INTERVAL 1 WEEK) -- Recent message < 1 week ago
sql;
    }

    protected function countSql(): string
    {
        return <<<sql
            SELECT 1 AS count;
sql;
    }

    public static function allowPaginatedAccess(): bool
    {
        return true;
    }
}