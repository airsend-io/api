<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Realtime;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\RtmNotConfiguredException;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\ChatPostedEvent;
use CodeLathe\Core\Messaging\Events\UserOfflineEvent;
use CodeLathe\Core\Messaging\Events\UserOnlineEvent;
use CodeLathe\Core\Messaging\MessageQueue\MQDefs;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ClientApp;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedEvent;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Zoo\ZooService;
use Exception;
use Firebase\JWT\JWT;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class RtmOperations
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var ZooService
     */
    protected $zooService;

    /**
     * ChannelManager constructor.
     * @param LoggerInterface $logger
     * @param CacheItemPoolInterface $cache
     * @param ConfigRegistry $config
     * @param DataController $dataController
     * @param EventManager $eventManager
     * @param ZooService $zooService
     */
    public function __construct(LoggerInterface $logger,
                                CacheItemPoolInterface $cache,
                                ConfigRegistry $config,
                                DataController $dataController,
                                EventManager $eventManager,
                                ZooService $zooService)
    {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->config = $config;
        $this->dataController = $dataController;
        $this->eventManager = $eventManager;
        $this->zooService = $zooService;
    }


    /**
     * Save a token to cache. This is a userID key
     *
     * @param RtmToken $token
     * @throws InvalidArgumentException
     */
    public function cacheRtmToken(RtmToken $token)
    {
        // This is a userID -> array of tokens map
        $item = $this->cache->getItem('airsend.rtm.uid.' . $token->userId());
        if (!$item->isHit()) {
            $ar = [$token];
            $item->set($ar);
        } else {
            $ar = $item->get();
            $ar[] = $token;
            $item->set($ar);
        }
        $this->logger->info("User: " . $token->userId() . " has " . count($ar) . " valid RTM connections");
        //$this->logger->info("Caching ".'airsend.rtm.uid.'.$token->userId() .'==> '. print_r($ar,true));

        $this->cache->save($item);
    }

    /**
     * Cleared expired tokens on Cron
     */
    public function onCron(): void
    {
       // Cleared expired tokens
        $cleared = 0;
        try {
            foreach ($this->dataController->getAllUsersIds() as $id) {
                $tokens = $this->getRtmTokens($id);
                if ($tokens !== null) {
                    foreach ($tokens as $token) {
                        if ($this->tokenExpired($token)) {
                            $this->removeRtmToken($token);
                            $cleared++;
                        }
                    }
                }
            }
        } catch (DatabaseException | InvalidArgumentException $e) {
            $this->logger->error(__FUNCTION__ . ':'  . $e->getMessage());
        }

        $this->logger->info('Cleared ' . $cleared . ' expired tokens');
    }

    /**
     *
     * Get cached token list for a user id
     *
     * @param $userId
     * @return RtmToken[]|null Array of RtmToken objects
     * @throws InvalidArgumentException
     */
    public function getRtmTokens(int $userId): ?array
    {
        $item = $this->cache->getItem('airsend.rtm.uid.' . $userId);
        if ($item->isHit()) {
            return (array)$item->get();
        }
        return null;
    }

    /**
     * Checks if the user is connected via RTM system
     * @param int $userId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isUserOnline(int $userId): bool
    {
        return $this->cache->getItem('airsend.rtm.uid.' . $userId)->isHit();
    }

    /**
     * @return int[]
     * @throws InvalidArgumentException
     * @throws \CodeLathe\Core\Exception\DatabaseException
     */
    public function getActiveConnections(): array
    {
        $connections = [
            'total' => 0,
            'max_time' => 0,
            'avg_time' => 0
        ];
        foreach ($this->dataController->getAllUsersIds() as $id) {
            $tokens = $this->getRtmTokens($id);
            if ($tokens !== null) {
                foreach ($tokens as $token) {

                    if ($this->tokenExpired($token)) {
                        $this->removeRtmToken($token);
                        continue;
                    }

                    $connections['total']++;
                    $app = ClientApp::identifyFromUserAgent($token->userAgent()) ?? 'web';
                    $connections[$app] = ($connections[$app] ?? 0) + 1;

                    // connection time
                    if (($createdTime = $token->createdOn()) !== null) {

                        $time = time() - $createdTime;

                        // max
                        if ($time > $connections['max_time']) {
                            $connections['max_time'] = $time;
                        }

                        // avg
                        $connections['avg_time'] = $connections['avg_time'] + (($time - $connections['avg_time']) / $connections['total']);

                    }
                }
            }
        }
        return $connections;
    }

    /**
     *
     * Clear a token cache
     *
     * @param string $userId
     * @throws InvalidArgumentException
     */
    public function clearRtmToken(string $userId)
    {
        $this->cache->deleteItem('airsend.rtm.uid.' . $userId);
    }

    /**
     * Remove a connection token
     *
     * @param RtmToken $token
     * @throws InvalidArgumentException
     */
    public function removeRtmToken(RtmToken $token)
    {
        // This is a userID -> array of tokens map
        $item = $this->cache->getItem('airsend.rtm.uid.' . $token->userId());
        if ($item->isHit()) {
            $ar = $item->get();

            $this->logger->info(__FUNCTION__ . " BEFORE: " . count($ar) . " connections for " . $token->userId());

            $newar = array();
            foreach ($ar as $savedToken) {

                if ($token->fingerPrint() != $savedToken->fingerPrint()) {
                    $newar[] = $savedToken;
                }
            }

            $this->logger->info(__FUNCTION__ . " AFTER: " . count($newar) . " connections for " . $token->userId());

            if (count($newar) > 0) {
                $item->set($newar);
                $this->cache->save($item);
            } else {
                $this->cache->deleteItem('airsend.rtm.uid.' . $token->userId());
            }
        } else {
            $this->logger->info(__FUNCTION__ . " No record of this user " . $token->userId() . " is found. Ignoring");
        }
    }

    /**
     *
     * Construct realtime message object for a given user. If the user has
     * active RTM connections, we will need to built one for each fo the RTM connection
     *
     * @param int $targetUserId : This is the target ID to send this message to
     * @param NormalizedEvent $event
     * @return array
     * @throws InvalidArgumentException
     */
    public function getRtmMessages(int $targetUserId, NormalizedEvent $event): array
    {
        $rtmTokens = $this->getRtmTokens($targetUserId);
        $rtmMessages = array();
        if (!empty($rtmTokens)) {
            foreach ($rtmTokens as $rtmToken) {
                if (!$this->tokenExpired($rtmToken)) {
                    // Create the message with the rtm tokens so that the RTM Node server can route it correctly
                    $rtmMessages[] = new RtmMessage($event, $rtmToken);
                }
            }
        }
        return $rtmMessages;
    }

    /**
     * Findn and retrieve the RTM (connection) token for a specific device fingerprint of a user
     * @param int $userId
     * @param string $fingerPrint
     * @return RtmToken
     * @throws InvalidArgumentException
     */
    public function getRtmTokenForFP(int $userId, string $fingerPrint): ?RtmToken
    {
        $rtmTokens = $this->getRtmTokens($userId);
        if (!empty($rtmTokens)) {
            foreach ($rtmTokens as $rtmToken) {
                if ($rtmToken->fingerPrint() === $fingerPrint) {
                    return $rtmToken;
                }
            }
        }
        return null;
    }

    /**
     *
     * Check if a token given out has
     *
     * @param RtmToken $rtmToken
     * @return bool
     * @throws InvalidArgumentException
     */
    private function tokenExpired(RtmToken $rtmToken): bool
    {
        return false;
        $now = date("Y-m-d H:i:s");
        if ($now > $rtmToken->expiry()) {
            $this->logger->info(__FUNCTION__ . " Token Expired" . $rtmToken->userId() . ':' . $rtmToken->fingerPrint());
            //Expired
            $this->removeRtmToken($rtmToken);
            return true;
        }
        return false;
    }

    /**
     *
     * Create a rtm response
     *
     * @param User $user
     * @param Auth $auth
     * @return RtmResponse
     * @throws InvalidArgumentException
     * @throws RtmNotConfiguredException
     */
    public function getRtmResponse(User $user, Auth $auth, string $endPoint = ''): RtmResponse
    {

        $timeOut = $auth->getExpirationTime()->format("Y-m-d H:i:s");
        $key = "fe95e913-ff6e-4b50-a700-6383b7c95d85";

        // Get the endpoint and its associated topic
        $endPoint = $this->getRTMEndPoint($endPoint);

        $token = RtmToken::create(
            $auth->getUserId(),
            $user->getDisplayName(),
            $auth->getClientIp(),
            $auth->getUserAgent(),
            $timeOut,
            StringUtility::generateRandomString(12),
            $endPoint['topic']
        );

        $jwt = JWT::encode($token->getArray(), $key);
        return new RtmResponse($endPoint['rtm_endpoint'], $jwt);
    }

    /**
     * @param string $token
     * @return RtmToken
     */
    public function validateRtmToken(string $token): ?RtmToken
    {
        $key = "fe95e913-ff6e-4b50-a700-6383b7c95d85";

        try {
            $payload = (array)JWT::decode($token, $key, array('HS256'));
            return RtmToken::withDBData($payload);
        } catch (\Exception $ex) {
            return null;
        }
    }

    /**
     * @return array
     * @throws RtmNotConfiguredException
     * @throws InvalidArgumentException
     */
    private function getRTMEndPoint(string $ep = ''): array
    {
        $endPoints = $this->getAvailableRTMEndpoints();

        $this->logger->info("Available endpoints " . print_r($endPoints, true));
        if (!empty($ep)) {
            $this->logger->info(' Attempting to return requested EP ' . $ep);
            foreach ($endPoints as $endPoint) {
                if ($endPoint['rtm_endpoint'] == $ep) {
                    return $endPoint;
                }
            }
            // Cannot find this endpoint
            $this->logger->error(' Cannot find requested EP ' . $ep);
            return  ['rtm_endpoint' => '', 'topic' => ''];
        }
        return $this->getLoadBalancedEndPoint($endPoints);
    }

    /**
     * @param string $internalWsHost
     * @return string
     */

    /**
     *
     * Get list of RTM nodes from Zookeeper.
     *
     * @return array
     * @throws RtmNotConfiguredException
     */
    private function getAvailableRTMEndpoints(): array
    {
        // Need to get the zoo rtm node
        $rtmNode = $this->config['/zoo/ws_nodes'];
        if ($this->zooService->exists($rtmNode)) {
            $endpoints = $this->zooService->getChildren($rtmNode);

            //$this->logger->info("ZOOKEEPER: " . print_r($endpoints, true));

            $rtm_endpoints = [];
            foreach ($endpoints as $endpoint) {
                $arr = explode('#', $endpoint);
                if (count($arr) != 4) {
                    $this->logger->error("Invalid node found in RTM - IGNORING" . $endpoint);
                    continue;
                }

                $wsHost = $arr[0] . "://" . $arr[1] . ":" . $arr[2];
                $wsTopic = $arr[3];
                $rtm_endpoints[] = ['rtm_endpoint' => $wsHost, 'topic' => $wsTopic];
            }
            if (empty($rtm_endpoints)) {
                $this->logger->error(__FUNCTION__ . " RTM NODE INFORMATION EMPTY. No valid RTM NODES found");
                throw new RtmNotConfiguredException("Server Configuration Error");
            }

            return $rtm_endpoints;
        } else {
            $this->logger->error(__FUNCTION__ . " RTM NODE INFORMATION NOT FOUND. Server MISCONFIGURATION");
            throw new RtmNotConfiguredException("Server Configuration Error");
        }
    }

    /**
     *
     * Select a end point to use based on the load on each of the node.
     *
     * @param array $rtmEndPoints
     * @return mixed
     * @throws InvalidArgumentException
     */
    private function getLoadBalancedEndPoint(array $rtmEndPoints)
    {
        // It is guaranteed endpoints is not empty
        if (count($rtmEndPoints) == 1) {
            return $rtmEndPoints[0];
        }

        $topicLoadMap = $this->getTopicLoadMap();
        $this->logger->info(__FUNCTION__ . " Topic Load map = " . print_r($topicLoadMap, true));
        if (empty($topicLoadMap)) {
            $this->updateTopicLoad($rtmEndPoints[0]['topic'], 1);
            return $rtmEndPoints[0];
        }

        $mapEp = [];
        foreach ($rtmEndPoints as $ep) {
            $mapEp[$ep['topic']] = $ep['rtm_endpoint'];
        }

        //$this->logger->info(print_r($topicLoadMap,true));

        $lowestLoadTopic = '';
        $lowestConnection = 0;
        foreach ($topicLoadMap as $topic => $connection) {
            //$this->logger->info("Topic = $topic Connections = $connection Lowest = $lowestConnection");
            if (!in_array($topic, array_keys($mapEp))) {
                continue;
            }

            if ($lowestLoadTopic == '') {
                $lowestConnection = $connection;
                $lowestLoadTopic = $topic;
            }

            if ((int)$connection < (int)$lowestConnection) {
                $lowestConnection = $connection;
                $lowestLoadTopic = $topic;
            }
        }
        $this->logger->info("Lowest loaded topic [$lowestLoadTopic] with connection count of [$lowestConnection]");

        $this->updateTopicLoad($lowestLoadTopic, ++$lowestConnection);
        return ['topic' => $lowestLoadTopic, 'rtm_endpoint' => $mapEp[$lowestLoadTopic]];
    }

    /**
     *
     * Stat record has one stat map per node.
     * Constructs a map of topic name = number of connections
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function getTopicLoadMap(): array
    {
        $key = 'airsend_ws_connections';
        $item = $this->cache->getItem($key);
        $statArray = [];

        if ($item->isHit()) {
            foreach ($item->get() as $key => $val) {
                $statArray[$key] = $val;
            }
        }

        return $statArray;
    }

    /**
     * @param string $topic
     * @param int $load
     * @throws InvalidArgumentException
     */
    private function updateTopicLoad(string $topic, int $load): void
    {
        $this->logger->info('Setting topic ' . $topic . ' count to ' . $load);
        $key = 'airsend_ws_connections';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $map = $item->get();
            $map[$topic] = $load;
            $item->set($map);
        }
        else {
            $val = [$topic => $load];
            $item->set($val);
        }

        $this->cache->save($item);
    }

}