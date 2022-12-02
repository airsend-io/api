<?php

namespace CodeLathe\Core\Data\Migrations;

use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractMigration
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * @var string
     */
    protected $database;

    /**
     * AbstractMigration constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param ServiceRegistryInterface $config
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, ServiceRegistryInterface $config)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Returns a short textual description about what the migration is doing on the database
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Executes the migration (be sure to handle every possible exception, returning only true or false)
     * Place the errors on the log.
     * This method takes care of database version update on the versions table
     *
     * @param DatabaseService $dbs
     * @return bool
     */
    abstract public function handle(DatabaseService $dbs): bool;

    public function execute(): bool
    {
        if (preg_match('/(FS)?DBVersion([0-9]+)$/', get_class($this), $matches)) {

            if ($matches[1] === 'FS') {

                // storage DB
                $dbs = $this->container->get(FSDatabaseService::class);

            } else {

                // cloud DB
                $dbs = $this->container->get(DatabaseService::class);

            }

            // set admin creds to change database structure
            $dbAdminUser = $this->config->get('/db/cloud_db_root/user');
            $dbAdminPass = $this->config->get('/db/cloud_db_root/password');
            $dbs->setCredentials($dbAdminUser, $dbAdminPass);

            // set the database name
            $result = $dbs->selectOne('SELECT DATABASE() AS "database";');
            $this->database = $result['database'] ?? '';

            $migrationId = $matches[2];

            try {
                if ($this->handle($dbs)) {
                    return $dbs->executeStatement('INSERT INTO versions(id, notes) VALUES (:id, :notes)', [
                        'id' => $migrationId,
                        'notes' => $this->getDescription()
                    ]);
                }
            } catch(Throwable $e) {
                echo "ERROR: " . get_class($e) . " -> {$e->getMessage()}";
                $this->logger->error($e->getMessage());
                return false;
            }
        }
        return false;

    }

}