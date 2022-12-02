<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\FactDataStores;

use Carbon\CarbonImmutable;
use Elasticsearch\Client as ElasticClient;
use Psr\Log\LoggerInterface;

class RequestsFactDataStore
{

    /**
     * @var ElasticClient
     */
    protected $elasticClient;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ElasticClient $elasticClient, LoggerInterface $logger)
    {
        $this->elasticClient = $elasticClient;
        $this->logger = $logger;
    }

    public function getIndexName(): string
    {
        return 'requests_cube';
    }

    public function createIfNotExists(): void
    {
        if (!$this->elasticClient->indices()->exists(['index' => $this->getIndexName()])) {
            $this->logger->debug("Index {$this->getIndexName()} doesn't exists. Creating...");
            $this->create();
        }
    }

    /**
     * Creates the index with the correct format
     */
    public function create(): void
    {

        $this->elasticClient->indices()->create([
            'index' => $this->getIndexName(),
            'body' => [
                'mappings' => [
                    'properties' => [
                        'endpoint' => [
                            'properties' => [
                                'endpoint' => ['type' => 'keyword'],
                                'prefix' => ['type' => 'keyword'],
                                'productive' => ['type' => 'boolean'],
                                'data_call' => ['type' => 'boolean'],
                            ],
                        ],
                        'location' => [
                            'properties' => [
                                'continent' => ['type' => 'keyword'],
                                'country' => ['type' => 'keyword'],
                                'country_code' => ['type' => 'keyword'],
                                'country_area' => ['type' => 'keyword'],
                                'city' => ['type' => 'keyword'],
                                'location' => ['type' => 'geo_point'],
                                'timezone' => ['type' => 'keyword'],
                                'hemisphere' => ['type' => 'keyword'],
                                'climate_zone' => ['type' => 'keyword'],
                            ]
                        ],
                        'date' => [
                            'properties' => [
                                'date' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd',
                                ],
                                'year' => ['type' => 'integer'],
                                'year_quarter' => ['type' => 'integer'],
                                'server_season' => ['type' => 'keyword'],
                                'local_season' => ['type' => 'keyword'],
                                'month' => ['type' => 'integer'],
                                'year_week' => ['type' => 'integer'],
                                'month_week' => ['type' => 'integer'],
                                'week_monday' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd'
                                ],
                                'week_friday' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd'
                                ],
                                'month_day' => ['type' => 'integer']
                            ]
                        ],
                        'time' => [
                            'properties' => [
                                'server_hour' => ['type' => 'keyword'],
                                'local_hour' => ['type' => 'keyword'],
                                'server_period' => ['type' => 'keyword'],
                                'local_period' => ['type' => 'keyword'],
                            ]
                        ],
                        'device' => [
                            'properties' => [
                                'os' => ['type' => 'keyword'],
                                'os_version' => ['type' => 'keyword'],
                                'client' => ['type' => 'keyword'],
                                'client_version' => ['type' => 'keyword'],
                                'browser' => ['type' => 'keyword'],
                                'browser_version' => ['type' => 'keyword'],
                            ]
                        ],
                        'user' => [
                            'properties' => [
                                'email' => ['type' => 'keyword'],
                                'email_domain' => ['type' => 'keyword'],
                                'company_domain' => ['type' => 'boolean'],
                                'internal' => ['type' => 'boolean'],
                                'channels_owned' => ['type' => 'long'],
                                'channels_member' => ['type' => 'long'],
                                'messages_sent' => ['type' => 'long'],
                                'created_on' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss'
                                ],
                            ],
                        ],
                        'language' => [
                            'properties' => [
                                'lang_code' => ['type' => 'keyword'],
                            ],
                        ],
                        'time_taken' => ['type' => 'integer'],
                        'timestamp' => ['type' => 'date'],
                    ]
                ]
            ]
        ]);
    }

    public function delete(): void
    {
        if ($this->elasticClient->indices()->exists(['index' => $this->getIndexName()])) {
            $this->elasticClient->indices()->delete(['index' => $this->getIndexName()]);
        }
    }

    /**
     * Parses and save the dimension data to the database.
     * Returns the id of the inserted/found record
     *
     * @param array $endpointData
     * @param array $locationData
     * @param array $dateData
     * @param array $timeData
     * @param array $deviceData
     * @param array $userData
     * @param array $languageData
     * @param int $timeTaken
     * @param CarbonImmutable $date
     * @param int $version
     * @return void
     */
    public function insert(array $endpointData,
                           array $locationData,
                           array $dateData,
                           array $timeData,
                           array $deviceData,
                           array $userData,
                           array $languageData,
                           int $timeTaken,
                           CarbonImmutable $date,
                           int $version): void
    {

        // ensure index creation and definition
        $this->createIfNotExists();

        $data = [
            'endpoint' => $endpointData,
            'location' => $locationData,
            'date' => $dateData,
            'time' => $timeData,
            'device' => $deviceData,
            'user' => $userData,
            'language' => $languageData,
            'time_taken' => $timeTaken,
            'timestamp' => $date->valueOf(),
            'version' => $version,
        ];

        $this->elasticClient->index([
            'index' => $this->getIndexName(),
            'body' => $data,
        ]);

    }

}