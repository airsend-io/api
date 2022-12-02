<?php /** @noinspection PhpDocRedundantThrowsInspection */
declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\SearchDataControllerException;
use CodeLathe\Core\Indexers\ActionIndexer;
use CodeLathe\Core\Indexers\ChannelIndexer;
use CodeLathe\Core\Indexers\FileIndexer;
use CodeLathe\Core\Indexers\MessageIndexer;
use CodeLathe\Core\Indexers\UserIndexer;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;
use Elasticsearch\Client as ElasticSearchClient;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Throwable;

class SearchDataController
{

    /**
     * @deprecated not used anymore, since we don't list from index anymore
     */
    const MEDIA_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];

    /**
     * @var DatabaseService
     */
    protected $dbs;

    /**
     * @var FSDatabaseService
     */
    protected $fsdbs;

    /**
     * @var DataController
     */
    protected $dc;

    /**
     * @var ElasticSearchClient
     */
    protected $elasticClient;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * SearchDataController constructor.
     * @param DatabaseService $dbs
     * @param FSDatabaseService $fsdbs
     * @param DataController $dc
     * @param ElasticSearchClient $elasticClient
     */
    public function __construct(DatabaseService $dbs, FSDatabaseService $fsdbs, DataController $dc, ElasticSearchClient $elasticClient, ConfigRegistry $config)
    {
        $this->dbs = $dbs;
        $this->fsdbs = $fsdbs;
        $this->dc = $dc;
        $this->elasticClient = $elasticClient;
        $this->config = $config;
    }

    /**
     * @param int $userId
     * @return int[]
     */
    protected function findChannelIdsForUser(int $userId): array
    {

        // TODO - With teams support, this logic should change, since channels/users from the same team should always appear on the search

        $channelIds = [];
        try {
            foreach ($this->dc->getChannelsForUser($userId) as $channel) {
                $channelIds[] = $channel['id'];
            }
        } catch (Throwable $e) {
            return [];
        }
        return $channelIds;
    }

    /**
     * @param int $userId
     * @param string $searchQuery The search query
     * @param int|null $limit
     * @param int|null $channelId
     * @return array
     * @throws NoNodesAvailableException
     * @throws Missing404Exception
     */
    public function searchMessages(int $userId, string $searchQuery, ?int $limit = null, ?int $channelId = null)
    {

        $searchQuery = $this->normalizeSearchQuery($searchQuery);

        $params = [
            'index' => $this->config->get('/indices/messages'),
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'match' => [
                                'text' => [
                                    'query' => $searchQuery,
                                    'fuzziness' => 'AUTO',
                                ]
                            ]
                        ],
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'text' => new \StdClass()
                    ]
                ],
            ]
        ];

        if ($limit !== null) {
            $params['body']['size'] = $limit;
        }

        if ($channelId !== null) {
            $params['body']['query']['bool']['filter'] = ['term' => ['channel_id' => $channelId]];
        } else {
            $params['body']['query']['bool']['filter'] = ['terms' => ['channel_id' => $this->findChannelIdsForUser($userId)]];
        }

        $results = $this->elasticClient->search($params);
        $hits = $results['hits']['hits'] ?? [];

        $output = [];
        foreach ($hits as $hit) {
            $output[$hit['_id']] = [
                'channel_id' => (int)$hit['_source']['channel_id'],
                'user_id' => (int)$hit['_source']['user_id'],
                'highlights' => $hit['highlight']['text'] ?? [],
            ];
        }
        return $output;

    }

    /**
     * @param int $userId
     * @param string $searchQuery
     * @param int|null $limit
     * @param int|null $channelId
     * @return array
     * @throws NoNodesAvailableException
     * @throws Missing404Exception
     */
    public function searchUsers(int $userId, string $searchQuery, ?int $limit, ?int $channelId)
    {

        $searchQuery = $this->normalizeSearchQuery($searchQuery);

        $params = [
            'index' => $this->config->get('/indices/users'),
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'match' => [
                                'name' => [
                                    'query' => $searchQuery,
                                    'fuzziness' => 'AUTO',
                                ]
                            ]
                        ],
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'name' => new \StdClass()
                    ]
                ],
            ]
        ];

        if ($limit !== null) {
            $params['body']['size'] = $limit;
        }

        // filter only users from the required channels, or users that are members of at least one channel with the logged user
        if ($channelId !== null) {
            $params['body']['query']['bool']['filter'] = ['term' => ['channels' => $channelId]];
        } else {
            $params['body']['query']['bool']['filter'] = ['terms' => ['channels' => $this->findChannelIdsForUser($userId)]];
        }

        $results = $this->elasticClient->search($params);

        $hits = $results['hits']['hits'] ?? [];
        $output = [];
        foreach ($hits as $hit) {
            $output[$hit['_id']] = $hit['highlight']['name'] ?? [];
        }
        return $output;

    }

    /**
     * @param int $userId
     * @param string $searchQuery
     * @param int|null $limit
     * @param int|null $channelId
     * @param int|null $highlightSize
     * @param callable|null $highlightHandler
     * @return array
     */
    public function searchActions(int $userId,
                                  string $searchQuery,
                                  ?int $limit,
                                  ?int $channelId,
                                  ?int $highlightSize = 0,
                                  ?callable $highlightHandler = null)
    {

        $highlightHandler = $highlightHandler
            ?? function(array $highlightData) {
                return array_merge($highlightData['name'] ?? [], $highlightData['desc'] ?? []);
            };

        $searchQuery = $this->normalizeSearchQuery($searchQuery);

        $params = [
            'index' => $this->config->get('/indices/actions'),
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $searchQuery,
                                'fields' => ['name', 'desc'],
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'name' => [
                            "fragment_size" => $highlightSize ?? 0
                        ],
                        'desc' => [
                            "fragment_size" => $highlightSize ?? 0
                        ]
                    ]
                ],
            ]
        ];

        $params['body']['size'] = $limit ?? 100;

        if ($channelId !== null) {
            $params['body']['query']['bool']['filter'] = ['term' => ['channel_id' => $channelId]];
        } else {
            $params['body']['query']['bool']['filter'] = ['terms' => ['channel_id' => $this->findChannelIdsForUser($userId)]];
        }

        $hits = [];
        do {
            $params['body']['from'] = count($hits);
            $results = $this->elasticClient->search($params);
            $hits = array_merge($hits, $results['hits']['hits'] ?? []);
        } while ($limit === null && $results['hits']['total']['value'] > count($hits));

        // if limit is not defined, paginate until all the results are found
        $output = [];
        foreach ($hits as $hit) {
            if (isset($hit['_id']) && isset($hit['_source']['channel_id']) && isset($hit['highlight'])) {
                $output[$hit['_id']] = [
                    'channel_id' => (int)$hit['_source']['channel_id'],
                    'highlights' => $highlightHandler($hit['highlight']),
                ];
            }
        }
        return $output;

    }

    /**
     * Simple search for actions mentioning. Always require a search term and a channel id.
     * Only returns the found id and highlighted result.
     *
     * @param string $searchQuery
     * @param int $channelId
     * @return void[]
     */
    public function searchActionsForMention(string $searchQuery, int $channelId)
    {
        $searchQuery = $this->normalizeSearchQuery($searchQuery);

        // to run a phrase_search using fuziness, we need to cook the search query, splitting it in words
        $searchTerms = preg_split('/\s+/', $searchQuery);

        $clauses = [];
        foreach ($searchTerms as $term) {
            $clauses[] = [
                'span_multi' => [
                    'match' => [
                        'fuzzy' => [
                            'name' => $term
                        ],
                    ]
                ],
            ];
        }

        $params = [
            'index' => $this->config->get('/indices/actions'),
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'span_near' => [
                                'clauses' => $clauses,
                                'slop' => 10,
                                'in_order' => true,
                            ],
                        ],
                        'filter' => [
                            'term' => [
                                'channel_id' => $channelId
                            ]
                        ],
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        'name' => [
                            "fragment_size" => 0
                        ],
                    ]
                ],
            ]
        ];

        $results = $this->elasticClient->search($params);

        // if there is no results and the search query have just one word, try a wildcard search
        if (count($searchTerms) === 1 && ($results['hits']['total']['value'] ?? 0) === 0) {
            $params['body']['query']['bool']['must'] = [
                'wildcard' => ['name' => "$searchQuery*"]
            ];
            $results = $this->elasticClient->search($params);
        }


        return array_map(function($item) {
            return [
                'id' => $item['_id'],
                'name' => $item['_source']['name'],
                'highlight' => $item['highlight']['name'][0] ?? '',
            ];
        }, $results['hits']['hits'] ?? []);

    }

    /**
     * @param int $userId
     * @param string $searchQuery
     * @param int|null $limit
     * @return array
     * @throws NoNodesAvailableException
     * @throws Missing404Exception
     */
    public function searchChannels(int $userId, string $searchQuery, ?int $limit)
    {
        $searchQuery = $this->normalizeSearchQuery($searchQuery);

        $params = [
            'index' => $this->config->get('/indices/channels'),
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $searchQuery,
                                'fields' => ['name', 'blurb'],
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                        'filter' => [
                            'term' => ['members' => $userId]
                        ]
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'name' => new \StdClass(),
                        'blurb' => new \StdClass()
                    ]
                ],
            ]
        ];

        if ($limit !== null) {
            $params['body']['size'] = $limit;
        }

        $results = $this->elasticClient->search($params);

        $hits = $results['hits']['hits'] ?? [];

        $output = [];
        foreach ($hits as $hit) {
            $output[$hit['_id']] = array_merge($hit['highlight']['name'] ?? [], $hit['highlight']['blurb'] ?? []);
        }
        return $output;

    }

    /**
     * @param int $userId
     * @param string $searchQuery
     * @param int|null $channelId
     * @return array
     */
    public function searchFiles(int $userId,
                                string $searchQuery,
                                ?int $channelId = null)
    {

        $params = [
            'index' => $this->config->get('/indices/files'),
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $searchQuery,
                                'fields' => ['name', 'content'],
                                'fuzziness' => 'AUTO',
                            ],
                             //'match_all' => new \StdClass(), // this returns all results
                        ],
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        'name' => new \StdClass(),
                        'content' => new \StdClass()
                    ]
                ],
            ]
        ];

        // filter context
        if ($channelId !== null) {
            $params['body']['query']['bool']['filter'] = ['term' => ['channel_id' => $channelId]];
        } else {
            $params['body']['query']['bool']['filter'] = ['terms' => ['channel_id' => $this->findChannelIdsForUser($userId)]];
        }

        // run the search
        $results = $this->elasticClient->search($params);

        // normalize the results
        $hits = $results['hits']['hits'];

        $output = [];
        foreach ($hits as $hit) {

            $id = (int)$hit['_id'];
            $highlights = array_merge($hit['highlight']['content'] ?? [], $hit['highlight']['name'] ?? []);
            $channelId = $hit['_source']['channel_id'];
            $path = $hit['_source']['relative_path'];

            $output[$hit['_id']] = [
                'id' => $id,
                'path' => $path,
                'highlights' => $highlights,
                'channel_id' => $channelId
            ];
        }
        return $output;

    }

    /**
     * @param string $query
     * @return string
     */
    protected function normalizeSearchQuery(string $query): string
    {
        // first convert any special char sequence to single spaces
        $query = preg_replace('/[^a-zA-Z0-9À-ÿ]+/', ' ', $query);

        // trim spaces
        return trim($query);

    }

}