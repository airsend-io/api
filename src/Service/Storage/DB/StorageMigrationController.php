<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\DB;

use CodeLathe\Core\Data\VersionDataStore;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class StorageMigrationController
{
    /**
     * Declare container
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var DatabaseService|mixed
     */
    private $dbh;

    /**
     * @var mixed|LoggerInterface
     */
    private $logger;


    /**
     * DataController constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->dbh = $container->get(DatabaseService::class);
        $this->dbh->setConnection("/db/fs");
    }

    /**
     * Get Latest version as set in asconfig file
     *
     * @return int
     */
    public function getLatestVersion() : int
    {
        $registry = $this->container->get(ServiceRegistryInterface::class);
        return (empty($registry->get("/db/fs/version")) ? 1 : intval($registry->get("/db/fs/version")));
    }

    /**
     * Get Current Version from db
     *
     * @return int
     */
    public function getCurrentVersion() : int
    {
        return intval((new VersionDataStore($this->container))->getLatestVersion()->getId());
    }

    /**
     * Check if db upgrade is available
     *
     * @return bool
     */
    public function isUpgradeAvailable() : bool
    {
        return ($this->getCurrentVersion() < $this->getLatestVersion());
    }

    public function listVersions() : array
    {
        return (new VersionDataStore($this->container))->getVersions();
    }

    /**
     * upgrade the database
     */
    public function upgrade() : bool
    {
        $currentVersion = $this->getCurrentVersion();
        $latestVersion = $this->getLatestVersion();

        for($i = $currentVersion + 1; $i <= $latestVersion; $i++)
        {
            $func = 'upgradeToVersion' . $i;
            if (!$this->$func()) {
                return false;
                break;
            }
        }
        return true;
    }

    private function upgradeToVersion2()
    {
        //this is a placeholder function for future upgrades
        $this->logger->debug("Upgrading to version 2");
    }
}