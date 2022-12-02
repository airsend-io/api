<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion25 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Add Oauth server tables to the database";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {


        // does oauth_clients table already exists on the database?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oauth_clients' AND TABLE_SCHEMA = '{$this->database}'; ";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE oauth_clients(
                    id VARCHAR(255),
                    owner_id BIGINT UNSIGNED NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    secret VARCHAR(255) NOT NULL,
                    redirect TEXT DEFAULT NULL,
                    grant_type VARCHAR(255) NOT NULL,
                    active BOOLEAN DEFAULT TRUE,
                    PRIMARY KEY(id)
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // does oauth_access_tokens already exists on the database?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oauth_access_tokens' AND TABLE_SCHEMA = '{$this->database}'; ";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE oauth_access_tokens(
                    id VARCHAR(255),
                    user_id BIGINT UNSIGNED DEFAULT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    scopes TEXT,
                    revoked BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    PRIMARY KEY(id)
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // does oauth_access_tokens already exists on the database?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oauth_auth_codes' AND TABLE_SCHEMA = '{$this->database}'; ";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE oauth_auth_codes(
                    id VARCHAR(255),
                    user_id BIGINT UNSIGNED NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    scopes TEXT,
                    revoked BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    PRIMARY KEY(id)
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // does oauth_clients table already exists on the database?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oauth_clients_channels' AND TABLE_SCHEMA = '{$this->database}'; ";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE oauth_clients_channels(
                    client_id VARCHAR(255),
                    channel_id BIGINT UNSIGNED NOT NULL,
                    PRIMARY KEY(client_id, channel_id)
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        // does oauth_refresh_tokens table already exists on the database?
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oauth_refresh_tokens' AND TABLE_SCHEMA = '{$this->database}'; ";

        if ($dbs->selectOne($sql) === null) {
            $sql = <<<sql
                CREATE TABLE oauth_refresh_tokens(
                    id VARCHAR(255),
                    access_token_id VARCHAR(255) NOT NULL,
                    revoked BOOLEAN DEFAULT FALSE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    PRIMARY KEY(id)
                )ENGINE=INNODB;
sql;
            $dbs->executeStatement($sql);
        }

        return true;
    }
}
