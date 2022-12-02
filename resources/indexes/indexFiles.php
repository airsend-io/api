<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use Carbon\Carbon;
use Carbon\CarbonInterval;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Indexers\FileIndexer;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Objects\ChannelPath;
use CodeLathe\Core\Utility\FileSize;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use CodeLathe\Service\Storage\Exceptions\NotAFileException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use Elasticsearch\Client;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

const CHUNK_SIZE = 50;

/** @var ContainerInterface $container */

function recursiveFindPath(FSDatabaseService $fsdbs, int $parentId, string $path)
{

    $sql = <<<sql
        SELECT name, parent_id
        FROM items
        WHERE id = :id
sql;

    $row = $fsdbs->selectOne($sql, ['id' => $parentId]);

    $path = "{$row['name']}/$path";

    if (empty($row['parent_id'])) {
        return "/$path";
    }

    return recursiveFindPath($fsdbs, (int)$row['parent_id'], $path);
}

if(php_sapi_name() == "cli") {


    /** @var FSDatabaseService $fsdbs */
    $fsdbs = $container->get(FSDatabaseService::class);

    /** @var DatabaseService $dbs */
    $dbs = $container->get(DatabaseService::class);

    /** @var StorageServiceInterface $storageService */
    $storageService = $container->get(StorageServiceInterface::class);

    /** @var Client $elasticClient */
    $elasticClient = $container->get(Client::class);

    /** @var FileIndexer $fileIndexer */
    $fileIndexer = $container->get(FileIndexer::class);

    /** @var ConfigRegistry $config */
    $config = $container->get(ConfigRegistry::class);

    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);

    require_once __DIR__ . '/functions.php';

    echo PHP_EOL;

    // first parse the command options
    try {
        $options = parseOptions($argv);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(0);
    }

    $verbose = $options['verbose'];

    // find the index name
    if (!empty($options['index_name'])) {
        $indexName = $options['index_name'];
        $fileIndexer->setIndexName($indexName);
    } else {
        $indexName = $fileIndexer->getIndexName();
    }

    // handle index flushing
    if ($options['flush']) {

        // drop current index
        echo 'DROP THE INDEX `' . $indexName . '` ---> ';
        try {
            $elasticClient->indices()->delete(['index' => [$indexName]]);
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            echo 'Index not found. Skipping...' . PHP_EOL;
        } catch (Throwable $e) {
            echo 'Failed' . PHP_EOL;
            echo $e->getMessage();
            exit(1);
        }
        echo 'SUCCESS' . PHP_EOL;
    }

    // creates the index if it don't exists
    if (!$elasticClient->indices()->exists(['index' => $indexName])) {

        echo 'CREATE THE INDEX `' . $indexName . '` ---> ';
        try {
            $elasticClient->indices()->create([
                'index' => $indexName,
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'path' => [
                                'type' => 'keyword',
                            ],
                            'relative_path' => [
                                'type' => 'keyword',
                            ],
                            'name' => [
                                'type' => 'text',
                                'analyzer' => 'simple',
                            ],
                            'content' => [
                                'type' => 'text',
                                'analyzer' => 'simple',
                            ],
                            'extension' => [
                                'type' => 'keyword',
                            ],
                            'type' => [
                                'type' => 'keyword'
                            ],
                            'channel_id' => [
                                'type' => 'long',
                            ],
                            'ts' => [
                                'type' => 'long'
                            ],

                        ]
                    ]
                ]
            ]);
        } catch (\Throwable $e) {
            echo 'Failed' . PHP_EOL;
            echo $e->getMessage();
            exit(1);
        }
        echo 'SUCCESS' . PHP_EOL;
    } else {
        echo 'INDEX ' . $indexName . ' EXISTS...' . PHP_EOL;
    }

    $sinceSql = '';
    if(!empty($options['since'])) {
        echo "INCREMENTALLY INDEXING SINCE {$options['since']} ..." . PHP_EOL;
        $sinceSql = "AND modificationdate > '{$options['since']}'";
    } else {
        echo "INCREMENTALLY INDEXING ..." . PHP_EOL;
        $sinceSql = '';
    }

    // handle ids scope
    if (empty($options['ids'])) {
        $idFilterSql = '';
    } else {
        $idFilterSql = 'AND id in (' . implode(',', $options['ids']) . ')';
    }

    // find out the available parsers
    $contentParsersConfig = $config->get('/search/content_parsers');

    // content indexing size limit
    $sizeLimit = FileSize::toBytes($config->get('/search/content_size_limit'));

    // count the number of files to be indexed
    $countSql = <<<sql
        SELECT count(*) as total
        FROM items i
        WHERE type IN ('file', 'folder') -- only include files
        AND versioneddate IS NULL -- ignore older versions
        {$sinceSql}
        {$idFilterSql}
