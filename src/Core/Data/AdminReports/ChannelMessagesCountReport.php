<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class ChannelMessagesCountReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'Channels with messages count';
    }

    public static function description(): string
    {
        return "List of Channels sorted by number of messages descending ignoring any created with @codelathe.com address";
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT c.id, c.channel_name, o.email AS owner_email, c.created_on, count(m.id) AS messages_sent
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
                INNER JOIN asclouddb.messages m ON m.channel_id = c.id
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io') -- ignoring any created with @codelathe.com address
            GROUP BY c.id
            ORDER BY messages_sent DESC -- sorted by number of messages descending
sql;
    }

    protected function countSql(): string
    {
        return <<<sql
            SELECT COUNT(DISTINCT c.id) AS count
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io')
sql;
    }

    public static function allowPaginatedAccess(): bool
    {
        return true;
    }
}