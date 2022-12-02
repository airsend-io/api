<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class CubesDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    private $logger;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
    }

    public function get(string $cube): array
    {
        return $this->$cube();
    }

    protected function addRowsToCube(array $rows, array &$cube)
    {
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $cube[$id] = array_merge($cube[$id] ?? [], $row);
        }
    }

    protected function engaged_users(): array
    {
        try {
            $cube = [];

            $sql = <<<sql
                SELECT 
                    u.id,
                    u.last_active_on,
                    datediff(now(), u.last_active_on) as last_active_days,
                    count(*) as channels,
                    count(case when cu.created_by = u.id then 1 else null end) as channels_owned,
                    count(case when c.last_active_on > date_sub(now(), interval 2 week) then 1 else null end) as active_channels
                    
                FROM users u
                LEFT JOIN channel_users cu ON cu.user_id = u.id
                LEFT JOIN channels c ON c.id = cu.channel_id
                WHERE /*u.email not like '%@codelathe.com' -- ignore internal users
                AND */u.email not like '%airsend.io' -- ignore internal users
                AND u.created_on < date_sub(now(), interval 1 week) -- users created more than 1 week ago
                AND u.last_active_on > date_sub(now(), interval 2 week) -- users active on the last 2 weeks
                GROUP BY u.id
sql;
            $this->addRowsToCube($this->dbs->select($sql) ?? [], $cube);

            $sql = <<<sql
                SELECT 
                    u.id,
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN m.created_on > CURRENT_DATE() THEN 1 ELSE NULL END) as messages_today,
                    COUNT(CASE WHEN m.created_on > CURRENT_DATE() THEN 2 ELSE NULL END) as messages_day_before,
                    COUNT(CASE WHEN m.created_on > DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)  THEN 1 ELSE NULL END) as messages_since_yesterday,
                    COUNT(CASE WHEN m.created_on > DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY)  THEN 1 ELSE NULL END) as messages_since_day_before,
                    COUNT(CASE WHEN m.created_on > DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY)  THEN 1 ELSE NULL END) as messages_last5days,
                    COUNT(CASE WHEN m.created_on > DATE_SUB(NOW(), INTERVAL 2 WEEK) THEN 1 ELSE NULL END) as messages_last2weeks,
                    COUNT(CASE WHEN WEEK(m.created_on) = WEEK(NOW()) THEN 1 ELSE NULL END) as messages_this_week,
                    COUNT(m.attachments) as messages_with_attachments
                FROM users u
                INNER JOIN messages m ON m.user_id = u.id
                WHERE /*u.email not like '%@codelathe.com' -- ignore internal users
                AND */u.email not like '%airsend.io' -- ignore internal users
                AND u.created_on < date_sub(now(), interval 1 week) -- users created more than 1 week ago
                GROUP BY u.id
sql;
            $this->addRowsToCube($this->dbs->select($sql) ?? [], $cube);

            return $cube;

        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}