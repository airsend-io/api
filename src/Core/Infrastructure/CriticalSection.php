<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Infrastructure;

/*
 * Critical Sections are useful when you want to make sure you are doing atomic operations
 * and don't want another request to execute and preempt you in between
 * For example:
 *            VALUE = READ VALUE FROM DB
 *            VALUE = VALUE + 1;
 *            WRITE VALUE TO DB
 *
 * Suppose, another request meanwhile writes the resource value between the read and write values
 * then the value you just wrote corrupts the state
 *
 * $autoexpirytime = 10; // seconds after acquire where the critical section lapses
 * $timetowait = 10; // seconds to wait to enter the critical section
 * $acquired = $csm->acquireSection("fleecingsheep", $autoexpirytime, 10);
 * if ($acquired)
 * {
 *  /// DO YOUR CRITICAL STUFF
 *  csm->releaseSection();
 * }
 */

use CodeLathe\Core\Utility\Base64;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class CriticalSection {

    private static $lockedKeys = array();

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    public function __construct(LoggerInterface $logger, CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    private function getKeyName($name)
    {

        return 'airsend.csec.'. Base64::urlEncode($name);
    }

    private function tryAcquire($name, $who, $expiryofsection)
    {
        $keyName = $this->getKeyName($name);

        $item = $this->cache->getItem($keyName);
        if ($item->isHit()) {
            $csecrec = (array)$item->get();
            //$this->logger->debug(__CLASS__ . ":" . __FUNCTION__ ." csecrec=".print_r($csecrec, true));

            $lockedBy = $csecrec['who'];
            if ($lockedBy == $who)
            {
                //$this->logger->debug("CSEC $name Reacquire OK . locked by: ".$lockedBy." wanted by: ".$who);
                return true; // already acquired and not expired
            }

            //$this->logger->debug("CSEC $name locked by: ".$lockedBy." wanted by: ".$who);
            return false;
        }

        $csecrec = array('who' => $who);
        $item->set($csecrec);
        $item->expiresAfter($expiryofsection);
        $this->cache->save($item);

        //$this->logger->debug("ADD CSEC ".$name." who: ".$who);

        $item = $this->cache->getItem($keyName);
        if (!$item->isHit()) {
            return false; // What?? Inserted record went away?
        }

        $csecrec = (array)$item->get();
        $lockedBy = $csecrec['who'];

        if ($lockedBy == $who)
            return true;

        //$this->logger->debug("CSEC $name lock attempt failed, locked by: ".$lockedBy." wanted by: ".$who);
        return false; // ... Someone else got there first
    }


    /**
     *
     * Get exclusive lock
     *
     * @param $name - Unique label to serialize with
     * @param $sessionid
     * @param int $timecanwait - Max timeout in seconds waiting to get lock
     * @param int $autoexpirytime - Automatically release lock if not released after this time in seconds
     * @return bool True if lock is issued and false otherwise
     */
    public function acquireSection($name, $sessionid, $timecanwait=60, $autoexpirytime=3600): bool
    {
        $start = microtime(true);
        while (1)
        {
            $acquired = $this->tryAcquire($name, $sessionid, $autoexpirytime);
            if ($acquired) {
                //$this->logger->debug("CSEC $name Acquired OK by ".$sessionid." waited: ".(microtime(true) - $start)." secs");
                CriticalSection::$lockedKeys[] = $name;
                return true;
            }

            // .. We didn't get it can we wait.
            $end = microtime(true);
            $elapsed = $end - $start;
            if ($elapsed > $timecanwait) {
                $this->logger->info("CSEC: ".$name." Timedout who: ".$sessionid." Elapsed: ".$elapsed." Time can wait: ".$timecanwait);
                return false; // Timeout
            }

            time_nanosleep(0, 100 * 1000 * 1000); // Check after 100 ms

        }

    }

    public function execute(callable $callback, $name, $sessionid, $timecanwait=60, $autoexpirytime=3600): bool
    {
        if ($this->acquireSection($name, $sessionid, $timecanwait, $autoexpirytime)) {
            try {
                $callback();
            } catch (Throwable $e) {

                // if there is any unhandled exception...
                // ... log it as an error
                $this->logger->error("Unhandled exception on CS: [" . get_class($e) . "] {$e->getMessage()}");

                // ensure the CS release
                $this->releaseSection($name, $sessionid);

                // ... throw it to the caller
                throw $e;
            }

            // on success ensure the releasing of the CS and return true
            $this->releaseSection($name, $sessionid);
            return true;
        }
        return false;
    }

    private function cleanArray(string $keyName)
    {
        $found = array_search($keyName, CriticalSection::$lockedKeys);
        if ($found !== false) {
            array_splice(CriticalSection::$lockedKeys, $found, 1);
        }
    }

    /**
     *
     * Release previously acquired lock
     *
     * @param string $name
     * @param float $sessionid
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function releaseSection(string $name, float $sessionid)
    {
        $keyName = $this->getKeyName($name);
        $item = $this->cache->getItem($keyName);
        if (!$item->isHit()) {
            $this->cleanArray($name); // ... Clean up just in case
            return false;
        }
        $csecrec = (array)$item->get();
        $lockedBy = $csecrec['who'];
        if ($lockedBy == $sessionid) {
            $this->cache->deleteItem($keyName);
            $this->cleanArray($name);
            //$this->logger->debug("CSEC: ".$name." Released by : ".$sessionid);
            return true;
        }
        $this->logger->error("CSEC: ".$name." cannot be released by session who hasn't locked it: ".$sessionid." originally locked by ".$lockedBy);
        return false;
    }

    public function releaseAllForSession(float $sessionid)
    {
        if (sizeof(CriticalSection::$lockedKeys) > 0)
        {
            $this->logger->error("Pending Keys found for Release All for Section: ".$sessionid." ".print_r(CriticalSection::$lockedKeys, true));
            foreach (CriticalSection::$lockedKeys as $key => $value)
            {
                $this->releaseSection($value, $sessionid);
            }
        }
    }

}