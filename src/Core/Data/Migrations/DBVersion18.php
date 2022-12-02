<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion18 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Fix encoding on bot messages";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // get all bot messages (by chunks of 100)
        $chunkSize = 2;
        $offset = 0;
        while (true) {

            // build sql statement
            $sql = "SELECT * FROM messages WHERE message_type = 5 LIMIT $chunkSize OFFSET $offset";

            // increment the offset
            $offset += $chunkSize;

            // check if the result is empty (done)
            $rows = $dbs->select($sql);
            if (empty($rows)) {
                break;
            }

            // pre-generate the update sql
            $updateSql = "UPDATE messages SET content_text = :content_text WHERE id = :id";

            // go row by row
            foreach ($rows as $row) {

                // save the id for later update
                $id = $row['id'];


                // get the content object
                $content = json_decode($row['content_text'], true);

                // check if there is a path on the message text (skip if don't)
                $regex = '/\(path:\/\/([^)]+)\)/';
                if (preg_match($regex, $content['bot_message'], $m)) {

                    // url_decode the path
                    $content['bot_message'] = preg_replace_callback($regex, function ($matches) {
                        $path = urldecode($matches[1]);
                        return "(path://$path)";
                    }, $content['bot_message']);

                    // convert new content to json
                    $newContent = \GuzzleHttp\json_encode($content);

                    // update the record
                    $dbs->executeStatement($updateSql, ['id' => $id, 'content_text' => $newContent]);

                }
            }
        }

        return true;
    }
}