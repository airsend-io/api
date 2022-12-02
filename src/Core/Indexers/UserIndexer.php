<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers;


use Carbon\Carbon;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Service\Database\DatabaseService;
use Elasticsearch\Client as ElasticClient;

class UserIndexer
{

    use IndexerTrait;

    /**
     * @var ElasticClient
     */
    protected $elasticClient;

    /**
     * @var DatabaseService
     */
    protected $dbs;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var string
     */
    protected $indexName;

    public function __construct(ElasticClient $elasticClient, DatabaseService $dbs, ConfigRegistry $config)
    {
        $this->elasticClient = $elasticClient;
        $this->dbs = $dbs;
        $this->config = $config;
    }

    protected function removeMarkdowns(string $message): string
    {
        // remove mentions
        $message = preg_replace('/\[([^]]+)]\([^)]+\)/', '$1', $message);

        // remove quotes
        $message = str_replace('`', '', $message);

        return $message;
    }

    protected function sanitizeMessage(string $message): string
    {
        return $this->removeMarkdowns($message);
    }

    public function indexUser(int $userId, string $displayName, string $email)
    {

        // first find the channels where the user is member of
        $sql = <<<SQL
            SELECT channel_id
            FROM channel_users
            WHERE user_id = :user_id
SQL;
        $rows = $this->dbs->select($sql, ['user_id' => $userId]);
        $channels = array_map(function ($row) {
            return $row['channel_id'];
        }, $rows);

        $params = [
            'index' => $this->getIndexName(),
            'id'    => $userId,
            'body'  => [
                'doc' => [
                    'name' => $displayName,
                    'channels' => $channels,
                    'ts' => Carbon::now()->timestamp,
                ],
                'upsert' => [
                    'name' => $displayName,
                    'channels' => $channels,
                    'ts' => Carbon::now()->timestamp,
                ],
            ]
        ];

        $this->elasticClient->update($params);

    }

    public function getIndexName(): string
    {
        return $this->indexName ?? $this->config->get('/indices/users');
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

}