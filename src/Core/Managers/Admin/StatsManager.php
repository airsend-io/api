<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Admin;


use Carbon\Carbon;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Service\ServiceRegistryInterface;
use CodeLathe\Service\Zoo\ZooService;
use Elasticsearch\Client as ElasticClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class StatsManager extends ManagerBase
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    protected $registry;

    protected $rtmOps;

    /**
     * @var ZooService
     */
    protected $zooService;

    /**
     * @var ElasticClient
     */
    protected $elasticClient;

    /**
     * StatsManager constructor.
     * @param ServiceRegistryInterface $registry
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param CacheItemPoolInterface $cache
     * @param RtmOperations $rtmOps
     * @param ZooService $zooService
     * @param ElasticClient $elasticClient
     */
    public function __construct(ServiceRegistryInterface $registry,
                                DataController $dataController,
                                LoggerInterface $logger,
                                CacheItemPoolInterface $cache,
                                RtmOperations $rtmOps,
                                ZooService $zooService,
                                ElasticClient $elasticClient)
    {
        $this->registry = $registry;
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->rtmOps = $rtmOps;
        $this->zooService = $zooService;
        $this->elasticClient = $elasticClient;
    }

    /**
     * Derived class must give us the dataController
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getMessageQueueStats(Request $request, Response $response): Response
    {
        //TODO
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws InvalidArgumentException
     */
    public function getWebSocketStats(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $key = 'airsend_ws_stats';
        $item = $this->cache->getItem($key) ;
        $statArray = [];

        if ($item->isHit()) {

            foreach($item->get() as $key=>$val) {
                $statArray[] = $val;
            }
        }
        // If nothing is found, just return empty array
        return JsonOutput::success()->withContent('ws_stats', $statArray)->write($response);
    }

    public function getRedisStats(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $host = $this->registry->get('/cache/host');
        $port = (int)$this->registry->get('/cache/port');

        $fp = fsockopen($host, $port, $errno, $errstr, 5);
        $data = array();
        if (!$fp) {
            die($errstr);
        } else {
            fwrite($fp, "INFO\r\nQUIT\r\n");
            while (!feof($fp)) {
                $dat = fgets($fp);
                if (!empty($dat)) {
                    $info = explode(':', trim($dat), 2);
                    if (isset($info[1])) $data[$info[0]] = $info[1];
                }
            }
            fclose($fp);
        }

        return JsonOutput::success()->withContent('redis_stats', $data)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function getConnectionsStats(Request $request, Response $response): Response
    {
        return JsonOutput::success()->withContent('connections', $this->rtmOps->getActiveConnections())->write($response);
    }

    public function getPublicChannelStats(Request $request, Response $response): Response
    {
        $data = $this->dataController->getPublicChannelStats();
        $stats = [
            'total' => count($data),
            'active24' => count(array_filter($data, function ($row) {
                return !empty($row['latest_message_on']) && !Carbon::createFromFormat('Y-m-d H:i:s', $row['latest_message_on'])->addHours(24)->isPast();
            })),
            'active48' => count(array_filter($data, function ($row) {
                return !empty($row['latest_message_on']) && !Carbon::createFromFormat('Y-m-d H:i:s', $row['latest_message_on'])->addHours(48)->isPast();
            })),
            'active_week' => count(array_filter($data, function ($row) {
                return !empty($row['latest_message_on']) && !Carbon::createFromFormat('Y-m-d H:i:s', $row['latest_message_on'])->addWeek()->isPast();
            })),
            'max_messages' => array_reduce($data, function($carry, $row) {
                $current = (int) $row['message_count'];
                return $current > $carry ? $current : $carry;
            }, 0),
            'avg_messages' => array_reduce($data, function($carry, $row) {
                    $current = (int) $row['message_count'];
                    return $carry + $current;
                }, 0) / count($data),
            'max_users' => array_reduce($data, function($carry, $row) {
                $current = (int) $row['user_count'];
                return $current > $carry ? $current : $carry;
            }, 0),
            'min_users' => array_reduce($data, function($carry, $row) {
                $current = (int) $row['user_count'];
                return ($carry === null || $current < $carry) ? $current : $carry;
            }, null),
            'avg_users' => array_reduce($data, function($carry, $row) {
                $current = (int) $row['user_count'];
                return $carry + $current;
            }, 0) / count($data),
        ];

        return JsonOutput::success()->withContent('stats', $stats)->write($response);
    }

    public function kafkaStats(Request $request, Response $response, array $params): Response
    {
        $command = $params['command'];

        $httpClient = new GuzzleClient();

        try {
            $res = $httpClient->request('GET', "http://kafka:3000/$command", [
                'query' => $request->getQueryParams(),
            ]);
        } catch (GuzzleException $e) {
            return JsonOutput::error("Failed to contact kafka: {$e->getMessage()}")->write($response);
        }
        $output = \GuzzleHttp\json_decode($res->getBody()->getContents());

        return JsonOutput::success()->withContent('data', $output)->write($response);
    }

    public function zooTree(Request $request, Response $response, array $params): Response
    {

        $params = $request->getQueryParams();
        $path = '/' . ($params['path'] ?? '');

        try {
            $children = $this->zooService->getChildren($path);
        } catch (\Throwable $e) {
            return JsonOutput::error("Zoo-node not found", 404)->write($response);
        }

        if (!empty($children)) {
            return JsonOutput::success()->withContent('items', $children)->write($response);
        }

        // no children, so check the value of the key
        try {
            $value = $this->zooService->get($path);
        } catch (\Throwable $e) {
            return JsonOutput::error("Zoo-node not found", 404)->write($response);
        }

        return JsonOutput::success()->withContent('value', $value)->write($response);

    }

    public function getElasticIndices(Request $request, Response $response, array $params): Response
    {
        $indices = $this->elasticClient->cat()->indices();
        return JsonOutput::success()->withContent('indices', $indices)->write($response);
    }

    public function queryElasticIndex(Request $request, Response $response, array $params): Response
    {
        $params = $request->getQueryParams();

        $index = $params['index'] ?? null;
        if ($index === null) {
            return JsonOutput::error('Index is required', 422)->write($response);
        }

        $from = $params['from'] ?? 0;
        $size = $params['size'] ?? 30;

        $query = $params['query'] ?? null;

        if ($query === null) {
            // no query, so try the field/term approach
            $field = $params['field'] ?? null;
            $term = $params['term'] ?? null;
            if ($field !== null && $term !== null) {
                $query = [
                    'match' => [
                        $field => $term
                    ]
                ];
            } else {
                $query = [
                    'match_all' => new \StdClass(),
                ];
            }
        }

        $params = [
            'index' => $index,
            'body'  => [
                'from' => $from,
                'size' => $size,
                'query' => $query,
            ]
        ];

        $searchResult = $this->elasticClient->search($params);

        $output = [
            'total' => $searchResult['hits']['total']['value'],
            'from' => $from + 1,
            'to' => $from + $size,
            'entries' => $searchResult['hits']['hits'],
        ];

        return JsonOutput::success()->withContent('results', $output)->write($response);

    }
}