<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Zoo;

use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Describes a Service instance.
 */
class ZooService implements ServiceInterface
{
    private $zookeeper;
    private $loggerService;

    public function __construct(ServiceRegistryInterface $registry, LoggerInterface $loggerService)
    {
        $this->loggerService = $loggerService;
        $this->zookeeper = new \ZooKeeper($registry->get('/zoo/host'));
    }

    public function __destruct() {
        $this->zookeeper->close();
    }
    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return ZooService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "Zoo Service provides a thin wrapper around zookeeper";
    }

    public function create(string $path , string $value, array $acls = array(), int $flags = 0) : string
    {
        $acls = array(
            array(
                'perms'  => \Zookeeper::PERM_ALL,
                'scheme' => 'world',
                'id'     => 'anyone',
            )
        );
        return $this->zookeeper->create($path, $value, $acls, $flags);
    }

    public function delete(string $path) : bool
    {
        return $this->zookeeper->delete($path);
    }

    public function exists(string $path, callable $watcher = NULL)
    {
        return $this->zookeeper->exists($path, $watcher);
    }

    public function get(string $path, callable $watcher = NULL) : ?string
    {
        return $this->zookeeper->get($path, $watcher);
    }

    public function getChildren(string $path, callable $watcher = NULL) : array
    {
        return $this->zookeeper->getChildren($path, $watcher);
    }

    public function set(string $path, string $value)
    {
        if (!$this->zookeeper->exists($path)) {
            $this->makePath($path);
            $this->makeNode($path, $value);
        } else {
            $this->zookeeper->set($path, $value);
        }
    }

    public function makePath($path, $value = '') {
        $parts = explode('/', $path);
        $parts = array_filter($parts);
        $subpath = '';
        while (count($parts) > 1) {
            $subpath .= '/' . array_shift($parts);
            if (!$this->zookeeper->exists($subpath)) {
                $this->makeNode($subpath, $value);
            }
        }
    }

    public function makeNode($path, $value, array $params = array()) {
        if (empty($params)) {
            $params = array(
                array(
                    'perms'  => \Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id'     => 'anyone',
                )
            );
        }
        return $this->zookeeper->create($path, $value, $params);
    }



}