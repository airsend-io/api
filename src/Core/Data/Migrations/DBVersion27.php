<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion27 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add read_watermark_id column to channel_users table.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        $config = ContainerFacade::get(ConfigRegistry::class);
        // read watermark fix

        // set all read_watermark_id value to the latest timeline read date for user/channel
        // or for the greatest self message or for the greatest bot message, which is bigger...
        $sql = <<<sql
            UPDATE channel_users cu
            LEFT JOIN (
                SELECT user_id, channel_id, max(message_id) as timeline_watermark
                FROM timelines
                WHERE activity = 10
                GROUP BY user_id, channel_id
            ) t ON t.user_id = cu.user_id AND t.channel_id = cu.channel_id
            LEFT JOIN (
                SELECT user_id, channel_id, max(id) as self_watermark
                FROM messages
                GROUP BY user_id, channel_id
            ) m ON m.user_id = cu.user_id AND m.channel_id = cu.channel_id
            LEFT JOIN (
                SELECT user_id, channel_id, max(id) as bot_watermark
                FROM messages
                GROUP BY user_id, channel_id
            ) b ON m.user_id = :bot_id AND m.channel_id = cu.channel_id
            SET cu.read_watermark_id = greatest(coalesce(t.timeline_watermark, 0), coalesce(m.self_watermark, 0), coalesce(b.bot_watermark, 0), coalesce(cu.read_watermark_id, 0))
            WHERE greatest(coalesce(t.timeline_watermark, 0), coalesce(m.self_watermark, 0), coalesce(b.bot_watermark, 0), coalesce(cu.read_watermark_id, 0)) > 0;
sql;
        $dbs->executeStatement($sql, ['bot_id' => $config->get('/app/airsend_bot_id')]);

        return true;
    }
}
