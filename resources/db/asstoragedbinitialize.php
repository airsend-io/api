<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use CodeLathe\Service\ServiceRegistryInterface;
/*
 * Run this script to initialize a fresh database asclouddb and corresponding tables
 *
 * WARNING: Existing asclouddb will be dropped and you will loose all tables and records.
 *
 * Usage:
 * From the local system go to docker api container with the command
 *      docker-compose exec api bash
 * Ensure you are in /var/ww/dev folder
 * Run composer asclouddbinit
 */
if(php_sapi_name() == "cli") {

    // cli output functions
    require_once dirname(__FILE__) . '/output.php';

    echo PHP_EOL;

    try {
        /** @var ContainerInterface $container */
        $registry = $container->get(ServiceRegistryInterface::class);
        $rootConn = $registry->get('/db/cloud_db_root/conn');
        $rootUser = $registry->get('/db/cloud_db_root/user');
        $rootPassword = $registry->get('/db/cloud_db_root/password');

        $asStoragedbConn = $registry->get('/db/fs/conn');
        $asStoragedbUser = $registry->get('/db/fs/user');
        $asStoragedbPassword = $registry->get('/db/fs/password');

        $dbh = new PDO($rootConn, $rootUser, $rootPassword);

        if ($dbh->query("SHOW DATABASES LIKE 'asstoragedb';")->rowCount() > 0 && !in_array('--fresh', $argv)) {
            output(true, 'DB asstoragedb already exists. Skipping');
            exit(0);
        }


        $sql = "SET sql_safe_updates=0;";
        $r = $dbh->exec($sql);
        $message = "Safe Sql Updates set to false";
        output($r === 0, $message);


        $sql = "SELECT count(*) FROM mysql.user where User = '" . $asStoragedbUser . "';";
        $r = $dbh->query($sql);
        if ($r !== false) {
            $total = $r->fetchColumn();
            if ($total != 0) {
                $sql = "DROP USER '" . $asStoragedbUser . "'@'%';";
                $sql .= "REVOKE ALL PRIVILEGES, GRANT OPTION FROM '". $asStoragedbUser . "'@'%';";
                $sql .= "FLUSH PRIVILEGES;";
                $r = $dbh->exec($sql);
                $message = "Drop User and Revoke privileges to user $asStoragedbUser";
                output($r, $message);
            }
        }
        else {
            // ... Check permission in $dbh must be root user or user with enough permission
            $message = "Select user $asStoragedbUser from mysql.user";
            output($r, $message);
        }

        $sql = "DROP DATABASE IF EXISTS asstoragedb;";
        $r = $dbh->exec($sql);
        $message = "Drop Database asstoragedb";
        output($r !== false, $message);

        /*
         create database if it does not exists
         Set character set udf8mb4 to accomodate different languages
         Set collation to utf8mb4_unicode_ci to be more accurate in comparisons.
         Set no encryption
        */
        $sql = <<<EOSQL
CREATE DATABASE IF NOT EXISTS asstoragedb
CHARACTER SET = utf8mb4
COLLATE = utf8mb4_bin;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Create Database asstoragedb";
        output($r !== false, $message);

        /*
        Grant Privileges to all objects and  specific actions in ascloud db
        For create, alter tables and databases use root account directly
        on the db server.

        Following are possible privileges
        ALTER, ALTER ROUTINE, CREATE, CREATE ROUTINE, CREATE TEMPORARY TABLES,
        CREATE VIEW, DELETE, DROP, EVENT, EXECUTE, INDEX, INSERT, LOCK TABLES,
        REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE
        */

        $sql = "CREATE USER '" . $asStoragedbUser . "'@'%' IDENTIFIED WITH mysql_native_password 
                BY '" . $asStoragedbPassword . "';";
        $sql .= "GRANT SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE TEMPORARY TABLES, INDEX, LOCK TABLES, REFERENCES, DROP
	            ON asstoragedb.*
                TO '". $asStoragedbUser . "'@'%'
                WITH GRANT OPTION;";
        $sql .= "FLUSH PRIVILEGES;";

        $r = $dbh->exec($sql);
        $message = "Create User and Grant Privilege to user $asStoragedbUser";
        output($r === 0, $message);

        $sql = "USE asstoragedb;";
        $r = $dbh->exec($sql);
        $message = "Use database asstoragedb";
        output($r === 0, $message);

        $sql = <<<EOSQL
CREATE TABLE IF NOT EXISTS items(
    id INT( 20 ) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    fileid CHAR(50),
    creationdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificationdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastaccessdate TIMESTAMP NULL DEFAULT NULL,
    versioneddate TIMESTAMP  NULL DEFAULT NULL,
    complete BOOLEAN DEFAULT 0,
    extension CHAR(10),
    type CHAR(25) NOT NULL,
    size INT(20),
    syncversion DECIMAL(20,0),
    owner VARCHAR(128),
    name VARCHAR(255),                        
    parentpath VARCHAR(750),
    storagepath VARCHAR(255),
    storagezoneid VARCHAR(64),
    backstoredata JSON DEFAULT NULL,
    sidecarmetadata VARCHAR(255) DEFAULT NULL,
    INDEX idx_items_parentpath_name_type(parentpath(100), name(50), type)              
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table ITEMS";
        output($r !== false, $message);

        $sql = <<<EOSQL
CREATE TABLE IF NOT EXISTS deferreddeleteitems(
    id INT( 20 ) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    fileid CHAR(50),
    creationdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificationdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lastaccessdate TIMESTAMP NULL DEFAULT NULL,
    versioneddate TIMESTAMP  NULL DEFAULT NULL,
    deleteddate TIMESTAMP  NULL DEFAULT NULL,
    complete BOOLEAN DEFAULT 0,
    extension CHAR(10),
    type CHAR(25) NOT NULL,
    size INT(20),
    syncversion DECIMAL(20,0),
    owner VARCHAR(128),
    name VARCHAR(255),                        
    parentpath VARCHAR(1024),
    storagepath VARCHAR(255),
    storagezoneid VARCHAR(64),
    backstoredata JSON DEFAULT NULL,
    sidecarmetadata VARCHAR(255) DEFAULT NULL                                                
)ENGINE = INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Create Table DEFERREDDELETEITEMS";
        output($r, $message);

        $sql = <<<EOSQL
CREATE TABLE IF NOT EXISTS itemmetatags(
    id INT( 20 ) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    fileid CHAR(50),
    metatags JSON DEFAULT NULL                                              
)ENGINE = INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Create Table ITEMMETATAGS";
        output($r, $message);

        $sql = <<<EOSQL
CREATE TABLE IF NOT EXISTS versions(
	id INT AUTO_INCREMENT PRIMARY KEY,
    notes VARCHAR(255) NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Create Table versions";
        output($r, $message);

        $sql = "INSERT INTO versions(notes) VALUES(\"storage db schema initialized\");";
        $r = $dbh->exec($sql);
        $message = "Set db version to 1";
        output($r, $message);

        output(true, 'Starting migrations');
        $shouldExecute = true;
        $onlyStorageDb = true;
        require_once dirname(__FILE__) . '/dbmigrate.php';
        output(true, 'Migrations executed');

        $message = "Database asstoragedb initialized with user $asStoragedbUser";
        echo  $message . addPeriods(60 - strlen($message)) . "DONE \n";
        exit(0);

    } catch (PDOException $e) {
        echo $e->getMessage();
        exit(1);
    }
}
else{
    echo "<html><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
