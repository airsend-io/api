<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Objects\PublicHash;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class PublicHashDataStore
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
     * PublicHashDataStore constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    public function findPublicHash(string $publicHash): ?PublicHash
    {
        $sql = <<<sql
            SELECT *
            FROM public_hashes
            WHERE public_hash = :public_hash
sql;
        $row = $this->dbs->selectOne($sql, ['public_hash' => $publicHash]);

        return empty($row) ? null : PublicHash::withDBData($row);

    }

    /**
     * @param string $resourceType
     * @param string $resourceId
     * @return string|null
     */
    public function findPublicHashForResource(string $resourceType, string $resourceId): ?string
    {
        $sql = <<<sql
            SELECT public_hash
            FROM public_hashes
            WHERE resource_type = :resource_type
            AND resource_id = :resource_id
sql;
        $row = $this->dbs->selectOne($sql, ['resource_type' => $resourceType, 'resource_id' => $resourceId]);

        return empty($row) ? null : $row['public_hash'];
    }

    /**
     * @param string $resourceType
     * @param string $resourceId
     * @return string
     * @throws \Exception
     */
    public function createPublicHash(string $resourceType, string $resourceId): string
    {

        // generate a unique public hash
        $sql = <<<sql
            SELECT 1
            FROM public_hashes
            WHERE public_hash = :public_hash
sql;
        do {
            $hash = StringUtility::generateRandomString(32);
            $result = $this->dbs->selectOne($sql, ['public_hash' => $hash]);
        } while($result !== null);

        // insert the new hash
        $sql = <<<sql
            INSERT INTO public_hashes(public_hash, resource_type, resource_id)
            VALUES (:public_hash, :resource_type, :resource_id);
sql;
        $this->dbs->insert($sql, ['public_hash' => $hash, 'resource_type' => $resourceType, 'resource_id' => $resourceId]);

        return $hash;
    }

    /**
     * @param string $resourceType
     * @param string $resourceId
     */
    public function deletePublicHashByResource(string $resourceType, string $resourceId): void
    {
        $sql = <<<sql
            DELETE FROM public_hashes
            WHERE resource_type = :resource_type
            AND resource_id = :resource_id
sql;
        $this->dbs->delete($sql, ['resource_type' => $resourceType, 'resource_id' => $resourceId]);

    }

    public function getByResource(string $resourceType, string $resourceId): ?string
    {
        $sql = "SELECT public_hash FROM public_hashes WHERE resource_type = :resource_type AND resource_id = :resource_id";
        $result = $this->dbs->selectOne($sql, ['resource_type' => $resourceType, 'resource_id' => $resourceId]);
        return $result['public_hash'] ?? null;
    }


}