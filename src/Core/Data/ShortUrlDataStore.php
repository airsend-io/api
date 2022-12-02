<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Objects\ExternalIdentity;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Core\Exception\DatabaseException;

class ShortUrlDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    protected $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * UserDataStore constructor.
     *
     * @param DatabaseService $dbs
     * @param LoggerInterface $logger
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(DatabaseService $dbs, LoggerInterface $logger, CacheItemPoolInterface $cache)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * @param string $url
     * @param string|null $hash
     * @param string|null $resourceType
     * @param string|null $resourceId
     */
    public function create(string $url, ?string $hash = null, ?string $resourceType = null, ?string $resourceId = null): void
    {

        if ($hash === null) {
            $hash = $this->generateUniqueHash();
        }

        $sql = <<<sql
            INSERT INTO short_urls (hash, url, resource_type, resource_id) 
            VALUES (:hash, :url, :resource_type, :resource_id);
sql;

        $this->dbs->executeStatement($sql, [
            'hash' => $hash,
            'url' => $url,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId
        ]);
    }

    public function delete(string $hash): void
    {
        $cacheKey = "airsend.shorturl.$hash";
        if ($this->cache->hasItem($cacheKey)) {
            $this->cache->deleteItem($cacheKey);
        }
        $sql = 'DELETE FROM short_urls WHERE hash = :hash;';
        $this->dbs->executeStatement($sql, ['hash' => $hash]);
    }

    /**
     * @param string $hash
     * @return string|null
     */
    public function translate(string $hash): ?string
    {
        $sql = "SELECT url FROM short_urls WHERE hash = :hash";
        $result = $this->dbs->selectOne($sql, ['hash' => $hash]);
        return $result['url'] ?? null;
    }

    public function getByResource(string $resourceType, string $resourceId): ?string
    {
        $sql = "SELECT hash FROM short_urls WHERE resource_type = :resource_type AND resource_id = :resource_id";
        $result = $this->dbs->selectOne($sql, ['resource_type' => $resourceType, 'resource_id' => $resourceId]);
        return $result['hash'] ?? null;
    }

    public function deletePublicHashByResource(string $resourceType, string $resourceId)
    {
        $sql = <<<sql
            DELETE FROM short_urls
            WHERE resource_type = :resource_type
            AND resource_id = :resource_id
sql;
        $this->dbs->delete($sql, ['resource_type' => $resourceType, 'resource_id' => $resourceId]);
    }

    public function generateUniqueHash(): string
    {

        $sql = "SELECT 1 FROM short_urls WHERE hash = :hash";

        // try to find a unique string until it's found
        do {
            $hash = StringUtility::generateRandomString(6, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        } while($this->dbs->selectOne($sql, ['hash' => $hash]) !== null);

        return $hash;
    }
}