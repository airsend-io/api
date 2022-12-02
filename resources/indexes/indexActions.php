<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


use Carbon\Carbon;
use Carbon\CarbonInterval;
use CodeLathe\Core\Indexers\ActionIndexer;
use CodeLathe\Service\Database\DatabaseService;
use Elasticsearch\Client;
use Psr\Container\ContainerInterface;

const CHUNK_SIZE = 10;

/** @var ContainerInterface $container */

if(php_sapi_name() == "cli") {


    /** @var DatabaseService $dbs */
    $dbs = $container->get(DatabaseService::class);

    /** @var Client $elasticClient */
    $elasticClient = $container->get(Client::class);

    /** @var ActionIndexer $messageIndexer */
    $actionIndexer = $container->get(ActionIndexer::class);

    require_once __DIR__ . '/functions.php';

    echo PHP_EOL;

    // first parse the command options
    try {
        $options = parseOptions($argv);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(0);
    }

    // find the index name
    if (!empty($options['index_name'])) {
        $indexName = $options['index_name'];
        $actionIndexer->setIndexName($indexName);
    } else {
        $indexName = $actionIndexer->getIndexName();
    }

    if ($options['flush']) {

        // no look-back limit defined, so recreate the index
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
                            'name' => [
                                'type' => 'text',
                                'analyzer' => 'simple',
                            ],
                            'desc' => [
                                'type' => 'text',
                                'analyzer' => 'simple',
                            ],
                        ]
                    ]
                ]
            ]); // can set configs here
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
        $sinceSql = "AND (created_on > '{$options['since']}' OR updated_on > '{$options['since']}')";
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

    // count the number of messages to be indexed
    $countSql = <<<sql
        SELECT count(*) as total
        FROM actions m
        WHERE TRUE
        {$sinceSql}
        {$idFilterSql}
sql;
    $result = $dbs->selectOne($countSql);
    $total = (int)$result['total'];

    echo "INDEXING ACTIONS ($total total)..." . PHP_EOL;

    // read the messages from database (by chunks)
    $sql = <<<sql
        SELECT id, action_name, action_desc, channel_id, created_on, updated_on
        FROM actions
        WHERE TRUE
        {$sinceSql}
        {$idFilterSql}
        LIMIT :limit OFFSET :offset
sql;

    $offset = 0;
    $indexed = 0;
    while(true) {

        $rows = $dbs->select($sql, ['limit' => CHUNK_SIZE, 'offset' => $offset]);
        if (empty($rows)) {
            break;
        }
        $offset+= CHUNK_SIZE;

        // index the chunk
        $skipped = 0;
        $indexedChunk = 0;
        foreach ($rows as $row) {
            $lastChangeTS = Carbon::createFromFormat('Y-m-d H:i:s', $row['updated_on'] ?? $row['created_on'])->timestamp;
            if ($options['force-reindex'] || !$actionIndexer->isIndexed((int)$row['id'], $lastChangeTS)) {
                try {
                    $actionIndexer->indexAction($row['id'], $row['action_name'], $row['action_desc'], $row['channel_id']);
                } catch (\Throwable $e) {
                    $message = "INDEXING FAILED FOR action {$row['id']} / {$row['action_name']} FROM CHANNEL {$row['channel_id']}";
                    echo $message . PHP_EOL;
                    logError($message, $e);
                }
            } else {
                $skipped++;
            }
            $indexedChunk++;
            $indexed++;
        }
        echo "INDEXED $indexed/$total ($skipped/$indexedChunk skipped)" . PHP_EOL;

    }

    echo "REMOVING ORPHANS ENTRIES ON INDEX..." . PHP_EOL;

    $scanned = 0;
    foreach ($actionIndexer->scanIndex(CHUNK_SIZE) as [$total, $items]) {

        // check if the action still exists on the database
        $sql = <<<sql
        SELECT 1
        FROM actions
        WHERE id = :id
sql;

        $orphans = 0;
        $itemsChunk = count($items);
        foreach ($items as $item) {
            $r = $dbs->selectOne($sql, ['id' => $item['_id']]);
            if ($r === null) {
                $actionIndexer->remove($item['_id']);
                $orphans++;
            }
        }
        $scanned += count($items);
        echo "SCANNED $scanned/$total ($orphans/$itemsChunk removed)" . PHP_EOL;
    }



    echo "INDEXING SUCCESSFUL" . PHP_EOL;

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