sql;
    $result = $fsdbs->selectOne($countSql);
    $total = (int)$result['total'];

    echo "INDEXING FILES ($total total)..." . PHP_EOL;

    // read the files from database (by chunks)
    $sql = <<<sql
        SELECT id, name, parent_id, extension, size, creationdate, modificationdate, type
        FROM items i
        WHERE type IN ('file', 'folder') -- only include files and folders (ignore sidecars)
        AND versioneddate IS NULL -- ignore older versions
        {$sinceSql}
        {$idFilterSql}
        ORDER BY id
        LIMIT :limit OFFSET :offset
sql;

    $offset = 0;
    $indexedCount = 0;

    // store the team root id on the key, and the team_id on the value
    $teamRoots = [];

    // store the channels team root on the key, and the team_id on the value
    $teamChannelRoots = [];

    // store the id => path key pair (works as a cache for the paths)
    $paths = [];

    while(true) {

        $rows = $fsdbs->select($sql, ['limit' => CHUNK_SIZE, 'offset' => $offset]);
        if (empty($rows)) {
            break;
        }
        $offset += CHUNK_SIZE;

        // index the chunk
        foreach ($rows as $row) {

            $id = (int) $row['id'];
            $name = $row['name'];
            $type = $row['type'];
            $extension = $row['extension'] ?? null;
            $size = $row['size'] ?? '?';
            $lastChangeTs = Carbon::createFromFormat('Y-m-d H:i:s', $row['modificationdate'])->timestamp;
            $parentId = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;

            // find the path
            if ($parentId === null) {
                $path = "/$name";
            } else {
                $path = isset($paths[$parentId]) ? "{$paths[$parentId]}/$name" : recursiveFindPath($fsdbs, $parentId, $name);
            }
            $paths[$id] = $path;

            output("CHECKING ($id) $path ---> ");
            $logger->info("INDEXING FILE ($id) $path");

            // here we skip roots that we don't want to see on the search
            // skip team roots
            if ($parentId === null) {
                output("SKIPPING TEAM ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip Team Channels root
            if (preg_match('/^\/[0-9]+\/Channels$/', $path)) {
                output("SKIPPING TEAM CHANNELS ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip Team deleted items root
            if (preg_match('/^\/[0-9]+\/deleted items$/', $path)) {
                output("SKIPPING TEAM DELETED ITEMS ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip any channels root
            if (preg_match('/^\/[0-9]+\/Channels\/[^\/]+$/', $path)) {
                output("SKIPPING CHANNEL ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip any channel files root
            if (preg_match('/^\/[0-9]+\/Channels\/[^\/]+\/files$/', $path)) {
                output("SKIPPING CHANNEL FILES ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip any channel attachments root
            if (preg_match('/^\/[0-9]+\/Channels\/[^\/]+\/files\/attachments$/', $path)) {
                output("SKIPPING CHANNEL ATTACHMENTS ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip any channel wiki root
            if (preg_match('/^\/[0-9]+\/Channels\/[^\/]+\/wiki$/', $path)) {
                output("SKIPPING CHANNEL WIKI ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // skip any channel deleted items root
            if (preg_match('/^\/[0-9]+\/Channels\/[^\/]+\/deleted items$/', $path)) {
                output("SKIPPING CHANNEL DELETED ITEMS ROOT..." . PHP_EOL);
                $indexedCount++;
                continue;
            }

            // if we done so far, we wil index the path, so try to find the channel id, and if
            // the file is a channel file or channel wiki file, to generate the channel path
            $channelId = null;
            $relativePath = null;
            if (preg_match('/^\/([0-9]+)\/Channels\/([^\/]+)\/([^\/]+)/', $path, $matches)) {
                $basePath = $matches[0];
                $teamId = (int)$matches[1];
                $channelName = $matches[2];
                $firstFolder = $matches[3];
                $channelSql = <<<sql
                    SELECT id
                    FROM channels
                    WHERE team_id = :teamId AND channel_name = :channelName
sql;

                $row = $dbs->selectOne($channelSql, compact('teamId', 'channelName'));
                $channelId = $row['id'];

                // find the relative path
                if (in_array($firstFolder, ['files', 'wiki'])) {
                    $pathType = $firstFolder === 'files' ? ChannelPath::CHANNEL_PATH_TYPE_FILE : ChannelPath::CHANNEL_PATH_TYPE_WIKI;
                    $channelPathSql = <<<sql
                        SELECT id 
                        FROM channel_paths
                        WHERE channel_id = :channel_id AND path_type = :path_type
sql;
                    $row = $dbs->selectOne($channelPathSql, ['channel_id' => $channelId, 'path_type' => $pathType]);
                    if ($row !== null) {
                        $baseRelativePath = $firstFolder === 'files' ? '/cf' : '/wf';
                        $baseRelativePath .= "/{$row['id']}";
                        $pattern = '/^' . preg_quote($basePath, '/') . '/';
                        $relativePath = preg_replace($pattern, $baseRelativePath, $path);
                    }

                }

            }

            // try to index the content (if necessary)
            $isIndexed = false;
            if (!$options['skip_content'] && in_array($extension, array_keys($contentParsersConfig)) && $size < $sizeLimit) {

                $isIndexed = $fileIndexer->isIndexed($id, $lastChangeTs, true);

                if ($options['force-reindex'] || !$isIndexed) {

                    $logger->info("DOWNLOADING CONTENT FROM FILE ({$size}) {$path}");
                    $download = null;
                    try {
                        $download = $fileLocation = $storageService->download($path, null, 'local');
                    } catch (NotAFileException | NotFoundException $e) {
                        // if anything goes wrong on file downloading,, skip content download
                        $logger->info("DOWNLOAD FAILED FOR FILE {$path}");
                    }

                    if ($download !== null) {

                        $logger->info("DOWNLOAD COMPLETE FOR FILE {$path}");
                        $fileLocation = $download->getPayload()['tmpfile'];

                        output("INDEXING FILE CONTENT..." . PHP_EOL);

                        $parser = $container->get($contentParsersConfig[$extension]);

                        try {
                            $fileIndexer->indexDocumentContent($id, $name, $path, $relativePath, $channelId, $extension, $parser, $fileLocation);
                        } catch (Throwable $e) {
                            $message = "CONTENT INDEXING FAILED FOR FILE {$path}/{$row['name']}";
                            output($message . PHP_EOL);
                            logError($message, $e);
                        }
                        $isIndexed = true;
                        unlink($fileLocation);

                    }
                } else {
                    output("SKIPPING (ALREADY INDEXED)..." . PHP_EOL);
                }

            }

            // content wasn't indexed, so index the file name
            if (!$isIndexed) {

                if ($options['force-reindex'] || !$fileIndexer->isIndexed($id, $lastChangeTs)) {

                    output('INDEXING ' . strtoupper($type) .  ' NAME...' . PHP_EOL);

                    try {
                        $fileIndexer->indexDocumentName($id, $name, $path, $relativePath, $channelId, $extension, $type);
                    } catch (Throwable $e) {
                        $message = "NAME INDEXING FAILED FOR FILE $name ($id)";
                        output($message . PHP_EOL);
                        logError($message, $e);
                    }

                } else {
                    output("SKIPPING (ALREADY INDEXED)..." . PHP_EOL);
                }
            }
            $indexedCount++;
        }
        if ($verbose) {
            echo "INDEXED $indexedCount/$total" . PHP_EOL;
        } else {
            echo "\rINDEXED " . number_format(($indexedCount/$total)*100, 0) . "% ($indexedCount/$total)                   ";
        }
    }

    echo PHP_EOL;
    echo "REMOVING ORPHANS ENTRIES ON INDEX..." . PHP_EOL;

    $scanned = 0;
    foreach ($fileIndexer->scanIndex(CHUNK_SIZE) as [$total, $items]) {

        // check if the file still exists on the database
        $sql = <<<sql
        SELECT 1
        FROM items
        WHERE id = :id
sql;

        $orphans = 0;
        $itemsChunk = count($items);
        foreach ($items as $item) {

            $row = $fsdbs->selectOne($sql, ['id' => $item['_id']]);

            if ($row === null) {
                $fileIndexer->remove($item['_id']);
                $orphans++;
            }
        }
        $scanned += count($items);

        if ($verbose) {
            echo "SCANNED $scanned/$total ($orphans/$itemsChunk removed)" . PHP_EOL;
        } else {
            echo "\rSCANNED " . number_format(($scanned/$total)*100) . "% ($scanned/$total)             ";
        }
    }

    echo PHP_EOL;

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
