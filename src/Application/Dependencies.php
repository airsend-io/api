<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use Carbon\CarbonInterval;
use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Core\Data\OAuth\AccessTokenDataStore;
use CodeLathe\Core\Data\OAuth\AuthCodeDataStore;
use CodeLathe\Core\Data\OAuth\ClientDataStore;
use CodeLathe\Core\Data\OAuth\RefreshTokenDataStore;
use CodeLathe\Core\Data\OAuth\ScopeDataStore;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Service\Auth\JwtService;
use CodeLathe\Service\Auth\JwtServiceInterface;
use CodeLathe\Service\Cron\CronService;
use CodeLathe\Service\Cron\CronServiceInterface;
use CodeLathe\Service\Database\FSDatabaseService;
use CodeLathe\Service\SMS\SMSService;
use CodeLathe\Service\SMS\SMSServiceInterface;
use CodeLathe\Service\EventDispatcher\EventDispatcherService;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\Cache\CacheSrvcs;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\Mailer\MailerService;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use CodeLathe\Service\MQ\MQService;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Service\Storage\StorageService;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use CodeLathe\Service\Storage\StorageServiceFactory;
use CodeLathe\Service\Zoo\ZooService;
use DI\ContainerBuilder;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use League\OAuth2\Client\Provider\Apple;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        ConfigRegistry::class => function () {
            return new ConfigRegistry();
        },
        ServiceRegistryInterface::class => function(ConfigRegistry $config) {
            return $config;
        },
        LoggerInterface::class => function (ServiceRegistryInterface $registry) {
            return new LoggerService($registry);
        },
        CacheItemPoolInterface::class => function (ContainerInterface $c) {
            $cacheService = $c->get(CacheSrvcs::class);
            return $cacheService;
        },
        EventDispatcherService::class => function (ContainerInterface $c) {
            $dispatchService = new EventDispatcherService($c->get(ConfigRegistry::class), $c->get(LoggerInterface::class));
            return $dispatchService;
        },
        MQService::class => function (ContainerInterface $c) {
            $mqService = new MQService($c->get(ConfigRegistry::class), $c->get(LoggerInterface::class));
            return $mqService;
        },
        ZooService::class => function (ContainerInterface $c) {
            $zooService = new ZooService($c->get(ConfigRegistry::class), $c->get(LoggerInterface::class));
            return $zooService;
        },
        DatabaseService::class => function (ContainerInterface $c) {
            $dbService = new DatabaseService($c->get(ConfigRegistry::class), $c->get(LoggerInterface::class));
            return $dbService;
        },
        FSDatabaseService::class => function (ContainerInterface $c) {
            $fsdbService = new FSDatabaseService($c->get(ConfigRegistry::class), $c->get(LoggerInterface::class));
            return $fsdbService;
        },
        StorageService::class => function (ContainerInterface $c) {
            $storageService = new StorageService($c->get(ConfigRegistry::class), $c->get(LoggerInterface::class),
                $c->get(FSDatabaseService::class));
            return $storageService;
        },
        StorageServiceInterface::class => function(FSDatabaseService $dbs, ConfigRegistry $config) {
            $factory = new StorageServiceFactory();
            return $factory->create(
                \CodeLathe\Service\Storage\Implementation\StorageService::class,
                new \CodeLathe\Service\Storage\Implementation\Data\MySQLItemDataStore($dbs),
                [
                    'S3_US_EAST1' => $config->getDomainValues('/storage/s3/')
                ]
            );
        },
        MailerServiceInterface::class => function (ServiceRegistryInterface $registry) {

            $driver = $registry->get('/mailer/driver');
            $fromDomain = $registry->get('/mailer/domain');
            $params = $registry->get("/mailer/drivers/$driver") ?? [];

            return new MailerService($driver, $params, $fromDomain);
        },
        SMSServiceInterface::class => function (ServiceRegistryInterface $registry) {
            $driver = $registry->get('/sms/driver');
            $params = $registry->get("/sms/drivers/$driver");
            return new SMSService($driver, $params);
        },
        JwtServiceInterface::class => function (ContainerInterface $c) {
            return $c->get(JwtService::class);
        },
        CronServiceInterface::class => function (ContainerInterface $c) {
            $tasks = require dirname(__DIR__, 2) . '/config/crontasks.php';
            return new CronService($tasks, $c->get(LoggerInterface::class));
        },
        CriticalSection::class => function(ContainerInterface $c) {
            $cs = new CriticalSection($c->get(LoggerInterface::class), $c->get(CacheItemPoolInterface::class));
            return $cs;
        },
        SearchServiceInterface::class => function (ContainerInterface $c) {
            return $c->get(SearchService::class);
        },
        SlugifyInterface::class => function(Slugify $slugifier) {
            $slugifier->addRule('\'', '');
            return $slugifier;
        },
        Translator::class => function() {
            $translatorLoader = new FileLoader(new Filesystem(), Directories::resources('lang'));
            $trans = new Translator($translatorLoader, 'en_US');
            $trans->setFallback('en_US');
            return $trans;
        },
        AuthorizationServer::class => function (ContainerInterface $c, ConfigRegistry $config) {
            $authorizationServer = new AuthorizationServer(
                $c->get(ClientDataStore::class),
                $c->get(AccessTokenDataStore::class),
                $c->get(ScopeDataStore::class),
                Directories::resources($config->get('/oauth/private_key_path')),
                \Defuse\Crypto\Key::loadFromAsciiSafeString($config->get('/oauth/encryption_key'))
            );
            $authorizationServer->setDefaultScope('user_info');

            // client credentials grant
            $authorizationServer->enableGrantType(
                new ClientCredentialsGrant(),
                CarbonInterval::fromString($config->get('/oauth/token_ttl'))->toDateInterval()
            );

            // Auth code Grant
            $codeGrant = new AuthCodeGrant(
                $c->get(AuthCodeDataStore::class),
                $c->get(RefreshTokenDataStore::class),
                CarbonInterval::fromString($config->get('/oauth/authorization_code_ttl'))->toDateInterval()
            );
            $codeGrant->setRefreshTokenTTL(
                CarbonInterval::fromString($config->get('/oauth/refresh_token_ttl'))->toDateInterval()
            );
            $authorizationServer->enableGrantType(
                $codeGrant,
                CarbonInterval::fromString($config->get('/oauth/token_ttl'))->toDateInterval()
            );

            // refresh token grant
            $refreshTokenGrant = new RefreshTokenGrant($c->get(RefreshTokenDataStore::class));
            $refreshTokenGrant->setRefreshTokenTTL(
                CarbonInterval::fromString($config->get('/oauth/refresh_token_ttl'))->toDateInterval()
            );
            $authorizationServer->enableGrantType(
                $refreshTokenGrant,
                CarbonInterval::fromString($config->get('/oauth/token_ttl'))->toDateInterval()
            );

            return $authorizationServer;
        },
        ResourceServer::class => function(ContainerInterface $c, ConfigRegistry $config) {
            // Setup the authorization server
            return new ResourceServer(
                $c->get(AccessTokenDataStore::class),
                Directories::resources($config->get('/oauth/public_key_path'))
            );
        },
        Client::class => function(ConfigRegistry $config) {
            return ClientBuilder::create()
                ->setHosts($config->get('/search/elastic_servers'))
                ->build();
        },
        Apple::class => function(ConfigRegistry $config) {
            [$clientId] = explode(',', $config->get('/apple/keys/id'));
            $keyFileId = $config->get('/apple/keys/key_file_id');
            return new Apple([
                'clientId'          => $clientId,
                'teamId'            => $config->get('/apple/keys/team_id'),
                'keyFileId'         => $keyFileId,
                'keyFilePath'       => Directories::resources("apple/keys/$keyFileId"),
            ]);
        },
    ]);
};
