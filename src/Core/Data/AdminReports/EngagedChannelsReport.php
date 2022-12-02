<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class EngagedChannelsReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'Engaged Channels';
    }

    public static function description(): string
    {
        return "Count and list of engaged channels: Creation Date > 2 weeks ago, owner is not someone with @codelathe.com address and Recent message < 1 week ago";
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT c.id, c.channel_name, o.email AS owner_email, c.created_on, MAX(m.created_on) AS last_message_on
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
                INNER JOIN asclouddb.messages m ON m.channel_id = c.id
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io') -- owner is not someone with @codelathe.com address
            AND c.created_on < DATE_SUB(NOW(), INTERVAL 2 WEEK) -- Creation Date > 2 weeks ago
            GROUP BY c.id
            HAVING MAX(m.created_on) > DATE_SUB(NOW(), INTERVAL 1 WEEK) -- Recent message < 1 week ago
            ORDER BY last_message_on DESC
sql;
    }

    protected function countSql(): string
    {
        return <<<sql
            SELECT COUNT(DISTINCT c.id) AS count
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
                INNER JOIN asclouddb.messages m ON m.channel_id = c.id
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io')
            AND c.created_on < DATE_SUB(NOW(), INTERVAL 2 WEEK) -- Creation Date > 2 weeks ago
            AND m.created_on > DATE_SUB(NOW(), INTERVAL 1 WEEK) -- Recent message < 1 week ago
sql;
    }

    public static function allowPaginatedAccess(): bool
    {
        return true;
    }
}