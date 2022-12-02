<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class ChannelFilesCountReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'Channels with files count';
    }

    public static function description(): string
    {
        return 'List of Channels sorted by number of total files descending ignoring any created with @codelathe.com address';
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT c.id, c.channel_name, o.email AS owner_email, c.created_on, count(i.id) AS files_count
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
                LEFT JOIN asclouddb.channel_paths cp 
                    ON cp.channel_id = c.id
                    AND cp.path_type = 1 -- only regular file paths (no wiki, no deleted)
                LEFT JOIN asstoragedb.items i 
                    ON i.parentpath LIKE CONCAT(SUBSTR(cp.path_value, 3), '%')
                    AND i.type = 'file' -- only files (no folders, no sidecars)
                    AND i.versioneddate IS NULL -- ignore previous versions
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io') -- ignoring any created with @codelathe.com address
            GROUP BY c.id
            ORDER BY files_count DESC -- sorted by number of total files descending
sql;
    }

    protected function countSql(): string
    {
        return <<<sql
            SELECT COUNT(DISTINCT c.id) AS COUNT
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io')
sql;
    }

    public static function allowPaginatedAccess(): bool
    {
        return false;
    }
}