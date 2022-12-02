<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\Mention;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Service\Database\DatabaseService;
use phpDocumentor\Reflection\Types\Iterable_;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class MentionDataStore
{

    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    /**
     * @var mixed|LoggerInterface
     */
    private $logger;

    /**
     * MentionDataStore constructor.
     *
     * @param DatabaseService $databaseService
     * @param LoggerInterface $logger
     */
    public function __construct(DatabaseService $databaseService, LoggerInterface $logger)
    {
        $this->dbs = $databaseService;
        $this->logger = $logger;
    }

    /**
     * @param Mention $mention
     * @return bool
     * @throws DatabaseException
     */
    public function create(Mention $mention) : bool
    {
        try {
            $sql = "INSERT INTO mentions
              SET                
                message_id = :message_id,   
                title = :title,
                resource_type = :resource_type,
                resource_id = :resource_id";
            $count = $this->dbs->insert($sql, $mention->getArray());
            $mention->setId((int)$this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }



}