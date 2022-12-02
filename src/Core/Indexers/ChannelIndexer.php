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

class ChannelIndexer
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

    public function indexChannel(int $channelId, string $channelName, ?string $blurb = '')
    {

        // first find the users that are members of this channel
        $sql = <<<SQL
            SELECT user_id
            FROM channel_users
            WHERE channel_id = :channel_id
SQL;
        $rows = $this->dbs->select($sql, ['channel_id' => $channelId]);
        $members = array_map(function ($row) {
            return $row['user_id'];
        }, $rows);

        $params = [
            'index' => $this->getIndexName(),
            'id'    => $channelId,
            'body'  => [
                'doc' => [
                    'name' => $channelName,
                    'blurb' => $blurb ?? '',
                    'members' => $members,
                    'ts' => Carbon::now()->timestamp,
                ],
                'upsert' => [
                    'name' => $channelName,
                    'blurb' => $blurb ?? '',
                    'members' => $members,
                    'ts' => Carbon::now()->timestamp,
                ],
            ]
        ];

        $this->elasticClient->update($params);

    }

    public function getIndexName(): string
    {
        return $this->indexName ?? $this->config->get('/indices/channels');
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

}