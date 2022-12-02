<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Cache;

use CodeLathe\Service\ServiceRegistryInterface;
use Predis\Client;
use Psr\Log\LoggerInterface;


class CacheService
{
    /**
     * Declare Redis Client
     *
     * @var Client
     */
    protected $redis;

    /**
     * Declare loggerservice variable
     *
     * @var LoggerService
     */
    protected $logger;

    /**
     * DatabaseService constructor.
     *
     * @param ServiceRegistryInterface $registry
     * @param LoggerInterface $logger
     * @param string $configprefix
     */
    public function __construct(ServiceRegistryInterface $registry, LoggerInterface $logger)
    {
        $host = $registry->get('/cache/host');
        $port = (int)$registry->get('/cache/port');
        $this->redis = new Client(array(
            "scheme" => "tcp",
            "host" => $host,
            "port" => $port,
            "persistent" => "1"));
        $this->logger   = $logger;
    }

    public function get($key)
    {
        $value = $this->redis->get($key);
        return ! is_null($value) ? $this->unSerialize($value) : null;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        return (bool) $this->redis->setex(
            $key, (int) max(1, $seconds), $this->serialize($value)
        );
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return bool
     */
    public function forever($key, $value)
    {
        return (bool) $this->redis->set($key, $this->serialize($value));
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return (bool) $this->redis->del($key);
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $this->redis->flushdb();
        return true;
    }

    public function softFlush(array $except)
    {

        // merge all keys that match any of the patterns on the $except
        $exceptKeys = [];
        foreach ($except as $pattern) {
            $exceptKeys = array_merge($exceptKeys, $this->keys($pattern));
        }

        // go through each key that is not on the exceptKeys array, and forget it
        foreach (array_diff($this->keys(), $exceptKeys) as $key) {
            $this->forget($key);
        }
        return true;
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->redis->incrby($key, $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->redis->decrby($key, $value);
    }


    /**
     * Unserialize
     *
     * @param $value
     * @return int|mixed|string
     */
    private function unSerialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }

    /**
     * Save the array as a hash
     *
     * @param $key
     * @param array $value
     */
    public function setHash($key, array $value)
    {
        $this->redis->hmset($key, $value);
    }

    /**
     * Get hash array based on key
     *
     * @param $key
     * @return array     *
     */
    public function getHash($key)
    {
        return $this->redis->hgetall($key );
    }

    /**
     * Get a field value in a given hash
     *
     * @param $key
     * @param $field
     * @return string
     */
    public function getByHashField($key, $field)
    {
        return $this->redis->hget($key, $field);
    }

    /**
     * Scan the keys based on a pattern
     *
     * @param $keyPattern
     * @return array
     */
    public function scanKeys($keyPattern)
    {
        return $this->redis->scan(0, ['match' => $keyPattern]);
    }

    /**
     * add a new item to sorted list using zadd
     *
     * @param $key
     * @param $member
     * @param $value
     */
    public function addSortedListItem($key, $member, $value)
    {
        $this->redis->zadd($key, [$member => $value]);
    }

    /**
     * add multiple items to sorted list using array
     *
     * @param $key
     * @param $array
     */
    public function addSortedListItems($key, $array)
    {
        $this->redis->zadd($key, $array);
    }

    /**
     * Get a sorted list item based on Key and Member
     *
     * @param $key
     * @param $member
     * @return string
     */
    public function getSortedListItem($key, $member)
    {
        return $this->redis->zscore($key, $member);
    }

    /**
     * Delete sorted list item based on key and Member
     *
     * @param $key
     * @param $member
     */
    public function delSortedListItem($key, $member)
    {
        $this->redis->zrem($key, $member);
    }

    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    /**
     * @inheritDoc
     */
    function keys(string $pattern = '*'): array
    {
        return $this->redis->keys($pattern);
    }
}