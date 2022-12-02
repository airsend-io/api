<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\UserSession;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;

class UserSessionDataStore
{
    /**
     * Declare database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    /**
     * UserSession DataStore constructor.
     *
     * @param ContainerInterface $container     *
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
    }

    /**
     * Query statement to create user session record
     *
     * @param UserSession $session
     * @return bool
     */
    public function createUserSession(UserSession $session) : bool
    {
        $sql = "INSERT INTO user_sessions
              SET
                user_id                   = :user_id,                
                issuer                    = :issuer,
                token                     = :token,
                ip                        = :ip,
                user_agent                = :user_agent,                    
                expiry                    = :expiry,                
                created_on                = :created_on;";
        $count = $this->dbs->insert($sql, $session->getArray());
        $session->setID((int)$this->dbs->lastInsertId());
        return $count == 1;
    }

    /**
     * Query to get User Session Object from $id
     *
     * @param $userId
     * @return array
     */
    public function getUserSessions(int $userId) : ?array
    {
        $sql = "SELECT * FROM user_sessions where user_id = :user_id";
        return $this->dbs->select($sql, ['user_id' => $userId]);
    }

    /**
     * Query to delete user session records
     *
     * @param $userId
     * @return int
     */
    public function deleteUserSessions(int $userId) : int
    {
        $sql = "DELETE FROM user_sessions where user_id = :user_id";
        return $this->dbs->delete($sql, ['user_id' => $userId]);
    }

}

