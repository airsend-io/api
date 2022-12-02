<?php

namespace CodeLathe\Core\DW\FactMigrations;

use CodeLathe\Service\ServiceRegistryInterface;
use Elasticsearch\Client as ElasticClient;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractMigration
{

    const CUBES_VERSIONS_INDEX_NAME = 'cubes_versions';

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
     * @var ElasticClient
     */
    protected $elasticClient;

    /**
     * @var string
     */
    protected $indexName;

    /**
     * AbstractMigration constructor.
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @param ServiceRegistryInterface $config
     */
    public function __construct(ContainerInterface $container,
                                LoggerInterface $logger,
                                ServiceRegistryInterface $config,
                                ElasticClient $elasticClient)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->config = $config;
        $this->elasticClient = $elasticClient;
    }

    /**
     * Returns a short textual description about what the migration is doing on the cube
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Executes the migration (be sure to handle every possible exception, returning only true or false)
     * Place the errors on the log.
     *
     * @return bool
     */
    abstract protected function handle(): bool;

    public function execute(string $indexName): bool
    {

        $this->indexName = $indexName;

        if (preg_match('/Version([0-9]+)$/', get_class($this), $matches)) {

            $version = (int)$matches[1];

            try {
                if ($this->handle()) {

                    $this->elasticClient->update([
                        'index' => static::CUBES_VERSIONS_INDEX_NAME,
                        'id'    => $indexName,
                        'body'  => [
                            'doc' => [
                                'version' => $version,
                            ],
                            'upsert' => [
                                'version' => $version,
                            ],
                        ]
                    ]);

                    return true;
                }
            } catch(Throwable $e) {
                $this->logger->debug($e->getMessage());
                return false;
            }
        }
        return false;

    }

}