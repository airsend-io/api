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

class ActionIndexer
{

    use IndexerTrait;

    /**
     * @var ElasticClient
     */
    protected $elasticClient;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var string
     */
    protected $indexName;

    public function __construct(ElasticClient $elasticClient, ConfigRegistry $config)
    {
        $this->elasticClient = $elasticClient;
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

    protected function sanitize(string $message): string
    {
        return $this->removeMarkdowns($message);
    }

    public function indexAction(int $actionId, string $name, ?string $desc, int $channelId)
    {

        $params = [
            'index' => $this->getIndexName(),
            'id'    => $actionId,
            'body'  => [
                'doc' => [
                    'name' => $name,
                    'desc' => $this->sanitize($desc ?? ''),
                    'channel_id' => $channelId,
                    'ts' => Carbon::now()->timestamp,
                ],
                'upsert' => [
                    'name' => $name,
                    'desc' => $this->sanitize($desc ?? ''),
                    'channel_id' => $channelId,
                    'ts' => Carbon::now()->timestamp,
                ],
            ]
        ];

        $this->elasticClient->update($params);

    }

    public function getIndexName(): string
    {
        return $this->indexName ?? $this->config->get('/indices/actions');
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

}