<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;

class DBVersion38 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Ensure a created history for every action (legacy actions)";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does message_attachments table exists?
        $selectSql = <<<sql
                    SELECT a.id, a.action_name, a.created_by, a.created_on
                    FROM actions a
                    WHERE a.id NOT IN (
                       SELECT action_id
                       FROM action_history ah
                       WHERE history_type = 'created'
                    )
                    LIMIT :limit OFFSET :offset;
sql;

        $insertSql = <<<sql
                    INSERT INTO action_history(action_id, user_id, attachments, history_type, created_on)
                    VALUES (:action_id, :user_id, :attachments, 'created', :created_on);
sql;


        $chunkSize = 50;
        $offset = 0;

        while (true) {
            $rows = $dbs->select($selectSql, ['limit' => $chunkSize, 'offset' => $offset]);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $attachments = [
                    'action_name' => $row['action_name'],
                ];
                $dbs->executeStatement($insertSql, [
                    'action_id' => $row['id'],
                    'attachments' => \json_encode($attachments),
                    'user_id' => $row['created_by'],
                    'created_on' => $row['created_on'],
                ]);

            }

            $offset += $chunkSize;
        }

        return true;
    }

}
