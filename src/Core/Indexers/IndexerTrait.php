<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Generator;

trait IndexerTrait
{

    /**
     * @var Client
     */
    protected $elasticClient;

    public function remove(string $id)
    {
        $params = [
            'index' => $this->getIndexName(),
            'id'    => $id
        ];

        $this->elasticClient->delete($params);
    }

    public function scanIndex(int $limit): Generator
    {
        $params = [
            'index' => $this->getIndexName(),
            "scroll" => "1m",
            "size" => $limit,
        ];
        $result = $this->elasticClient->search($params);

        $total = $result['hits']['total']['value'];

        while(count($result['hits']['hits'])) {

            yield [$total, $result['hits']['hits']];

            $result = $this->elasticClient->scroll([
                'scroll' => '1m',
                'scroll_id' => $result['_scroll_id'],
            ]);

        }

    }

    public function isIndexed($id, int $lastChangeTS): bool
    {
        $params =[
            'index' => $this->getIndexName(),
            'id'    => $id
        ];

        try {
            $document = $this->elasticClient->get($params);
        } catch (Missing404Exception $e) {
            return false;
        }

        return !empty($document['_source']['ts']) && $document['_source']['ts'] > $lastChangeTS;
    }

}