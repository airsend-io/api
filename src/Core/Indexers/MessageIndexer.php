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

class MessageIndexer
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

    protected function sanitizeMessage(string $message): string
    {
        return $this->removeMarkdowns($message);
    }

    public function indexMessage(int $messageId, int $channelId, int $userId, ?string $content = '')
    {

        $params = [
            'index' => $this->getIndexName(),
            'id'    => $messageId,
            'body'  => [
                'doc' => [
                    'user_id' => $userId,
                    'channel_id' => $channelId,
                    'text' => $this->sanitizeMessage($content ?? ''),
                    'ts' => Carbon::now()->timestamp,
                ],
                'upsert' => [
                    'user_id' => $userId,
                    'channel_id' => $channelId,
                    'text' => $this->sanitizeMessage($content ?? ''),
                    'ts' => Carbon::now()->timestamp,
                ],
            ]
        ];

        $this->elasticClient->update($params);

    }

    public function getIndexName(): string
    {
        return $this->indexName ?? $this->config->get('/indices/messages');
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

}