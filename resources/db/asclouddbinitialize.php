<?php
/*******************************************************************************
Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/*
 * Run this script to initialize a fresh database asclouddb and corresponding tables
 *
 * WARNING: Existing asclouddb will be dropped and you will loose all tables and records.
 *
 * Usage:
 * From the local system go to docker api container with the command
 *      docker-compose exec api bash
 * Ensure you are in /var/ww/dev folder
 * Run composer run asclouddbinit
 */

use CodeLathe\Core\Data\CacheDataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Service\Cache\CacheService;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use CodeLathe\Service\ServiceRegistryInterface;

/** @var ContainerInterface $container */

if(php_sapi_name() == "cli") {

    require_once dirname(__FILE__) . '/output.php';

    echo PHP_EOL;

    try {

        $quiet = in_array('--quiet', $argv);

        $registry = $container->get(ServiceRegistryInterface::class);

        $rootConn = $registry->get('/db/cloud_db_root/conn');
        $rootUser = $registry->get('/db/cloud_db_root/user');
        $rootPassword = $registry->get('/db/cloud_db_root/password');

        $asClouddbConn = $registry->get('/db/core/conn');
        $asClouddbUser = $registry->get('/db/core/user');
        $asClouddbPassword = $registry->get('/db/core/password');

        // split the database name
        preg_match('/dbname=([^;]+);/', $asClouddbConn, $matches);
        $asClouddbName = $matches[1];

        $adminPassword = $registry->get('/app/admin/password');

        $dbh = new PDO($rootConn, $rootUser, $rootPassword);

        // should we force the recreation of the database? (tests database are always recreated)
        $fresh = in_array('--fresh', $argv) || $registry->get('/app/mode') === 'tests';

        if ($dbh->query("SHOW DATABASES LIKE '$asClouddbName';")->rowCount() > 0 && !$fresh) {
            output(true, "DB $asClouddbName already exists. Skipping");
            exit(0);
        }

        $sql = "SET sql_safe_updates=0;";
        $r = $dbh->exec($sql);
        $message = "Safe Sql Updates set to false";
        output(($r !== false), $message);

        $sql = "SELECT count(*) FROM mysql.user WHERE user = '$asClouddbUser';";
        $r = $dbh->query($sql);

        if ($r !== false) {
            $total = $r->fetchColumn();
            if ($total != 0) {
                $sql  = "DROP USER '" . $asClouddbUser . "'@'%';";
                $sql .= "REVOKE ALL PRIVILEGES, GRANT OPTION FROM '" . $asClouddbUser . "'@'%';";
                $sql .= "FLUSH PRIVILEGES;";
                $r = $dbh->exec($sql);
                $message = "Revoke privileges to user $asClouddbUser";
                output(($r !== false), $message);
            }
        } else {
            // ... Check permission in $dbh must be root user or user with enough permission
            $message = "Failed to check user existence";
            output(($r !== false), $message);
        }

        $sql = "DROP DATABASE IF EXISTS $asClouddbName;";
        $r = $dbh->exec($sql);
        $message = "Drop database $asClouddbName";
        output(($r !== false), $message);

        /*
         create database if it does not exists
         Set character set udf8mb4 to accomodate different languages
         Set collation to utf8mb4_unicode_ci to be more accurate in comparisons.
         Set no encryption
        */
        $sql = <<<EOSQL
CREATE DATABASE IF NOT EXISTS $asClouddbName
CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Database $asClouddbName";
        output(($r !== false), $message);

        /*
        Grant Privileges to all objects and  specific actions in ascloud db
        For create, alter tables and databases use root account directly
        on the db server.

        Following are possible privileges
        ALTER, ALTER ROUTINE, CREATE, CREATE ROUTINE, CREATE TEMPORARY TABLES,
        CREATE VIEW, DELETE, DROP, EVENT, EXECUTE, INDEX, INSERT, LOCK TABLES,
        REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE
        */

        $sql = "CREATE USER '" . $asClouddbUser . "'@'%' IDENTIFIED WITH mysql_native_password 
                BY '". $asClouddbPassword ."';";
        $sql .= "GRANT SELECT, INSERT, UPDATE, DELETE, ALTER, CREATE TEMPORARY TABLES, INDEX, LOCK TABLES, REFERENCES, DROP
	            ON $asClouddbName.*
                TO '" . $asClouddbUser . "'@'%'
                WITH GRANT OPTION;";
        $sql .= "FLUSH PRIVILEGES;";
        $r = $dbh->exec($sql);
        $message = "Create User and Grant Privilege to user $asClouddbUser";
        output(($r !== false), $message);

        $sql = "USE $asClouddbName;";
        $r = $dbh->exec($sql);
        $message = "Use database $asClouddbName";
        output(($r !== false), $message);

        $databaseService = $container->get(DatabaseService::class);
        $logger = $container->get(LoggerInterface::class);
        $dc = new DataController($container);

        $cacheService = $container->get(CacheService::class);
        $cache = new CacheDataController($container);
        $cacheService->flush();

        $sql = <<<EOSQL
CREATE TABLE users (
	id 	BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NULL,
    phone VARCHAR(100) NULL,	
    `password` VARCHAR(255) NOT NULL,
	display_name VARCHAR(255) NOT NULL,
	has_avatar BOOLEAN NOT NULL DEFAULT 0,  
	user_role TINYINT NOT NULL, /* admin, subadmin, editor, viewer*/
    account_status TINYINT NOT NULL, /*pending verification, active, disabled, blocked*/  
    approval_status TINYINT NOT NULL, /* pending approval, approved */
    trust_level TINYINT NOT NULL,
    online_status TINYINT NOT NULL, /*active, away, offline, busy*/  
    is_auto_pwd BOOLEAN NOT NULL,     
    is_terms_agreed BOOLEAN NOT NULL,
    is_tour_complete BOOLEAN NOT NULL,   
    is_email_verified BOOLEAN NOT NULL DEFAULT 0,
    is_phone_verified BOOLEAN NOT NULL DEFAULT 0,        
    timezone VARCHAR(255) DEFAULT NULL,    
    locale VARCHAR(16) DEFAULT NULL,
    date_format VARCHAR(255) NULL,
    time_format VARCHAR(255) NULL,
    lang_code CHAR(2) NOT NULL DEFAULT 'en',   
    invited_by BIGINT UNSIGNED NULL,
    is_pwd_reset TINYINT NOT NULL DEFAULT 0,
    is_locked TINYINT NOT NULL DEFAULT 0,
    last_active_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notifications_config INT NOT NULL DEFAULT 3, /* binary digits that represents the notification config for the user */
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,    
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,	
    CONSTRAINT unique_users_email UNIQUE KEY(email),    
    CONSTRAINT unique_users_phone UNIQUE KEY(phone) ,   
    CONSTRAINT check_null_users_email_phone CHECK (email is not null or phone is not null),
    INDEX(last_active_on)
)ENGINE = INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table users";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE  external_identities (
	external_id VARCHAR(255) NOT NULL,
  	provider TINYINT NOT NULL,
	email VARCHAR(255) NOT NULL,	
    phone VARCHAR(100) NULL,    
	display_name VARCHAR(255) NOT NULL,
	profile_url VARCHAR(1000) NULL,
	user_id BIGINT UNSIGNED NOT NULL,	
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (external_id, provider),        
    CONSTRAINT unique_provider_email UNIQUE KEY(provider, email), 
    CONSTRAINT fk_external_identities_user_id  FOREIGN KEY (user_id) REFERENCES users(id)    
)ENGINE = INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table external_identities";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE teams(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(255) NOT NULL,         
    team_type TINYINT NOT NULL, /* personal, Regular */
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    CONSTRAINT unique_team_name UNIQUE KEY(team_name)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table teams";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE contact_forms(
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	owner_id BIGINT UNSIGNED NOT NULL,
	form_title VARCHAR(255) NOT NULL,
	confirmation_message TEXT NOT NULL,
	copy_from_channel_id BIGINT UNSIGNED DEFAULT NULL,
	enable_overlay BOOLEAN DEFAULT FALSE,
	enabled BOOLEAN DEFAULT TRUE,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   CONSTRAINT fk_contact_forms_users FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE 
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table contact_forms";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE channels(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	team_id BIGINT UNSIGNED NOT NULL,
	channel_name VARCHAR(255) NOT NULL,
	channel_email VARCHAR(255) NOT NULL, 
	blurb TEXT,  
	locale VARCHAR(16) DEFAULT NULL,
	default_joiner_role INTEGER DEFAULT NULL,
	default_invitee_role INTEGER DEFAULT NULL,
    channel_status TINYINT NOT NULL,   /*open, closed, archived*/    
    is_auto_closed TINYINT NOT NULL DEFAULT 0, /* autoclose, days, mode {1 - absolute,2 - nonactive}*/
    close_after_days INT NULL,        
    last_active_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    has_logo BOOLEAN NOT NULL DEFAULT 0,
    has_background BOOLEAN NOT NULL DEFAULT 0,  
    public_hash VARCHAR(32),
    public_url VARCHAR(255),  
    contact_form_id INT UNSIGNED DEFAULT NULL,   
    contact_form_filler_id BIGINT UNSIGNED DEFAULT NULL,
    one_one BOOLEAN DEFAULT FALSE,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    owned_by BIGINT UNSIGNED NOT NULL,
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,    
    CONSTRAINT fk_channels_team_id  FOREIGN KEY (team_id) REFERENCES teams(id),    
    CONSTRAINT unique_channel_email UNIQUE KEY(channel_email),
    CONSTRAINT unique_channel_name_team_id UNIQUE KEY(team_id, channel_name),
    CONSTRAINT fk_channels_contact_forms FOREIGN KEY (contact_form_id) REFERENCES contact_forms(id) ON DELETE CASCADE,
    CONSTRAINT fk_channels_filler_users FOREIGN KEY (contact_form_filler_id) REFERENCES users(id) ON DELETE CASCADE,
    FULLTEXT (channel_name)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table channels";
        output(($r !== false), $message);

        $sql = <<<EOSQL
    ALTER TABLE contact_forms ADD CONSTRAINT fk_contact_forms_channels FOREIGN KEY (copy_from_channel_id) REFERENCES channels(id) ON DELETE CASCADE
EOSQL;
        $dbh->exec($sql);


        $sql = <<<EOSQL
CREATE TABLE actions(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL, 
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    action_name VARCHAR(255) NOT NULL,
    action_desc VARCHAR(1000) NULL,
    action_type TINYINT NOT NULL,    
    action_status TINYINT NOT NULL,    
    order_position BIGINT NOT NULL,
    due_on DATETIME NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_actions_channel_id  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_actions_parent_id  FOREIGN KEY (parent_id) REFERENCES actions(id) ON DELETE CASCADE,
    FULLTEXT (action_name, action_desc)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table actions";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE team_users(	
    team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    user_role TINYINT NOT NULL, /* owner, member etc */
	created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,    
    PRIMARY KEY (team_id, user_id),
    CONSTRAINT fk_team_users_team_id  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_users_user_id  FOREIGN KEY (user_id) REFERENCES users(id)       
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table team_users";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE channel_users (	
    channel_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,    
    user_role TINYINT NOT NULL, /*owner, collaborator, viewer*/
    is_favorite BOOLEAN NOT NULL DEFAULT 0,
    notifications_config INT NOT NULL DEFAULT 3, /* binary digits that represents the notification config for the user */
    muted BOOLEAN DEFAULT FALSE,
    email_tag VARCHAR(8) NULL,    
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL, 
    PRIMARY KEY (channel_id, user_id),
    CONSTRAINT fk_channel_users_channel_id  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_channel_users_user_id  FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (created_on),
    INDEX (channel_id)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table channel_users";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE user_actions(
    user_id BIGINT UNSIGNED NOT NULL,	
    action_id BIGINT UNSIGNED NOT NULL, 
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL, 
	PRIMARY KEY (action_id, user_id),
    CONSTRAINT fk_action_users_action_id  FOREIGN KEY (action_id) REFERENCES actions(id) ON DELETE CASCADE,
    CONSTRAINT fk_action_users_user_id  FOREIGN KEY (user_id) REFERENCES users(id)       
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table user_actions";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE channel_paths(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    path_type TINYINT UNSIGNED NOT NULL,  /* file or wiki */  
    path_value VARCHAR(500) NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP ,    
    created_by BIGINT UNSIGNED NOT NULL, 
    CONSTRAINT fk_channel_paths_channel_id  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table channel_paths";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE messages(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,    
    display_name VARCHAR(255) NOT NULL,
    message_type TINYINT NOT NULL, /* admin message, new, reply, quote, forward*/
    content_text VARCHAR(5000) NULL,    
    attachments JSON NULL, /* path, content-type*/
    emoticons JSON NULL, /* userid, emoji */            
    is_edited BOOLEAN NOT NULL DEFAULT 0, /* edited */  
    is_deleted BOOLEAN NOT NULL DEFAULT 0, /* deleted */  
    parent_message JSON NULL,    
    source INT DEFAULT 0,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,    
    send_email BOOLEAN NOT NULL DEFAULT 0,
    CONSTRAINT fk_messages_channel_id  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_user_id  FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_messages_created_on (created_on)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table messages";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE timelines(
    channel_id BIGINT UNSIGNED NOT NULL,
	user_id BIGINT UNSIGNED NOT NULL,
	message_id BIGINT UNSIGNED NOT NULL,
	activity TINYINT NOT NULL,
	created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY(channel_id, user_id, message_id),
	CONSTRAINT fk_timelines_message_id  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,   
    CONSTRAINT fk_timelines_channel_id  FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_timelines_user_id  FOREIGN KEY (user_id) REFERENCES users(id)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table timelines";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE language_codes(
	lang_code CHAR(2) NOT NULL PRIMARY KEY,
    lang_name VARCHAR(100) NOT NULL
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table language_codes";
        output(($r !== false), $message);

        $sql = <<<EOSQL
create table user_codes (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    code_type TINYINT NOT NULL, /* emailverification, resetpassword, 2fa */
    code varchar(255) NOT NULL,    
    expires DATETIME,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_user_id_code_type UNIQUE KEY(user_id, code_type),    
	CONSTRAINT fk_codes_user_id  FOREIGN KEY (user_id) REFERENCES users(id)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table user_codes";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE user_passwords(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,
	CONSTRAINT fk_user_passwords_user_id  FOREIGN KEY (user_id) REFERENCES users(id)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table user_passwords";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE user_sessions(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    issuer VARCHAR(255) NOT NULL, /* usually url e.g. airsend.io */
    token VARCHAR(500) NOT NULL,
    ip varchar(15) NOT NULL,
    user_agent varchar(500) NOT NULL,
    expiry DATETIME NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_user_sessions_user_id  FOREIGN KEY (user_id) REFERENCES users(id)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table user_sessions";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE user_terms (
	user_id BIGINT UNSIGNED NOT NULL,
    version_number varchar(50) NOT NULL,
	ipv4  VARCHAR(15) NOT NULL,
    agent VARCHAR(255) NOT NULL,
    create_on DATETIME DEFAULT current_timestamp,
    CONSTRAINT fk_user_terms_user_id  FOREIGN KEY (user_id) REFERENCES users(id),
    PRIMARY KEY (user_id, version_number)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table user_terms";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE lookup_notification_frequency_map (
    notifications_sent_count INT NOT NULL PRIMARY KEY,
    minutes_to_wait INT NOT NULL
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table lookup_notification_frequency_map";
        output(($r !== false), $message);

        $sql = <<<EOSQL
INSERT INTO lookup_notification_frequency_map 
VALUES
    (1,10),
    (2,30),
    (3,60*3),
    (4,60*6),
    (5,60*12),
    (6,60*24);
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Inserted lookup_notification_frequency_map values";
        output(($r !== false), $message);


        $sql = <<<EOSQL
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    context_type INT UNSIGNED NOT NULL,
    context_id BIGINT UNSIGNED NOT NULL,
    media_type INT UNSIGNED NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_type INT UNSIGNED NOT NULL,
    data JSON NULL,
    CONSTRAINT uq_notifications UNIQUE KEY (token),
    CONSTRAINT fk_notifications_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE 
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table notifications";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE notifications_timeline(
    notification_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_notifications_timeline PRIMARY KEY (notification_id, message_id),
    CONSTRAINT fk_notifications_timeline_notifications FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table notification_timeline";
        output(($r !== false), $message);


        $sql = <<<EOSQL
CREATE TABLE notification_abuse_reports(
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	notification_id BIGINT UNSIGNED NOT NULL,
	reporter_name VARCHAR(255) NOT NULL,
	reporter_email VARCHAR(255) NOT NULL,
	report_text TEXT NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT fk_notifications_abuse_reports_notifications FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE   
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table notification_abuse_report";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE versions(
	id INT AUTO_INCREMENT PRIMARY KEY,
    notes VARCHAR(255) NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table versions";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE keystore(
	`key` VARCHAR(255) PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL    
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table keystore";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE assets(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    context_id BIGINT UNSIGNED NOT NULL,
    context_type TINYINT NOT NULL,
    asset_type TINYINT NOT NULL,
    attribute TINYINT NOT NULL,
    mime VARCHAR(255) NOT NULL, 
    asset_data MEDIUMBLOB NOT NULL,    
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,
    CONSTRAINT unique_asset_type UNIQUE KEY(context_id, context_type, asset_type, attribute)    
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table assets";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE alerts(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NULL, /* receiver, null implies all users */	    
    context_id BIGINT UNSIGNED NULL, /* what object is it about */    
    context_type TINYINT NULL,        
    alert_text VARCHAR(255) NOT NULL,
    alert_type TINYINT default 0,
    is_read BOOLEAN NOT NULL DEFAULT 0,	  
    issuers JSON NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_alerts UNIQUE KEY(user_id, context_id, context_type, alert_text),     
    INDEX (user_id),
    INDEX (created_on)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table alerts";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE phones(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	is_valid BOOLEAN NOT NULL DEFAULT 0,
	`number` VARCHAR(100) NOT NULL, 
	local_format VARCHAR(100) NOT NULL,
	intl_format VARCHAR(100) NOT NULL,
	country_prefix VARCHAR(10) NOT NULL, 
	country_code VARCHAR(10) NOT NULL,
	country_name VARCHAR(100) NOT NULL,
	location VARCHAR(100) NULL,
	carrier VARCHAR(100) NULL,
	line_type VARCHAR(50) NOT NULL,   
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_phone_validators UNIQUE KEY(`number`, local_format, intl_format)
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table phones";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE policies(
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	context_id BIGINT UNSIGNED NOT NULL,    
    context_type TINYINT NOT NULL,      
	policy_name VARCHAR(255) NOT NULL,
	policy_value VARCHAR(255) NOT NULL,	   
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT unique_alerts UNIQUE KEY(context_id, context_type, policy_name)   
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table policies";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE fcm_devices(
    device_id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    client_app VARCHAR(32),
    created_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,    
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fcm_devices_user_id  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table fcm_devices";
        output(($r !== false), $message);

        $sql = <<<EOSQL
CREATE TABLE locks(
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id BIGINT UNSIGNED NULL, /* lock owner */
    created_on DATETIME NOT NULL,
    expiry DATETIME NULL,	
	path VARCHAR(500) NOT NULL, 
    context VARCHAR(32),   
    CONSTRAINT unique_locked_path UNIQUE KEY(path)    
)ENGINE=INNODB;
EOSQL;
        $r = $dbh->exec($sql);
        $message = "Created Table locks";
        output(($r !== false), $message);

        $sql = "ALTER TABLE channels AUTO_INCREMENT 	= 11000000;
                ALTER TABLE teams AUTO_INCREMENT 	    = 51000000;
                ALTER TABLE users AUTO_INCREMENT 	    = 91000000;
                ALTER TABLE actions AUTO_INCREMENT 	    = 10000;                                
                ALTER TABLE messages AUTO_INCREMENT 	= 10000;
                ALTER TABLE timelines AUTO_INCREMENT 	= 10000;
                ALTER TABLE channel_paths AUTO_INCREMENT= 10000;";
        $r = $dbh->exec($sql);
        $message = "Set Auto Increment Seed Value for Tables";
        output(($r !== false), $message);

        // execute the migrations before inserting data (this way we avoid conflicts with migrations)
        $sql = "INSERT INTO versions(notes) VALUES('db schema initialized');";
        $r = $dbh->exec($sql);
        $message = "Set db version to 1";
        output(($r !== false), $message);

        output(true, 'Starting migrations');
        $shouldExecute = true;
        $onlyCloudDb = true;
        require_once dirname(__FILE__) . '/dbmigrate.php';
        output(true, 'Migrations executed');

        $adminUser = User::create("admin@airsend.io",null,
                                    password_hash($adminPassword, PASSWORD_BCRYPT),
                                    "serviceadmin", User::ACCOUNT_STATUS_ACTIVE, User::USER_ROLE_SERVICE_ADMIN,
                                    User::APPROVAL_STATUS_APPROVED,false, true);
        $result = $dc->createUser($adminUser);
        $message = "Create admin user record";
        output($result, $message);

        $adminTeam = Team::create(Team::SELF_TEAM_NAME . " " . $adminUser->getId(), Team::TEAM_TYPE_SELF, $adminUser->getId());
        $result = $dc->createTeam($adminTeam);
        $message = "Create admin team record";
        output($result, $message);

        $channel = Channel::create($adminTeam->getId(), "service admin channel", "adminchannel",
                                    Channel::CHANNEL_STATUS_CLOSED, $adminUser->getId());
        $result = $dc->createChannel($channel);
        $message = "Added channel to admin team";
        output($result, $message);

        $channelUser = ChannelUser::create($channel->getId(), $adminUser->getId(),
                                        ChannelUser::CHANNEL_USER_ROLE_ADMIN, $adminUser->getId());
        $result = $dc->addChannelUser($channelUser);
        $message = "Added admin user to self created channel";
        output($result, $message);


        $botUser = User::create("bot@airsend.io",null,
            password_hash("8JrPEjabp7JPbTu7", PASSWORD_BCRYPT),
            "botuser", User::ACCOUNT_STATUS_ACTIVE, User::USER_ROLE_SUB_ADMIN,
            User::APPROVAL_STATUS_APPROVED,true);
        $result = $dc->createUser($botUser);
        $message = "Create bot user record";
        output($result, $message);

        $botTeam = Team::create(Team::SELF_TEAM_NAME . " " . $botUser->getId(), Team::TEAM_TYPE_SELF, $botUser->getId());
        $result = $dc->createTeam($botTeam);
        $message = "Create bot user team record";
        output($result, $message);


        $msUser = User::create("microservice@airsend.io",null,
            password_hash("8KrLEjnbp7JPbTu7", PASSWORD_BCRYPT),
            "Micro Service User", User::ACCOUNT_STATUS_ACTIVE, User::USER_ROLE_SUB_ADMIN,
            User::APPROVAL_STATUS_APPROVED,true);
        $result = $dc->createUser($msUser);
        $message = "Create Micro Service user record";
        output($result, $message);

        $msTeam = Team::create(Team::SELF_TEAM_NAME . " " . $msUser->getId(), Team::TEAM_TYPE_SELF, $msUser->getId());
        $result = $dc->createTeam($msTeam);
        $message = "Create Micro Service user team record";
        output($result, $message);

        $sql = "ALTER TABLE channels AUTO_INCREMENT 	= 11010000;
                ALTER TABLE teams AUTO_INCREMENT 	    = 51010000;
                ALTER TABLE users AUTO_INCREMENT 	    = 91010000;";
        $r = $dbh->exec($sql);
        $message = "Reset Auto Increment Seed Values";
        output(($r !== false), $message);


        $message = "Database $asClouddbName initialized with user $asClouddbUser";
        echo  $message . addPeriods(60 - strlen($message)) . "DONE \n";
        exit(0);

    } catch (PDOException $e) {
        echo $e->getMessage();
        exit(1);
    } catch (DatabaseException $e) {
        echo $e->getMessage();
        exit(1);
    }
}
else{
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}
