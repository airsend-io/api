<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\Phone;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class PhoneDataStore
{
    /**
     * Declare Database Service
     *
     * @var DatabaseService|mixed
     */
    private $dbs;

    private $logger;

    /**
     * UserDataStore constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->dbs = $container->get(DatabaseService::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * @param Phone $phone
     * @return bool
     * @throws DatabaseException
     */
    public function createPhone(Phone $phone)
    {
        try {
            $sql = "INSERT INTO phones
                SET 
                  is_valid              = :is_valid,
                  `number`              = :number,
                  local_format          = :local_format,
                  intl_format           = :intl_format,
                  country_prefix        = :country_prefix,
                  country_code          = :country_code,
                  country_name          = :country_name,
                  location              = :location,
                  carrier               = :carrier,
                  line_type             = :line_type,
                  updated_on            = :updated_on;";
            $count = $this->dbs->insert($sql, $phone->getArray());
            $phone->setId($this->dbs->lastInsertId());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param Phone $phone
     * @return bool
     * @throws DatabaseException
     */
    public function updatePhone(Phone $phone)
    {
        try {
            $sql = "update phones
                SET 
                  is_valid              = :is_valid,
                  `number`              = :number,
                  local_format          = :local_format,
                  intl_format           = :intl_format,
                  country_prefix        = :country_prefix,
                  country_code          = :country_code,
                  country_name          = :country_name,
                  location              = :location,
                  carrier               = :carrier,
                  line_type             = :line_type,
                  updated_on            = :updated_on
                WHERE
                  id                    = :id;";
            $count = $this->dbs->insert($sql, $phone->getArray());
            return $count === 1;
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * @param string $number
     * @return Phone|null
     * @throws DatabaseException
     */
    public function getPhone(string $number) : ?Phone
    {
        try {
            $sql = "SELECT * FROM phones 
                WHERE `number` = :number OR local_format = :number or intl_format = :number;";
            $record = $this->dbs->selectOne($sql, ['number' => $number]);
            return empty($record) ? null : Phone::withDBData($record);
        }
        catch(\PDOException $e){
            $this->logger->error(print_r($e->errorInfo,true));
            throw new DatabaseException($e->getMessage());
        }
    }
}