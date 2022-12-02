<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application;

use CodeLathe\Service\ServiceRegistryInterface;

/**
 * Simple Config Registry Class
 * This manages all kinds of settings and configurations in the system
 * aka Windows Registry
 * In the future, this config registry can do more fancy things like
 * connect to a Apache ZooKeeper type service
 */
class ConfigRegistry implements ServiceRegistryInterface
{
    private $registry;

    public function __construct()
    {
        // load from config for now        
        $this->registry = require __DIR__ . '/../../config/asconfig.php';
    }

    /**
     * @param string $path
     * @param string $value
     * @return bool
     */
    public function createNode(string $path, string $value): bool
    {
        $this->registry[$path] = $value;
        return true;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deleteNode(string $path): bool
    {
        if (array_key_exists($path, $this->registry)) {
            unset($this->registry);
            return true;
        }

        return false;
    }

    /**
     * Check if Node Exists
     *
     * @param string $path
     * @return boolean
     */
    public function existsNode(string $path): bool
    {
        return array_key_exists($path, $this->registry);
    }

    public function getChildren(string $path): array
    {
        $children = array();
        foreach ($this->registry as $key => $value) {
            if (strstr($key, $path) !== false) {
                $children[$key] = $value;
            }
        }
        return $children;
    }

    /**
     * Sets the Node
     *
     * @param string $path
     * @param string $value
     * @return boolean
     */
    public function set(string $path, string $value): bool
    {
        $this->registry[$path] = $value;
        return true;
    }

    /**
     * Gets the node value for a provided path
     *
     * @param string $path
     * @return mixed
     */
    public function get(string $path)
    {
        return $this->registry[$path];
    }

    public function getDomainValues(string $domain, bool $includeDomain = false) {

        $output = [];
        foreach ($this->registry as $key => $entry) {
            $regex = '/^' . preg_quote($domain, '/') . '(.*)/';
            if (preg_match($regex, $key, $matches)) {
                if ($includeDomain) {
                    $output[$key] = $entry;
                } else {
                    $output[$matches[1]] = $entry;
                }
            }
        }
        return $output;

    }

    /**
     * Checks if the Offset Exists
     *
     * @param [type] $offset
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return $this->existsNode($offset);
    }

    public function offsetGet($offset)
    {
        return $this->registry[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->deleteNode($offset);
    }
}
