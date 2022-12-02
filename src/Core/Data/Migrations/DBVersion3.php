<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion3 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Replace the current channel email with a slug based on channel name";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        /** @var SlugifyInterface $slugifier */
        $slugifier = $this->container->get(SlugifyInterface::class);

        // use chunks (100 items per chunk) to avoid memory problems
        $offset = 0;
        do {

            // first get all existent channel names
            $sql = "select id, channel_name, channel_email from channels limit 100 offset :offset";
            if (empty($result = $dbs->select($sql, ['offset' => $offset]))) {
                break; // if we're done, stop
            }

            // slugify each name
            $slugs = array_map(function($item) use ($slugifier) {
                return [
                    'id' => $item['id'],
                    'email' => $slugifier->slugify($item['channel_name']),
                ];
            }, $result);

            // update the channel_email column for each channel, replacing the current value with the slug
            foreach ($slugs as $slug) {

                // first ensure that the slug still don't exist (must be unique) and suffix it incrementally
                $suffix = 0;
                do {
                    $email = $slug['email'] . ($suffix ?: '');
                    $sql = 'select 1 from channels where channel_email = :email and id <> :id';
                    if ($dbs->selectOne($sql, ['email' => $email, 'id' => $slug['id']]) === null) {
                        break;
                    }
                    $suffix++;
                } while(true);

                // finally update the email on the channel
                $sql = 'update channels set channel_email = :email where id = :id';
                $dbs->executeStatement($sql, ['id' => $slug['id'], 'email' => $email]);
            }

            $offset += 100;
        } while(true);

        return true;
    }
}