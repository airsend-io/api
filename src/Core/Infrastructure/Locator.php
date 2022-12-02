<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Infrastructure;

use CodeLathe\Service\Zoo\ZooService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Locator
 *
 * The Locator is a distributed service or a distributed end point locater.
 *
 * Say you have two or more distributed endpoints that can be active, and you need to list these or select one in a round robin fashion,
 * the locator can accomplish that.
 *
 * 1. The end points need to register with ZooKeeper using a node path say /airsend.servers.ws_servers with any name
 *    They should use ephemeral and sequence values (so entries are removed when endpoint dies and the names are automatically appended with a incrementing integer.
 *
 * 2. The locator then can be used to get list of all available endpoints or one endpoint in a roundrobin basis
 *
 * @package CodeLathe\Core\Infrastructure
 */
class Locator
{
    private $zoo;
    private $logger;
    private $cache;

    public function __construct(LoggerInterface $logger, ZooService $zoo, CacheItemPoolInterface $cache)
    {
        $this->zoo = $zoo;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * @param string $path The service endpoint registry node under which services are registed. e.g. '/airsend.servers.ws_servers'
     * @param string $value The configuration value to write
     * @param string $childSuffix optional suffix to use, the default is child_
     * @return bool
     */
    public function registerEndPoint(string $path, string $value, string $childSuffix = "") : bool
    {
        // ... Create Parent Node if it doesn't exist
        if (!$this->zoo->exists($path)) {
            $setPath = $this->zoo->create($path, '1');
            if ($setPath === false)
            {
                $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Failed to create path " . $path);
                return false;
            }
        }

        if ($childSuffix == "")
            $childSuffix = "child_";
        $keyName = $childSuffix;

        if (!$this->zoo->create($path.'/'.$keyName, $value, array(), \Zookeeper::EPHEMERAL|\Zookeeper::SEQUENCE)) {
            $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ . " Failed to register endpoint " . $path.'/'.$keyName);
            return false;
        }

        return true;
    }

    /**
     * @param string $path The service endpoint registry node under which services are registed. e.g. '/airsend.servers.ws_servers'
     * @param array $endpoints
     * @return bool if endpoints are available, false otherwise
     */
    public function getAllEndPoints(string $path, array &$endpoints):bool
    {
        try {
            $children = $this->zoo->getChildren($path);
            if ($children === false) {
                $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ . " Failed to get children for " . $path);
                return false;
            }

            foreach ($children as $child) {
                $childPath = $path . '/' . $child;
                if ($this->zoo->exists($childPath)) {
                    $value = $this->zoo->get($path . '/' . $child);
                    $endpoints[$child] = $value;
                }
            }

        } catch (Exception $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Exception Failed " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param string $path The service endpoint registry node under which services are registed. e.g. '/airsend.servers.ws_servers'
     * @return array|bool returns a single array item with a child name and value, false otherwise
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getRoundRobinEndPoint(string $path)
    {
        $endpoints = array();
        if (!$this->getAllEndPoints($path, $endpoints))
        {
            return false;
        }

        $count = count($endpoints);
        if ($count == 0)
            return false;
        // get last used endpoint index from redis
        // if we didn't find index, return the first one and save the index

        $idx = 0; // default index
        // TODO: Make the locator endpoint unique for a path
        $keyName = str_replace($path, '/', '>');
        $item = $this->cache->getItem($keyName);
        if ($item->isHit()) {
            $idx = (int)$item->get();
            $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ ." Last Used idx=".$idx);
            $idx++;
            if ($idx > ($count-1))
                $idx = 0;
        }

        $item->set($idx);
        $this->cache->save($item);

        $this->logger->debug(__CLASS__ . ":" . __FUNCTION__ ." Using idx=".$idx);

        return array_slice($endpoints, $idx, 1, true);
    }
}