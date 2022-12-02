<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class AccountsAgeReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'Accounts Age';
    }

    public static function description(): string
    {
        return "Average, Range and Std. Dev of age of engaged user (in number of days). Age = Now() - Account Created() (not codelathe.com addresses etc)";
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT 
                CAST(AVG(DATEDIFF(NOW(), u.created_on)) AS UNSIGNED) AS average_account_age, -- average
                DATEDIFF(NOW(), MAX(u.created_on)) AS min_account_age, -- range(min)
                DATEDIFF(NOW(), MIN(u.created_on)) AS max_account_age, -- range(max)
                CAST(STDDEV(DATEDIFF(NOW(), u.created_on)) AS UNSIGNED) AS std_dev -- Std. dev
            FROM asclouddb.users u
            WHERE (u.email NOT LIKE '%@codelathe.com' AND u.email NOT LIKE '%@airsend.io') -- ignoring users from @codelathe.com
            AND u.created_on < DATE_SUB(NOW(), INTERVAL 2 WEEK) -- created more than 2 weeks ago (engaged)
            AND u.last_active_on > DATE_SUB(NOW(), INTERVAL 1 WEEK) -- active on the last week (engaged)
sql;
    }

    protected function countSql(): string
    {
        return <<<sql
            SELECT COUNT(*) as COUNT
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