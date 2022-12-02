<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion29 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Create the public hash and short url structure for public links.";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {
        // does public_hash table already exists on asclouddb?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'public_hashes';
sql;

        // if not, create it
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE public_hashes(
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    public_hash VARCHAR(32) NOT NULL UNIQUE,
                    resource_type VARCHAR(255) NOT NULL,
                    resource_id VARCHAR(255) NOT NULL,
                    created_on DATETIME DEFAULT CURRENT_TIMESTAMP
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // does resource_type and resource_id columns exists on the short_urls table?
        $sql = <<<sql
                   SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = '{$this->database}'
                   AND TABLE_NAME = 'short_urls'
                   AND COLUMN_NAME = 'resource_type';
sql;

        // if not, create them
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                   ALTER TABLE short_urls ADD COLUMN resource_type VARCHAR(255) DEFAULT NULL;
                   ALTER TABLE short_urls ADD COLUMN resource_id VARCHAR(255) DEFAULT NULL;
                   ALTER TABLE short_urls ADD CONSTRAINT uq_short_urls_resource_type_id UNIQUE(resource_type, resource_id);
sql;
            $dbs->executeStatement($sql);
        }

        // migrate the current public_hashes/urls from channels to the new tables
        $selectSql1 = <<<sql
            SELECT id, public_hash, public_url
            FROM channels
            WHERE public_hash IS NOT NULL OR public_url IS NOT NULL
sql;

        $selectSql2 = <<<sql
            SELECT 1 FROM public_hashes WHERE public_hash = :public_hash
sql;

        $selectSql3 = <<<sql
            SELECT 1 FROM short_urls WHERE hash = :hash AND resource_type IS NULL AND resource_id IS NULL;
sql;


        $insertSql = <<<sql
            INSERT INTO public_hashes (public_hash, resource_type, resource_id)
            VALUES (:public_hash, 'ChannelInvite', :id)
sql;

        $updateSql = <<<sql
            UPDATE short_urls 
            SET resource_type = 'ChannelInvite', resource_id = :id
            WHERE hash = :hash
sql;

        foreach ($dbs->cursor($selectSql1) as $row) {
            if ($dbs->selectOne($selectSql2, ['public_hash' => $row['public_hash']]) === null) {
                $dbs->insert($insertSql, ['public_hash' => $row['public_hash'], 'id' => $row['id']]);
            }

            if (!empty($row['public_url']) && preg_match('/\/u\/([^\/]+)$/', $row['public_url'], $matches)) {
                if ($dbs->selectOne($selectSql3, ['hash' => $matches[1]])) {
                    $dbs->update($updateSql, ['id' => $row['id'], 'hash' => $matches[1]]);
                }
            }
        }

        return true;
    }
}
