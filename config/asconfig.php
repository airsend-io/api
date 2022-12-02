<?php declare(strict_types=1);

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use CodeLathe\Core\Indexers\FileParsers\DocParser;
use CodeLathe\Core\Indexers\FileParsers\DocxParser;
use CodeLathe\Core\Indexers\FileParsers\HtmlParser;
use CodeLathe\Core\Indexers\FileParsers\MdParser;
use CodeLathe\Core\Indexers\FileParsers\OdtParser;
use CodeLathe\Core\Indexers\FileParsers\PdfParser;
use CodeLathe\Core\Indexers\FileParsers\RtfParser;
use Psr\Log\LogLevel;

// set the environment to "tests" if the X-Airsend-Tests header is set
if (getenv('APP_ENV') === 'dev' && ($_SERVER['HTTP_X_AIRSEND_TESTS'] ?? false)) {
    putenv('APP_ENV=tests');
}

return [
    /* Application Specific Configuration */
    '/app/version' => '1.0.0.',
    '/app/mode' => getenv('APP_ENV') ?: 'prod',
    '/app/approval' => getenv('SIGNUP_APPROVAL') ?: 'verify', // auto, verify, approve
    '/app/authcookieenable' => getenv('ENABLE_COOKIE_AUTH') ?:'0',
    '/app/disable_account_creation' => getenv('DISABLE_ACCOUNT_CREATION') ?: '0',
    '/app/team/default_storage_quota_gb' => 100,
    '/app/baseurl' => (getenv('SERVER_BASE_URL') ?: 'https://live.airsend.io'),
    '/app/server/baseurl' => (getenv('SERVER_BASE_URL') ?: 'https://live.airsend.io') . '/api/v1',
    '/app/ui/baseurl' => getenv('UI_BASE_URL') ?: 'https://live.airsend.io',
    '/app/admin/password' => getenv('APP_ADMIN_PASSWORD') ?: '',
    '/app/admin/email' => getenv('APP_ADMIN_EMAIL') ?: '',
    '/app/admin/stats_email'=> getenv('APP_ADMIN_STATS_EMAIL') ?: '',
    '/app/internal/auth_token' => getenv('APP_INTERNAL_AUTH_TOKEN') ?: '',
    '/app/firebase_enabled' => getenv('FCM_ENABLED'),
    '/app/wopi_relay_url' => getenv('AIRSEND_WOPI_RELAY_URL') ?:'',
    '/app/wopi_relay_client_id' => getenv('AIRSEND_WOPI_RELAY_CLIENT_ID') ?:'',
    '/app/wrtc_server_address' => getenv('WEBRTC_SERVER_ADDRESS') ?: 'wrtc.airsend.io',
    '/app/slow_request_log_threshold' => getenv('SLOW_REQUEST_LOG_THRESHOLD') ?: 1000,
    '/app/airsend_bot_id' => (int)(getenv('AIRSEND_BOT_ID') ?: 91000001),
    '/app/service_admin_users' => getenv('AIRSEND_SERVICE_ADM_USERS') ?: '91000000',
    '/app/serialize_events' => (bool) getenv('AIRSEND_SERIALIZE_EVENTS'),
    '/app/apple_push_url' => getenv('APPLE_PUSH_URL') ?: '',


    /* Logger Specific Configuration */
    '/logger/name' => 'ASCORE',
    '/logger/level' => LogLevel::DEBUG,
    '/logger/extended' => array_map(function($item) { return trim(strtoupper($item)); }, explode(',', getenv('LOGGER_EXTENSIONS') ?: '')),

    /* Cache Specific */
    '/cache/host' => getenv('AIRSEND_REDIS_HOST') ?:'redis',
    '/cache/port' => getenv('AIRSEND_REDIS_PORT') ?:'6379',

    '/giphy/key' => getenv('GIPHY_KEY') ?: '',

    /* Captcha */
    '/captcha/enabled' => getenv('RECAPTCHA_ENABLED') ?: false,
    '/captcha/mobile_bypass' => getenv('RECAPTCHA_BYPASS_MOBILE') ?: false,
    '/captcha/v3/secret' => getenv('RECAPTCHA_V3_SECRET_KEY') ?: '',
    '/captcha/v3/siteKey' => getenv('RECAPTCHA_V3_SITE_KEY') ?: '',
    '/captcha/v2/secret' => getenv('RECAPTCHA_V2_SECRET_KEY') ?: '',
    '/captcha/v2/siteKey' => getenv('RECAPTCHA_V2_SITE_KEY') ?: '',
    '/captcha/android/secret' => getenv('RECAPTCHA_ANDROID_SECRET_KEY') ?: '',
    '/captcha/android/siteKey' => getenv('RECAPTCHA_ANDROID_SITE_KEY') ?: '',

    /* Elastic search */
    '/indices/files' => 'files_index_0001',
    '/indices/actions' => 'actions_index',
    '/indices/channels' => 'channels_index',
    '/indices/messages' => 'messages_index',
    '/indices/users' => 'users_index',

    /* Data Warehouse related */
    '/dw/requests_cube/version' => 1,

    /* DB Specific */
    '/db/cloud_db/port' => getenv('AIRSEND_DB_PORT') ?:'3306',

    /* cloud_db_root account */
    '/db/cloud_db_root/conn' => getenv('AIRSEND_DB_ROOT_HOST') ?: 'mysql:host=db;',
    '/db/cloud_db_root/user' => getenv('AIRSEND_DB_ROOT_USER') ?: 'root',
    '/db/cloud_db_root/password' => getenv('AIRSEND_DB_ROOT_PASSWORD') ?: 'root',

    /* Core DB */
    '/db/core/conn' => getenv('APP_ENV') === 'tests'
        ? (getenv('AIRSEND_CLOUD_DB_TESTS_HOST') ?: 'mysql:host=db;dbname=asclouddb_tests;charset=utf8mb4;')
        : (getenv('AIRSEND_CLOUD_DB_HOST') ?: 'mysql:host=db;dbname=asclouddb;charset=utf8mb4;'),
    '/db/core/user' => getenv('APP_ENV') === 'tests'
        ? (getenv('AIRSEND_CLOUD_DB_TESTS_USER') ?: 'asclouddbweb_test')
        : (getenv('AIRSEND_CLOUD_DB_USER') ?: 'asclouddbweb'),
    '/db/core/password' =>  getenv('AIRSEND_CLOUD_DB_PASSWORD') ?:'',
    '/db/core/version' => 43,

    /* Storage DB */
    '/db/fs/conn' => getenv('AIRSEND_FS_DB_HOST') ?: 'mysql:host=db;dbname=asstoragedb;charset=utf8mb4;',
    '/db/fs/user' => getenv('AIRSEND_FS_DB_USER') ?:'asstoragedbuser',
    '/db/fs/password' => getenv('AIRSEND_FS_DB_PASSWORD') ?: '',
    '/db/fs/version' => 5,

    /* Storage S3 */
    '/storage/s3/bucketname' => getenv('AIRSEND_FS_S3_BUCKETNAME') ?: '',
    '/storage/s3/key' => getenv('AIRSEND_FS_S3_KEY') ?: '',
    '/storage/s3/secret' => getenv('AIRSEND_FS_S3_SECRET') ?: '',
    '/storage/s3/region' => getenv('AIRSEND_FS_S3_REGION') ?: '',
    '/storage/s3/handler' => \CodeLathe\Service\Storage\Implementation\Backstore\S3Backstore::class,

    /* Kafka Specific */
    '/mq/host' => getenv('AIRSEND_KAFKA_HOST') ?:'kafka',

    /* Zookeeper Specific */
    '/zoo/host' => getenv('AIRSEND_ZOOKEEPER_HOST') ?:'zookeeper:2181',


    '/zoo/ws_nodes' => '/airsend.rtm_nodes',

    /* Mailer Specific */
    '/mailer/driver' => getenv('MAILER_DRIVER') ?: 'mailgun',
    '/mailer/domain' => getenv('MAILER_DOMAIN') ?: '',
    '/mailer/response_domain' => getenv('MAILER_RESPONSE_DOMAIN') ?: getenv('MAILER_DOMAIN') ?: '',
    '/mailer/drivers/dummy' => [],
    '/mailer/drivers/mailgun' => [
        'key' => getenv('MAILGUN_KEY') ?: '',
    ],
    '/mailer/drivers/aws_ses' => [
        'key' => getenv('AWS_SES_KEY') ?: '',
        'secret' => getenv('AWS_SES_SECRET') ?: '',
    ],


    /* SMS specific */
    '/sms/driver' => getenv('SMS_DRIVER') ?: 'dummy',
    '/sms/drivers/dummy' => [],
    '/sms/drivers/twilio' => [
        'sid' => getenv('TWILIO_SID'),
        'token' => getenv('TWILIO_TOKEN'),
        'from_number' => getenv('TWILIO_FROM_NUMBER'),
    ],
    '/sms/drivers/signalwire' => [
        'project' => getenv('SIGNALWIRE_PROJECT'),
        'token' => getenv('SIGNALWIRE_TOKEN'),
        'spaceurl' => getenv('SIGNALWIRE_SPACE_URL'),
        'from_number' => getenv('SIGNALWIRE_FROM_NUMBER'),
    ],
    /* Auth Specific */
    '/auth/jwt/issuer' => 'AirSend Server',
    '/auth/jwt/private_key_ttl' => ((int) (getenv('JWT_PRIVATE_KEY_TTL') ?: 6*3600)), // 6 hours by default
    '/auth/jwt/ttl' => ((int) (getenv('JWT_TTL') ?: 24*3600)), // 24 hours by default
    '/auth/notifications/token/ttl' => getenv('NOTIFICATIONS_TOKEN_TTL') ?: '1 day',
    '/auth/checkip' => getenv('AUTH_CHECK_IP') ?: false,
    '/auth/public_user_id' => getenv('AUTH_PUBLIC_USER_ID') ?: 91000003,

    /* oauth google settings */
    '/google/keys/id' => getenv('GOOGLE_KEY_ID'),
    '/google/keys/secret' => getenv('GOOGLE_KEY_SECRET'),

    /* oauth linkedin settings */
    '/linkedin/keys/id' => getenv('LINKEDIN_KEY_ID'),
    '/linkedin/keys/secret' => getenv('LINKEDIN_KEY_SECRET'),

    /* oauth apple settings */
    '/apple/keys/id' => getenv('APPLE_KEY_ID') ?: 'com.codelathe,com.codelathe.AirSend',
    '/apple/keys/team_id' => getenv('APPLE_TEAM_ID') ?: getenv('APPLE_KEY_ID'),
    '/apple/keys/key_file_id' => getenv('APPLE_KEY_FILE_ID'),

    /* OAuth Server settings */
    '/oauth/encryption_key' => getenv('OAUTH_KEY') ?: '',
    '/oauth/private_key_path' => 'oauth/private.key',
    '/oauth/public_key_path' => 'oauth/public.key',
    '/oauth/token_ttl' => getenv('OAUTH_TOKEN_TTL') ?: '1 day',
    '/oauth/authorization_code_ttl' => getenv('OAUTH_CODE_TTL') ?: '10 minutes',
    '/oauth/refresh_token_ttl' => getenv('OAUTH_REFRESH_TOKEN_TTL') ?: '1 month',
    '/oauth/approve_request_key_ttl' => getenv('OAUTH_APPROVE_REQUEST_KEY_TTL') ?: '5 minutes',


    /* Thumbnail */
    '/thumbnail/small' => 64,
    '/thumbnail/medium' => 256,
    '/thumbnail/max_size_mb' => getenv('MAX_THUMB_SIZE_MB') ?: '20',

    /* Num verify */
    '/num_verify/url' => 'https://apilayer.net/api/validate',
    '/num_verify/key' => getenv('NUMVERIFY_KEY') ?: '',

    /* Notifications */
    '/notifications/registered/mentions_ttw' => getenv('NOTIFICATIONS_FINALIZED_MENTIONS_TTW') !== false ? getenv('NOTIFICATIONS_FINALIZED_MENTIONS_TTW') : 10,
    '/notifications/registered/messages_ttw' => getenv('NOTIFICATIONS_FINALIZED_MESSAGES_TTW') ?: 24*60,
    '/notifications/dailyLimit/global' => 8,
    '/notifications/dailyLimit/perChannel' => 6,
    '/notifications/sender_suffix' => getenv('NOTIFICATIONS_SENDER_SUFFIX') ?: '',

    /* email digest */
    '/email_digest/max_message_size' => 140,
    '/email_digest/max_message_count_on_digest' => 5,


    '/search/elastic_servers' => [
        [
            'host' => getenv('ELASTIC_HOST') ?: 'elasticsearch',
            'port' => getenv('ELASTIC_PORT') ?: '9200',
            'scheme' => getenv('ELASTIC_SCHEME') ?: 'http',
            'path' => getenv('ELASTIC_PATH') ?: null,
            'user' => getenv('ELASTIC_USER') ?: null,
            'pass' => getenv('ELASTIC_PASS') ?: null,
        ]
    ],
    '/search/content_parsers' => [
        'md' => MdParser::class,
        'docx' => DocxParser::class,
        'doc' => DocParser::class,
        'odt' => OdtParser::class,
        'html' => HtmlParser::class,
        'htm' => HtmlParser::class,
        'rtf' => RtfParser::class,
        'pdf' => PdfParser::class,
    ],
    '/search/content_size_limit' => getenv('CONTENT_SEARCH_SIZE_LIMIT') ?: '10MB'
];
