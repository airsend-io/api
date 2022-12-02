<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers;

use Carbon\Carbon;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Indexers\FileParsers\AbstractParser;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;
use Elasticsearch\Client as ElasticClient;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class FileIndexer
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

    /**
     * @var FSDatabaseService
     */
    protected $fsdbs;

    public function __construct(ElasticClient $elasticClient, DatabaseService $dbs, FSDatabaseService $fsdbs, ConfigRegistry $config)
    {
        $this->elasticClient = $elasticClient;
        $this->dbs = $dbs;
        $this->config = $config;
        $this->fsdbs = $fsdbs;
    }

    public function sanitizeWiki(string $text): string
    {
        // remove mentions
        $text = preg_replace('/!?\[([^]]+)]\([^)]+\)/', '$1', $text);

        // remove quotes
        $text = str_replace('`', '', $text);

        // remove multiple line breaks
        $text = preg_replace('/\R{2,}/', "\n", $text);

        // remove horizontal lines
        $text = preg_replace('/-{2,}|={2,}|_{2,}/', '', $text);

        return $text;
    }

    public function isIndexed(int $id, ?int $lastChangeTS = null, bool $checkContent = false): bool
    {
        $params = [
            'index' => $this->getIndexName(),
            'id' => $id
        ];
        try {
            $document = $this->elasticClient->get($params);
        } catch (Missing404Exception $e) {
            return false;
        }

        // ignore dates, just check if the entry exists
        if ($lastChangeTS === null) {
            return !$checkContent || isset($document['_source']['content']);
        }
        return (!$checkContent || isset($document['_source']['content'])) && isset($document['_source']['ts']) && $document['_source']['ts'] >= $lastChangeTS;
    }

    /**
     * @param string $path
     * @param string $type
     * @return array|null[]
     * @deprecated
     */
    protected function findFSData(string $path, string $type = 'file'): array
    {

        $path = preg_replace('/^\/f/', '', $path);
        if (!preg_match('/^(.+)\/([^\/]+)$/', $path, $matches)) {
            return [null, null, null];
        }
        [,$parentpath, $name] = $matches;
        $sql = 'SELECT * FROM items WHERE parentpath = :parentpath AND name = :name AND type = :type AND versioneddate IS NULL';

        $row = $this->fsdbs->selectOne($sql, compact('parentpath', 'name', 'type'));
        if ($row === null) {
            return [null, null, null];
        }
        return [
            (int) $row['id'],
            Carbon::createFromFormat('Y-m-d H:i:s', $row['creationdate'])->timestamp,
            Carbon::createFromFormat('Y-m-d H:i:s', $row['modificationdate'])->timestamp,
        ];

    }

    /**
     * @param int $id
     * @param string $name
     * @param $path
     * @param string|null $relativePath
     * @param int|null $channelId
     * @param string|null $extension
     * @param string $type
     * @param string|null $content
     */
    public function index(int $id,
                          string $name,
                          $path,
                          ?string $relativePath,
                          ?int $channelId,
                          ?string $extension,
                          string $type,
                          ?string $content = null)
    {
        $params = [
            'index' => $this->getIndexName(),
            'id'    => $id,
            'body'  => [
                'doc' => [
                    'id' => (int) $id,
                    'name' => $name,
                    'path' => $path,
                    'relative_path' => $relativePath,
                    'extension' => $extension === null ? null : strtolower($extension),
                    'type' => $type,
                    'channel_id' => $channelId,
                    'ts' => Carbon::now()->timestamp,
                ],
                'upsert' => [
                    'id' => (int) $id,
                    'name' => $name,
                    'path' => $path,
                    'relative_path' => $relativePath,
                    'extension' => $extension === null ? null : strtolower($extension),
                    'type' => $type,
                    'channel_id' => $channelId,
                    'ts' => Carbon::now()->timestamp,
                ],
            ]
        ];

        if ($content !== null) {
            $params['body']['doc']['content'] = $content;
            $params['body']['upsert']['content'] = $content;
        }

        $this->elasticClient->update($params);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $path
     * @param string|null $relativePath
     * @param int|null $channelId
     * @param string|null $extension
     * @param string $type
     */
    public function indexDocumentName(int $id,
                                      string $name,
                                      string $path,
                                      ?string $relativePath,
                                      ?int $channelId,
                                      ?string $extension,
                                      string $type = 'file'): void
    {
        $this->index($id, $name, $path, $relativePath, $channelId, $extension, $type);
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $path
     * @param string|null $relativePath
     * @param int|null $channelId
     * @param string $extension
     * @param AbstractParser $parser
     * @param string $filePath
     * @throws \Exception
     */
    public function indexDocumentContent(int $id,
                                         string $name,
                                         string $path,
                                         ?string $relativePath,
                                         ?int $channelId,
                                         string $extension,
                                         AbstractParser $parser,
                                         string $filePath): void
    {
        $parser->index($id, $name, $path, $relativePath, $channelId, $extension, $filePath, $this);
    }

    /**
     * @param string $path
     * @param int|null $channelId
     * @return string
     * @deprecated Replaces
     */
    protected function translatePath(string $path, ?int &$channelId): string
    {

        if (preg_match('/^(\/f\/[0-9]+\/Channels\/[^\/]+\/wiki)\/(.+)$/', $path, $matches)) {

            // if it's a wiki file, convert it to /wf path
            $prefix = '/wf';
            $basePath = $matches[1];
            $suffix = $matches[2];

        } elseif (preg_match('/^(\/f\/[0-9]+\/Channels\/[^\/]+\/files)\/(.+)$/', $path, $matches)) {

            // if it's a channel file, convert it to /cf path
            $prefix = '/cf';
            $basePath = $matches[1];
            $suffix = $matches[2];

        } else {

            // not channel or wiki file, don't translate it
            return $path;
        }

        // query the path_value table
        $sql = 'SELECT id,channel_id FROM channel_paths WHERE path_value = :path';
        $bindings = ['path' => $basePath];
        $row = $this->dbs->selectOne($sql, $bindings);
        if ($row === null) {
            return $path; // not found, don't translate it
        }

        $channelId = (int)$row['channel_id'];

        return "$prefix/{$row['id']}/$suffix";

    }

    public function getIndexName(): string
    {
        return $this->indexName ?? $this->config->get('/indices/files');
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

    protected function extractExtension(string $path): ?string
    {
        if (!preg_match('/\.([^.]+)$/', $path, $matches)) {
            return null;
        }
        return trim($matches[1]);
    }

    protected function extractName(string $path): ?string
    {
        if (!preg_match('/\/([^\/]+)$/', $path, $matches)) {
            return null;
        }
        return trim($matches[1]);
    }

    /**
     * @param int $id
     * @return array
     * @deprecated
     */
    private function findTimestamps(int $id): array
    {

        $sql = <<<sql
            SELECT creationdate, modificationdate
            FROM items
            WHERE id = :id
sql;

        $result = $this->fsdbs->selectOne($sql, compact('id'));

        return [
            Carbon::createFromFormat('Y-m-d H:i:s', $result['creationdate'])->timestamp,
            Carbon::createFromFormat('Y-m-d H:i:s', $result['modificationdate'])->timestamp,
        ];
    }

    /**
     * @param string $documentId
     * @return array
     */
    public function findDocumentById(string $documentId): ?array
    {
        $params = [
            'index' => $this->getIndexName(),
            'id'    => $documentId,
        ];
        try {
            $result = $this->elasticClient->get($params);
        } catch (\Exception $e) {
            return null;
        }

        return $result['_source'];
    }

    public function findDocumentTree(string $documentId): array
    {
        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'wildcard' => ['base_path' => "$documentId/*"]
                        ]
                    ]
                ]
            ]
        ];
        try {
            $result = $this->elasticClient->search($params);
        } catch (\Exception $e) {
            return [];
        }
        return $result['hits']['hits'] ?? [];
    }

    public function indexDocumentRaw(string $path, $document)
    {

        $document['name_sort'] = strtolower($document['name_sort']);
        $document['updated_on'] = Carbon::now()->timestamp;
        $document['ts'] = Carbon::now()->timestamp;

        $params = [
            'index' => $this->getIndexName(),
            'id'    => $path,
            'body'  => [
                'doc' => $document,
                'upsert' => $document,
            ]
        ];

        $this->elasticClient->update($params);


    }

}