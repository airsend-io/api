<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

class ChannelWikiCountReport extends AbstractAdminReport
{

    public static function name(): string
    {
        return 'Channels with wiki pages count';
    }

    public static function description(): string
    {
        return "List of Channels sorted by number of total wiki pages descending ignoring any created with @codelathe.com address";
    }

    protected function mainSql(): string
    {
        return <<<sql
            SELECT c.id, c.channel_name, o.email AS owner_email, c.created_on, count(i.id) AS wiki_pages_count
            FROM asclouddb.channels c
                INNER JOIN asclouddb.users o ON c.owned_by = o.id
                LEFT JOIN asclouddb.channel_paths cp 
                    ON cp.channel_id = c.id
                    AND cp.path_type = 2 -- only wiki file paths
                LEFT JOIN asstoragedb.items i 
                    ON i.parentpath LIKE CONCAT(SUBSTR(cp.path_value, 3), '%')
                    AND i.type = 'file' -- only files (no folders, no sidecars)
                    AND i.extension = 'md' -- only markdown files
                    AND i.versioneddate IS NULL -- ignore previous versions
            WHERE (o.email NOT LIKE '%@codelathe.com' AND o.email NOT LIKE '%@airsend.io') -- ignoring any created with @codelathe.com address
            GROUP BY c.id
            ORDER BY wiki_pages_count DESC -- sorted by number of total wiki pages descending
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
        return false;
    }
}