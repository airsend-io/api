<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class ChatCest
 * @group chat
 */
class ChatCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;
    protected $channelId;
    protected $testEmail;
    protected $password;
    protected $displayName;
    protected $channelName;

    public function _before (ApiTester $I)
    {
        $this->jwtToken = "";
        $this->testEmail = 'chaatunittest@airsendmail.com';
        $this->password = 'password1';
        $this->displayName = 'Chat Unit Test User';
        $this->channelName = "New Test Channel";

        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');

        // Create a test user
        $this->createUser($I, $this->testEmail, $this->password, $this->displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $this->testEmail, $this->displayName, 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @param string $password
     * @param string $name
     * @incomplete
     */
    protected function createUser (ApiTester $I, string $email = "", string $password = "", string $name = "")
    {
        $payload = [];
        $payload['email'] = $email;
        $payload['password'] = $password;
        if (!empty($name)) {
            $payload['name'] = $name;
        }

        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/user.create', $payload);

        // there is no simple way to set this through the API (need a special code sent by email)
        $I->updateInDatabase('users', ['is_email_verified' => 1], ['email' => $this->testEmail]);
        $I->cleanup(); // clean redis cache
    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @param string $password
     * @throws Exception
     */
    protected function login (ApiTester $I, ?string $email = null, ?string $password = null)
    {
        $payload = [];
        if ($email !== null) {
            $payload['email'] = $email;
        }
        if ($password !== null) {
            $payload['password'] = $password;
        }

        $I->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/user.login', $payload);
        if ($token = $I->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
            $this->jwtToken = $token;
        }
    }

    /**
     * @param ApiTester $I
     * @param string $channelName
     * @param string $userEmailsToAdd
     * @incomplete
     * @throws Exception
     */
    protected function createChannel (ApiTester $I, string $channelName = "", string $userEmailsToAdd = "")
    {
        $payload = [];
        $payload['channel_name'] = $channelName;
        if (!empty($userEmailsToAdd)) {
            $payload['emails'] = $userEmailsToAdd;
        }

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/channel.create', $payload);
    }

    protected function postMessage (ApiTester $I, string $channelId = "", string $text = "",
                                    string $quoteMessageId = "", string $attachments = "" )
    {
        $payload = [];
        $payload['channel_id'] = $channelId;
        $payload['text'] = $text;

        if (!empty($quoteMessageId)) {
            $payload['quote_message_id'] = $quoteMessageId;
        }

        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/chat.postmessage', $payload);
    }

    protected function updateMessage (ApiTester $I, string $messageId = "", string $text = "")
    {
        $payload = [];
        $payload['message_id'] = $messageId;
        $payload['text'] = $text;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/chat.updatemessage', $payload);
    }

    protected function deleteMessage (ApiTester $I, string $messageId = "")
    {
        $payload = [];
        $payload['message_id'] = $messageId;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/chat.deletemessage', $payload);
    }

    protected function channelHistory(ApiTester $I, string $channelid="", string $cursor="", int $limit=10)
    {
        $payload = [];
        $payload['channel_id'] = $channelid;
        $payload['limit'] = $limit;
        $payload['cursor'] = $cursor;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGET('/channel.history', $payload);
    }

    protected function adminLogin(ApiTester $I, string $email, string $password)
    {
        $payload = [];
        $payload['email'] = $email;
        $payload['password'] = $password;


        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/admin.login', $payload);
        if ($token = $I->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
            $this->jwtToken = $token;
        }
    }

    protected function adminUserApprove(ApiTester $I, string $userId)
    {
        $payload = [];
        $payload['user_id'] = $userId;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/admin.user.approve', $payload);

    }

    protected function adminUserUpdate(ApiTester $I, string $userId, string $email, string $name, int $user_role, int $account_status, int $trust_level, int $is_locked,
                                       string $approval_status, string $is_email_verified, string $is_phone_verified, string $is_auto_pwd)
    {
        $payload = [];
        $payload['user_id'] = $userId;
        $payload['email'] = $email;
        $payload['name'] = $name;
        $payload['user_role'] = $user_role;
        $payload['account_status'] = $account_status;
        $payload['trust_level'] = $trust_level;
        $payload['is_locked'] = $is_locked;
        $payload['approval_status'] = $approval_status;
        $payload['is_email_verified'] = $is_email_verified;
        $payload['is_phone_verified'] = $is_phone_verified;
        $payload['is_auto_pwd'] = $is_auto_pwd;



        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/admin.user.update', $payload);

    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testPostMessageInChannel(ApiTester $I)
    {



        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Check if the history is there
        $this->channelHistory($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $messages = $I->grabDataFromResponseByJsonPath('$messages')[0] ;
        $I->assertEquals(count($messages), 0);
        // Post a message
        $utmessage = 'This is a unit test message';

        $this->postMessage($I, $this->channelId, $utmessage);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Check if the history is there
        $this->channelHistory($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $messages = $I->grabDataFromResponseByJsonPath('$messages')[0] ;
        //var_dump($messages);
        // Make sure we only one message in channel
        $I->assertEquals(count($messages), 1);
        // Makes sure the message is the one we added
        $I->assertEquals($messages[0]['content'], $utmessage);


    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testBotMessage(ApiTester $I)
    {

        $emails = 'unittest2@test.com';
        // Create channel
        $this->createChannel($I, $this->channelName, $emails);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Check if the history is there
        $this->channelHistory($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Because we added a user to a new channel, there should be one bot message
        $messages = $I->grabDataFromResponseByJsonPath('$messages')[0] ;
        //var_dump($messages);
        $I->assertEquals(count($messages), 1);

        // Makes sure the message of bot message type
        $I->assertEquals($messages[0]['message_type'],5);

    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testPostMessageInUnauthorizedChannel(ApiTester $I)
    {

        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Create a another user
        $this->createUser($I, 'test3@unitest3.com', 'password', 'Test user 3');

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, 'test3@unitest3.com', 'test3', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Login with the user now
        // Login with the user now
        $this->login($I, 'test3@unitest3.com', 'password');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Post a message as the new user who has no access this channel
        $this->postMessage($I, $this->channelId, "This is a unit test message");

        // Must be rejected as this user has no access to this channel
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);
    }


         /**
         * @param ApiTester $I
         * @throws Exception

        public function testMessageAttachmentsInChannel(ApiTester $I)
    {
        // Create a test user
        $this->createUser($I, $this->testEmail, $this->password, $this->displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        $attachment1 = array(
          '/cf/1/attachments/1.jpg',
          '/cf/1/attachments/2.png',
        );
        $attachment1_json = json_encode($attachment1);
        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message 1 ", '', $attachment1_json);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $attachment2 = array(
            [
                'file_path' => '/cf/1/attachments/3.jpg',
                'file_type' => 'image/jpeg'
            ]
        );

        $attachment2_json = json_encode($attachment2);
        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message 2", '', $attachment2_json);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
    }

          */
    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testMessageQuoteInChannel(ApiTester $I)
    {
        $emails = "temp1@test.com,temp2@test.com,temp3@test.com";

        // Create channel
        $this->createChannel($I, $this->channelName, $emails);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $messageId = $I->grabDataFromResponseByJsonPath('$message_id')[0];

        // Quote earlier message
        $this->postMessage($I, $this->channelId, "This is a unit test message", ''.$messageId);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testEditMessageInChannel(ApiTester $I)
    {
        $emails = "temp1@test.com,temp2@test.com,temp3@test.com";

        // Create channel
        $this->createChannel($I, $this->channelName, $emails);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $messageId = $I->grabDataFromResponseByJsonPath('$message_id')[0];


        // Edit the message
        $this->updateMessage($I, $messageId, "This is a edited message");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testEditMessageInChannelByAnotherUser(ApiTester $I)
    {

        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $messageId = $I->grabDataFromResponseByJsonPath('$message_id')[0];

        $this->createUser($I, 'user2@user2test.com', 'password', 'Unit test user 2');
        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, 'user2@user2test.com', 'user2', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Login with the user now
        $this->login($I, 'user2@user2test.com', 'password');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Edit the message
        $this->updateMessage($I, $messageId, "This is a edited message");

        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testDeleteMessage(ApiTester $I)
    {

        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $messageId = $I->grabDataFromResponseByJsonPath('$message_id')[0];


        // Edit the message
        $this->deleteMessage($I, $messageId);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testDeleteMessageByAnotherUser(ApiTester $I)
    {

        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);
        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Post a message
        $this->postMessage($I, $this->channelId, "This is a unit test message");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $messageId = $I->grabDataFromResponseByJsonPath('$message_id')[0];

        $this->createUser($I, 'user2@user2test.com', 'password', 'Unit test user 2');
        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, 'user2@user2test.com', 'user2', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Login with the user now
        $this->login($I, 'user2@user2test.com', 'password');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Edit the message
        $this->deleteMessage($I, $messageId);

        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }


}