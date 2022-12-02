<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class UserDomainsReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'User Domains';
    }

    public static function description(): string
    {
        return "Count of unique email domains in engaged user list (like @hello.com)";
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT SUBSTR(u.email, LOCATE('@', u.email) + 1) AS domain, count(*) AS count
            FROM asclouddb.users u
            WHERE (u.email NOT LIKE '%@codelathe.com' AND u.email NOT LIKE '%@airsend.io') -- ignoring users from @codelathe.com
            AND u.created_on < DATE_SUB(NOW(), INTERVAL 2 WEEK) -- created more than 2 weeks ago (engaged)
            AND u.last_active_on > DATE_SUB(NOW(), INTERVAL 1 WEEK) -- active on the last week (engaged)
            GROUP BY SUBSTR(u.email, LOCATE('@', u.email) + 1)
            ORDER BY count DESC
sql;

    }

    protected function countSql(): string
    {
        return <<<sql
            SELECT COUNT(DISTINCT SUBSTR(u.email, LOCATE('@', u.email) + 1)) AS count
            FROM asclouddb.users u
            WHERE (u.email NOT LIKE '%@codelathe.com' AND u.email NOT LIKE '%@airsend.io') -- ignoring users from @codelathe.com
            AND u.created_on < DATE_SUB(NOW(), INTERVAL 2 WEEK) -- created more than 2 weeks ago (engaged)
            AND u.last_active_on > DATE_SUB(NOW(), INTERVAL 1 WEEK) -- active on the last week (engaged)
sql;
    }

    public static function allowPaginatedAccess(): bool
    {
        return true;
    }
}