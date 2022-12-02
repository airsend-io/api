<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Database;

use CodeLathe\Service\Database\DBStatement;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Describes a Service instance.
 */
class RootDatabaseService extends DatabaseService
{
    public function __construct(ServiceRegistryInterface $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, $logger, '/db/cloud_db_root');
    }

}