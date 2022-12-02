<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class FCMDevicesDataStore
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
     * @param string $clientApp
     * @throws InvalidArgumentException
     */
    public function connectDevice(string $deviceId, int $userId, string $clientApp): void
    {

        // ensure critical section (avoid deadlocks and concurrency problems)
        $sessionId = microtime(true);
        if ($this->criticalSection->acquireSection("FIREBASE_UPDATE", $sessionId, 5, 10)) {

            // first check if the device exists
            $sql = <<<sql
                SELECT 1
                FROM fcm_devices
                WHERE device_id = :device_id
sql;

            // depending on the result, update or insert the device
            if ($this->dbs->selectOne($sql, ['device_id' => $deviceId]) !== null) {
                $sql = <<<sql
                    UPDATE fcm_devices
                    SET user_id = :user_id, client_app = :client_app
                    WHERE device_id = :device_id;
sql;
            } else {
                $sql = <<<sql
                    INSERT INTO fcm_devices(device_id, user_id, client_app)
                    VALUES (:device_id, :user_id, :client_app);
sql;
            }

            // execute the statement
            $this->dbs->executeStatement($sql, ['device_id' => $deviceId, 'user_id' => $userId, 'client_app' => $clientApp]);

            // Release the critical section lock
            $this->criticalSection->releaseSection("FIREBASE_UPDATE", $sessionId);

            return;

        }

        throw new \Exception('Impossible to get lock on firebase registry.');

    }

    /**
     * @param string $deviceId
     */
    public function disconnectDevice(string $deviceId): void
    {
        $sql = <<<sql
            DELETE FROM fcm_devices
            WHERE device_id = :device_id
sql;
        $this->dbs->executeStatement($sql, ['device_id' => $deviceId]);
    }

    /**
     * Gets the devices ids and client apps linked to the given user
     *
     * @param int $userId
     * @param string|null $clientApp
     * @return array
     */
    public function findDevicesForUser(int $userId, ?string $clientApp = null): array
    {
        $sql = <<<sql
            SELECT device_id, client_app
            FROM fcm_devices
            WHERE user_id = :user_id
sql;

        $bindings = ['user_id' => $userId];

        if ($clientApp !== null) {
            $sql .= " AND client_app = :client_app";
            $bindings['client_app'] = $clientApp;
        }

        return $this->dbs->select($sql, $bindings) ?? [];
    }


}
