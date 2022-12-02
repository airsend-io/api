<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


use Carbon\Carbon;
use CodeLathe\Core\Indexers\ChannelIndexer;
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

    /** @var ChannelIndexer $channelIndexer */
    $channelIndexer = $container->get(ChannelIndexer::class);

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
        $channelIndexerfile->setIndexName($indexName);
    } else {
        $indexName = $channelIndexer->getIndexName();
    }

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
            $elasticClient->indices()->create(['index' => $indexName]); // can set configs here
        } catch (\Throwable $e) {
            echo 'Failed' . PHP_EOL;
            echo $e->getMessage();
            exit(1);
        }
        echo 'SUCCESS' . PHP_EOL;
        $sinceSql = '';

    } else {
        echo 'INDEX ' . $indexName . ' EXISTS...' . PHP_EOL;
    }

    $sinceSql = '';
    if(!empty($options['since'])) {
        echo "INCREMENTALLY INDEXING SINCE {$options['since']} ..." . PHP_EOL;
        $sinceSql = "AND created_on > '{$options['since']}' OR updated_on > '{$options['since']}'";
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
        FROM channels
        WHERE channel_status = 1
        {$sinceSql}
        {$idFilterSql}
sql;
    $result = $dbs->selectOne($countSql);
    $total = (int)$result['total'];

    echo "INDEXING CHANNELS ($total total)..." . PHP_EOL;

    // read the messages from database (by chunks)
    $sql = <<<sql
        SELECT id, channel_name, blurb, created_on, updated_on
        FROM channels
        WHERE channel_status = 1
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
            if ($options['force-reindex'] || !$channelIndexer->isIndexed((int)$row['id'], $lastChangeTS)) {
                try {
                    $channelIndexer->indexChannel($row['id'], $row['channel_name'], $row['blurb']);
                } catch (\Throwable $e) {
                    $message = "INDEXING FAILED FOR CHANNEL {$row['id']} / {$row['channel_name']}";
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

    echo "INDEXING SUCCESSFUL" . PHP_EOL;

} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
