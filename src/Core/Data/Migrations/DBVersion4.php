<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion4 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Include the email tag column (to generate message sending addresses) and generate random tags";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does email_tag column already exists?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'channel_users' AND TABLE_SCHEMA = '{$this->database}' AND COLUMN_NAME = 'email_tag'";
        if ($dbs->selectOne($sql) === null) {

            // don't exists, so add it
            $sql = "ALTER TABLE channel_users ADD email_tag VARCHAR(8) NULL AFTER notifications_config";
            $dbs->executeStatement($sql);

        }

        // now update the existent rows with a random/unique string (using chunks of 100 items to avoid memory problems)
        $offset = 0;
        do {

            // first get all existent channel_users rows (just the ids)
            $sql = "SELECT channel_id, user_id FROM channel_users LIMIT 100 OFFSET :offset";
            if (empty($result = $dbs->select($sql, ['offset' => $offset]))) {
                break; // if we're done, stop
            }

            // for each channel/user pair, generate an unique random string
            $tags = array_map(function($item) use ($dbs) {

                // try to generate random string until it becomes unique
                do {
                    $emailTag = StringUtility::generateRandomString(8);
                    $sql = "SELECT 1 FROM channel_users WHERE email_tag = :email_tag";
                    if ($dbs->selectOne($sql, ['email_tag' => $emailTag]) === null) {
                        break;
                    }
                } while(true);

                // return the result
                return [
                    'channel_id' => $item['channel_id'],
                    'user_id' => $item['user_id'],
                    'email_tag' => $emailTag,
                ];
            }, $result);

            // update the email_tag column for each channel_users row
            foreach ($tags as $tag) {

                $sql = 'UPDATE channel_users SET email_tag = :tag WHERE channel_id = :channel_id AND user_id = :user_id';
                $dbs->executeStatement($sql, ['tag' => $tag['email_tag'], 'user_id' => $tag['user_id'], 'channel_id' => $tag['channel_id']]);

            }

            $offset += 100;
        } while(true);

        // now that all rows are updated, make the column not null
        $sql = "ALTER TABLE channel_users MODIFY email_tag VARCHAR(8) NOT NULL";
        $dbs->executeStatement($sql);

        return true;
    }
}