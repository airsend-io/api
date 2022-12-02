<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion33 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Replace deprecated ChannelInvite links with the new format.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // find deprecated ChannelInvite urls
        $sql = <<<sql
                    SELECT hash, url
                    FROM short_urls
                    WHERE resource_type = 'ChannelInvite'
                    AND url REGEXP '^/channel/[0-9]+/pub-[a-z0-9]+';
sql;

        $rows = $dbs->select($sql);

        // no records found, just skip it
        if (empty($rows)) {
            return true;
        }

        foreach ($rows as $row) {
            if (!preg_match('/^(\/channel\/[0-9]+)\/(pub-[a-z0-9]+)/', $row['url'], $matches)) {
                continue;
            }
            $baseUrl = $matches[1];
            $publicHash = $matches[2];
            $newUrl = "$baseUrl/invite?hash=$publicHash";

            $sql = "UPDATE short_urls SET url = :url WHERE hash = :hash";
            $dbs->executeStatement($sql, ['url' => $newUrl, 'hash' => $row['hash']]);

        }

        return true;
    }
}
