<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class IOSDevicesDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    protected $dbs;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CriticalSection
     */
    protected $criticalSection;

    /**
     * Asset DataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     * @param CriticalSection $criticalSection
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger, CriticalSection $criticalSection)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->criticalSection = $criticalSection;
    }

    /**
     * Connects the deviceId to the given user.
     * @param string $deviceId
     * @param int $userId
     * @throws InvalidArgumentException
     */
    public function connectDevice(string $deviceId, int $userId): void
    {

        // ensure critical section (avoid deadlocks and concurrency problems)
        $sessionId = microtime(true);
        if ($this->criticalSection->acquireSection("IOS_DEVICE_UPDATE", $sessionId, 5, 10)) {

            // first check if the device exists
            $sql = <<<sql
                SELECT 1
                FROM ios_devices
                WHERE device_push_token = :device_push_token
sql;

            // depending on the result, update or insert the device
            if ($this->dbs->selectOne($sql, ['device_push_token' => $deviceId]) !== null) {
                $sql = <<<sql
                    UPDATE ios_devices
                    SET user_id = :user_id
                    WHERE device_push_token = :device_push_token;
sql;
            } else {
                $sql = <<<sql
                    INSERT INTO ios_devices(device_push_token, user_id)
                    VALUES (:device_push_token, :user_id);
sql;
            }

            // execute the statement
            $this->dbs->executeStatement($sql, ['device_push_token' => $deviceId, 'user_id' => $userId]);

            // Release the critical section lock
            $this->criticalSection->releaseSection("IOS_DEVICE_UPDATE", $sessionId);

            return;

        }

        throw new \Exception('Impossible to get lock on iOS devices registry.');

    }

    /**
     * @param string $deviceId
     */
    public function disconnectDevice(string $deviceId): void
    {
        $sql = <<<sql
            DELETE FROM ios_devices
            WHERE device_push_token = :device_push_token
sql;
        $this->dbs->executeStatement($sql, ['device_push_token' => $deviceId]);
    }

    /**
     * Gets the devices ids and client apps linked to the given user
     *
     * @param int $userId
     * @return string[]
     */
    public function findDevicesForUser(int $userId): array
    {
        $sql = <<<sql
            SELECT device_push_token
            FROM ios_devices
            WHERE user_id = :user_id
sql;

        $bindings = ['user_id' => $userId];

        $rows = $this->dbs->select($sql, $bindings) ?? [];

        return array_map(function ($row) {
            return $row['device_push_token'];
        }, $rows);
    }


}
