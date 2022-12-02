<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

use Carbon\Carbon;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Indexers\FileIndexer;
use CodeLathe\Core\Utility\FileSize;
use Elasticsearch\Client as ElasticClient;
use Exception;
use GuzzleHttp\Psr7\Stream;

abstract class AbstractParser
{
    const CHUNK_SIZE = 4098;

    /**
     * @var ElasticClient
     */
    protected $elasticClient;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    public function __construct(ElasticClient $elasticClient, ConfigRegistry $config)
    {
        $this->elasticClient = $elasticClient;
        $this->config = $config;
    }

    /**
     * @param string $filePath
     * @return string
     */
    abstract protected function extractText(string $filePath): string;

    /**
     * @param string $text
     * @return string
     */
    abstract protected function sanitizeContent(string $text): string;

    /**
     * @param int $id
     * @param string $name
     * @param string $path
     * @param string|null $relativePath
     * @param int|null $channelId
     * @param string|null $extension
     * @param string $localFilePath
     * @param FileIndexer $indexer
     * @throws Exception
     */
    public function index(int $id,
                          string $name,
                          string $path,
                          ?string $relativePath,
                          ?int $channelId,
                          string $extension,
                          string $localFilePath,
                          FileIndexer $indexer
                          ): void
    {

        // ensure that we don't index a file greater than the global size limit
        if (filesize($localFilePath) > FileSize::toBytes($this->config->get('/search/content_size_limit'))) {
            $content = null;
        } else {
            $content = $this->extractText($localFilePath);
            $content = $this->sanitizeContent($content);
        }

        $indexer->index($id, $name, $path, $relativePath, $channelId, $extension, 'file', $content);

    }
}