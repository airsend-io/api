<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class ChannelOldCest
 * @group channel
 */
class ChannelOldCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;
    protected $channelId;
    protected $testEmail;
    protected $password;
    protected $displayName;
    protected $channelName;
    protected $adminEmail;
    protected $adminPassword;

    public function _before(ApiTester $I)
    {

        //$I->startFixtureDeltaRecording();

        $this->jwtToken = "";
        $this->testEmail = 'channelunittest@airsendmail.com';
        $this->password = 'password1';
        $this->displayName = 'Channel Unit Test User';
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
        $this->adminEmail = 'admin@airsend.io';
        $this->adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $this->adminEmail, $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $this->testEmail, $this->displayName, 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        //$I->generateFixtures('channel/init');

    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @param string $password
     * @param string $name
     * @incomplete
     */
    protected function createUser(ApiTester $I, string $email="", string $password="", string $name="")
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
    protected function login(ApiTester $I, ?string $email = null, ?string $password = null)
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
    protected function createChannel(ApiTester $I, string $channelName="", string $userEmailsToAdd = "")
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

    /**
     * @param ApiTester $I
     * @param string $channelid
     * @incomplete
     */
    protected function getChannelInfo(ApiTester $I, string $channelid="")
    {
        $payload = [];
        $payload['channel_id'] = $channelid;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGET('/channel.info', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelid
     * @param string $emailsToInvite
     * @incomplete
     */
    protected function channelInvite(ApiTester $I, ?string $channelid="", string $emailsToInvite="")
    {
        $payload = [];
        $payload['channel_id'] = $channelid;
        $payload['emails'] = $emailsToInvite;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/channel.invite', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelid
     * @incomplete
     */
    protected function getChannelMembers(ApiTester $I, string $channelid="")
    {
        $payload = [];
        $payload['channel_id'] = $channelid;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGET('/channel.members', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelid
     * @param string $channelName
     * @incomplete
     */
    protected function channelRename(ApiTester $I, string $channelid="", string $channelName="")
    {
        $payload = [];
        $payload['channel_id'] = $channelid;
        $payload['channel_name'] = $channelName;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/channel.rename', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelid
     * @param string $cursor
     * @param int $limit
     */
    protected function channelHistory(ApiTester $I, string $channelid="", string $cursor="", int $limit=1, int $limit_newer=0)
    {
        $payload = [];
        $payload['channel_id'] = $channelid;
        $payload['limit'] = $limit;
        $payload['cursor'] = $cursor;
        $payload['limit_newer'] = $limit_newer;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGET('/channel.history', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelId
     * @param string $text
     */
    protected function postMessage (ApiTester $I, string $channelId = "", string $text = "")
    {
        $payload = [];
        $payload['channel_id'] = $channelId;
        $payload['text'] = $text;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/chat.postmessage', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelId
     */
    protected function leave(ApiTester $I, string $channelId = "")
    {
        $payload = [];
        $payload['channel_id'] = $channelId;
        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/channel.leave', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $channelId
     * @param string $userId
     */
    protected function kick(ApiTester $I, string $channelId = "", string $userId="")
    {
        $payload = [];
        $payload['channel_id'] = $channelId;
        $payload['user_id'] = $userId;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPOST('/channel.kick', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @incomplete
     */
    protected function userInfo(ApiTester $I, string $email="", string $userid ="")
    {

        $payload = [];
        if (!empty($email)) {
            $payload['email'] = $email;
        }

        if (!empty($userid)) {
            $payload['user_id'] = $userid;
        }

        $I->haveHttpHeader('Authorization', "Bearer {$this->jwtToken}");

        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGET('/user.info', $payload);
    }


    protected function loginWithAnotherUser(ApiTester $I)
    {
        // Create another test user
        $this->createUser($I, 'user2@test.com', 'password', 'Test user 2');

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;


        $this->adminLogin($I, $this->adminEmail, $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId,'user2@test.com','Test user 2', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        // Login with the user now
        $this->login($I,'user2@test.com', 'password');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
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
    public function testChannelRename(ApiTester $I)
    {
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


        $renamedName = "CHANGED CHANNEL NAME";
        $this->channelRename($I, $this->channelId, $renamedName);
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->getChannelInfo($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['id' => $this->channelId]]);

        $I->seeResponseContainsJson(['channel' => ['channel_name' => $renamedName]]);
    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelRenameWithDuplicateName(ApiTester $I)
    {
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

        // Rename channel to same name and have it fail
        $this->channelRename($I, $this->channelId, $this->channelName);
        $I->seeResponseCodeIs(500);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testUnauthenticatedChannelRename(ApiTester $I)
    {
        $this->jwtToken = ""; // Clear
        $I->clearCookies();

        // Get Channel info
        $this->channelRename($I, $this->channelId, "TEST");
        // This should fail
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Missing token']]);
    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelHistory(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0];

        $this->postMessage($I, $this->channelId, "This is a unit test message 1");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        $this->postMessage($I, $this->channelId, "This is a unit test message 2");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->postMessage($I, $this->channelId, "This is a unit test message 3");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        $this->channelHistory($I, $this->channelId, "", 3);
        $I->seeResponseCodeIsSuccessful();

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testNewerChannelHistory(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Create channel
        $this->createChannel($I, $this->channelName);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0];

        $this->postMessage($I, $this->channelId, "This is a unit test message 1");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $oldestMessageId = $I->grabDataFromResponseByJsonPath('$message_id')[0];



        $this->postMessage($I, $this->channelId, "This is a unit test message 2");

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->postMessage($I, $this->channelId, "This is a unit test message 3");



        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $cursor = base64_encode($oldestMessageId);

        $this->channelHistory($I, $this->channelId, $cursor, 0, 1);
        $I->seeResponseCodeIsSuccessful();



    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelKick(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Login with the user now
        // Create channel
        $emails = "temp1@test.com";

        $this->createChannel($I, $this->channelName, $emails);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Get Channel info
        $this->userInfo($I, "temp1@test.com");
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        // Kick the user
        $this->kick($I, $this->channelId, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelKickWithoutBeingPartOfChannel(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Create a channel
        $this->createChannel($I, $this->channelName, '');
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Create another user
        $this->createUser($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Get Channel info
        $this->userInfo($I, "test2@test.com");
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        // Kick a user who is not part of the channel
        $this->kick($I, $this->channelId, $userId);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelKickTheOwnerOfTheChannel(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $ownerUserId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;


        // Create another user
        $this->createUser($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $userId= $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        $this->adminLogin($I, $this->adminEmail, $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, 'test2@test.com', 'test2', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Create a channel
        $this->createChannel($I, $this->channelName, 'test2@test.com');
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Login as the other user
        $this->login($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        // Kick a the owner of the channel
        $this->kick($I, $this->channelId, $ownerUserId);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }



    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelLeave(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Create another user
        $this->createUser($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

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

        $this->adminUserUpdate($I, $userId, 'test2@test.com', 'test2', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        // Create a channel and add the other user
        $this->createChannel($I, $this->channelName, 'test2@test.com');
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Login as the other user
        $this->login($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Leave channel
        $this->leave($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Will be nice to confirm the user doesnt exist
    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelLeaveWithoutBeingPartOfChannel(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Create a channel
        $this->createChannel($I, $this->channelName, '');
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // Create another user
        $this->createUser($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

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

        $this->adminUserUpdate($I, $userId, 'test2@test.com', 'test2', 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        // Login as anothe ruser
        $this->login($I, 'test2@test.com', 'password');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Leave channel (without being part of that channel)
        $this->leave($I, $this->channelId);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testOwnerLeavingChannel(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Create a channel
        $this->createChannel($I, $this->channelName, '');
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => $this->channelName]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        // Leave channel (as the owner of the channel)
        $this->leave($I, $this->channelId);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

}