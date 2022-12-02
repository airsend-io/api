<?php

namespace CodeLathe\Core\Data\Migrations;

use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;

class DBVersion10 extends AbstractMigration
{

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return "Create the public user";
    }

    /**
     * @inheritDoc
     */
    public function handle(DatabaseService $dbs): bool
    {

        // does the public user already exists on users table?
        $sql = "SELECT 1 FROM users WHERE email = 'public@airsend.io'";
        $adminPassword = password_hash('407gahsgadadoshuwe', PASSWORD_BCRYPT);
        if ($dbs->selectOne($sql) === null) {
            $sql = <<<SQL
                INSERT INTO users (id, email, password, display_name, user_role, account_status, approval_status, 
                                   trust_level, online_status, is_auto_pwd, is_terms_agreed, is_tour_complete)
                VALUES (91000003, 'public@airsend.io', '$adminPassword', 'Public User', 5, 100, 10, 0, 0, 1, 0, 0)
SQL;
            $dbs->executeStatement($sql);

        }
        return true;
    }
}