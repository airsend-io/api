<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Database;

use CodeLathe\Service\Database\DBStatement;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use PDOException;
use Psr\Log\LoggerInterface;

/**
 * Describes a Service instance.
 */
class DatabaseService implements ServiceInterface
{
    /**
     * Database Handle - active PDO connection
     *
     * @var \PDO
     */
    protected $dbh;

    /**
     * Declare loggerservice variable
     *
     * @var LoggerService
     */
    protected $logger;

    protected $registry;

    protected $connectionString;


    /**
     * DatabaseService constructor.
     *
     * @param ServiceRegistryInterface $registry
     * @param LoggerInterface $logger
     * @param string $configprefix
     */
    public function __construct(ServiceRegistryInterface $registry, LoggerInterface $logger, $configprefix = '/db/core')
    {
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION
        ];
        $this->connectionString = $registry->get($configprefix.'/conn');
        $this->dbh      = new \PDO($this->connectionString, $registry->get($configprefix.'/user'), $registry->get($configprefix.'/password'), $options);
        $this->logger   = $logger;
        $this->registry = $registry;

        // ... Easier to create a custom PDOStatement class, but it means we cannot use persistent connections
        //$this->pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('\CodeLathe\Service\Database\DBStatement', array($this->pdo)));
    }

    public function setConnection($configPrefix = '/db/core')
    {
        $options = [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
        $this->connectionString = $this->registry->get($configPrefix.'/conn');
        $this->dbh = new \PDO($this->connectionString,
                                $this->registry->get($configPrefix.'/user'),
                                $this->registry->get($configPrefix.'/password'), $options);
    }

    public function setCredentials(string $user, string $password) {
        $options = [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
        $this->dbh = new \PDO($this->connectionString, $user,$password, $options);
    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return DatabaseService::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "Database Service provides a thin wrapper around MySQL";
    }

    /**
     * Run an insert statement in database
     *
     * @param $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = []) : int
    {
        return $this->executeAffectingStatement($query, $bindings);
    }

    /**
     * Run select statement and return array
     *
     * @param $query
     * @param array $bindings
     * @return array
     */
    public function select($query, $bindings = []) : array
    {
        // log the query
        $this->logQuery($query, $bindings);
        //$this->logger->debug($query . print_r($bindings,true));

        // Prepare the query statement
        $sth = $this->dbh->prepare($query);

        // Bind the parameter key/values
        $this->bindvalues($sth, $bindings);

        // Execute the statement
        $sth->execute();

        // get result array
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Run an update statement in database
     *
     * @param $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = []) : int
    {
        return $this->executeAffectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement on database.
     *
     * @param $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = []) : int
    {
        return $this->executeAffectingStatement($query, $bindings);
    }

    /**
     * return last auto increment id
     *
     * @return string
     */
    public function lastInsertId() : string
    {
        return $this->dbh->lastInsertId();
    }

    /**
     * Run Select statement and return single result
     *
     * @param $query
     * @param array $bindings
     * @return array
     */
    public function selectOne($query, $bindings = []) : ?array
    {
        $records = $this->select($query, $bindings);
        return array_shift($records);
    }


    /**
     * Run select statement and return object of the class
     *
     * @param $query
     * @param $className
     * @param array $bindings
     * @return object of type class
     */
    public function selectObject($query, $className, $bindings = [])
    {
        // log the query
        $this->logQuery($query, $bindings);

        // Prepare the query statement
        $sth = $this->dbh->prepare($query);

        // Bind the parameter key/values
        $this->bindvalues($sth, $bindings);

        // Execute the statement
        $sth->execute();

        // return (yield) the object as it is retrieved.
        $sth->setFetchMode(\PDO::FETCH_CLASS, $className);

        return $sth->fetch();
    }

    /**
     * Run select statement and return object of the class
     *
     * @param $query
     * @param $className
     * @param array $bindings
     * @return iterable
     */
    public function selectObjects($query, $className, $bindings = []) : iterable
    {
        // log the query
        $this->logQuery($query, $bindings);

        // Prepare the query statement
        $sth = $this->dbh->prepare($query);

        // Bind the parameter key/values
        $this->bindvalues($sth, $bindings);

        // Execute the statement
        $sth->execute();

        // return (yield) the object as it is retrieved.
        while($object = $sth->fetchObject($className)){
            yield $object;
        }
    }

    /**
     * Run a select statement and return a iterator
     *
     * @param $query
     * @param array $bindings
     * @return iterable
     */
    public function cursor($query, $bindings = []) : \Generator
    {
        // log the query
        $this->logQuery($query, $bindings);

        // Prepare the query statement
        $sth = $this->dbh->prepare($query);

        // Bind the parameter key/values
        $this->bindvalues($sth, $bindings);

        // Execute the statement
        $sth->execute();

        while($record = $sth->fetch(\PDO::FETCH_ASSOC))
            yield $record;
    }

    /**
     * Run and execute a query
     *
     * @param $query
     * @param array $bindings
     * @return bool
     */
    public function executeStatement($query, $bindings = []) : bool
    {
        // log the query
        $this->logQuery($query, $bindings);

        // prepare the query
        $sth = $this->dbh->prepare($query);

        // bind the parameters key/value
        $this->bindvalues($sth, $bindings);

        // execute the query
        return $sth->execute();
    }

    /**
     * Run and execute a query and get rows affected.
     * If not error, returns number of rows
     * If error, returns -1
     *
     * @param $query
     * @param array $bindings
     * @return int
     */
    public function executeAffectingStatement($query, $bindings = []) : int
    {
        // log the query
        $this->logQuery($query, $bindings);

        // prepare the query
        $sth = $this->dbh->prepare($query);

        // bind the parameters key/value
        $this->bindvalues($sth, $bindings);

        // execute the query
        $result =  $sth->execute();
        if ($result !== false) {
            // return the rows affected
            return $sth->rowCount();
        }
        else {
            return -1;
        }
    }

    /**
     * Bind the Sql Parameters key/value/data type format
     *
     * @param $statement
     * @param array $bindings
     */
    public function bindValues($statement, $bindings = []) : void
    {
        foreach ($bindings as $key => $value) {
            if(is_int($value) || is_bool($value))
                $paramType = \PDO::PARAM_INT;
            elseif(is_null($value))
                $paramType = \PDO::PARAM_NULL;
            else
                $paramType = \PDO::PARAM_STR;
            //$paramType = is_bool($value) || is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $statement->bindValue($key, $value,$paramType);
        }
    }

    private function logQuery($query, $bindings = []) : void
    {
        $indexed=($bindings == array_values($bindings));
        foreach($bindings as $k => $v) {
            if(is_string($v)) $v = "'$v'";
            if($indexed)
                $query=preg_replace('/\?/',$v,$query,1);
            else
                $query=str_replace(":$k",$v,$query);
        }
        $formattedStmt = preg_replace('/\s+/', ' ', $query);
        $this->logger->debug($formattedStmt, ['EXT' => 'DB']);
    }

    /**
     * Executes the callback inside a transaction
     *
     * @param callable $callback
     * @param int $maxRetryOnDeadlock
     * @throws PDOException
     */
    public function transaction(callable $callback, int $maxRetryOnDeadlock = 5): void
    {

        // if there is already a transaction open, just execute the callback
        if ($this->inTransaction()) {
            $callback();
            return;
        }

        // otherwise handle the transaction
        $this->beginTransaction();
        $trials = 0;
        while(true) {
            try {
                $callback();
                $this->commit();
                return;
            } catch (PDOException $e) {
                $trials++;
                if ($trials > $maxRetryOnDeadlock) {
                    throw $e;
                }
            }
        }
    }

    public function inTransaction(): bool
    {
        return $this->dbh->inTransaction();
    }
    /**
     * Manually begins a transaction
     */
    public function beginTransaction(): void
    {
        $this->dbh->beginTransaction();
    }

    /**
     * Commits a transaction that was started with beginTransaction
     */
    public function commit()
    {
        $this->dbh->commit();
    }

    /**
     * Rollback a transaction that was started with beginTransaction
     */
    public function rollback()
    {
        $this->dbh->rollBack();
    }

}