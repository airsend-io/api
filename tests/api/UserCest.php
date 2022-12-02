<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class UserCest
 * @group user
 */
class UserCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;

    public function _before(ApiTester $I)
    {
        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');
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
        $I->sendPOST('/user.create', $payload);

        // there is no simple way to set this through the API (need a special code sent by email)
        $I->updateInDatabase('users', ['is_email_verified' => 1], ['email' => $email]);
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
        $I->sendPOST('/user.login', $payload);
        if ($token = $I->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
            $this->jwtToken = $token;
        }
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

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");

        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendGET('/user.info', $payload);
    }

    protected function update(ApiTester $I, ?string $name = null, ?string $phone = null)
    {

        $payload = [];
        if ($phone !== null) {
            $payload['phone'] = $phone;
        }
        if ($name !== null) {
            $payload['name'] = $name;
        }

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $I->sendPOST('/user.profile.set', $payload);
    }

    protected function setProfileImage(ApiTester $I,  $file)
    {

        $files =  [
            'file' => [
                'name' => $file,
                'size' => filesize(codecept_data_dir($file)),
                'tmp_name' => codecept_data_dir($file)
            ]];

        $I->deleteHeader('Content-Type');
        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawPOST('/user.image.set', ['inline' => 0], $files);
    }

    protected function getProfileImage(ApiTester $I, string $userid , string $imageclass)
    {

        $param = '?user_id='.$userid.'&image_class='.$imageclass;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/user.image.get'.$param);
    }

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

    protected function getAlerts(ApiTester $I)
    {

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/user.alerts');
    }

    protected function alertAck(ApiTester $I, int $alertId)
    {
        $payload = [];
        $payload['alert_id'] = $alertId;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawPOST('/user.alert.ack', $payload);
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
    public function testCreateUserAccountWithoutDisplayName(ApiTester $I)
    {
        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $this->createUser($I, $testEmail, $password);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Verify few things on response
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $I->seeResponseContainsJson(['user' => ['email' => $testEmail]]);
        $userId = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $testEmail, '', 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Try to login now with wrong password
        $this->login($I, $testEmail, 'wrongpassword');
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();


    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testCreateUserAccountWithDisplayName(ApiTester $I)
    {
        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Verify few things on response
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $I->seeResponseContainsJson(['user' => ['email' => $testEmail]]);
        $I->seeResponseContainsJson(['user' => ['display_name' => $displayName]]);

        $userId = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Try to login now with wrong password
        $this->login($I, $testEmail, 'wrongpassword');
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();


    }

    /**
     * @return array
     */
    protected function badUserPathExamples()
    {
        return [
            'no creds' => [
                'code' => 422,
                'error' => 'The Password field is required',
                'content' => [
                    'password' => 'The Password field is required'
                ]
            ],
            'no email' => [
                'code' => 422,
                'password' => 'password1',
                'error' => 'Email or Phone is Required',
            ],
            'no password' => [
                'code' => 422,
                'email' => 'test@airsendmail.com',
                'error' => 'The Password field is required',
                'content' => [
                    'password' => 'The Password field is required'
                ]
            ],
            'small password' => [
                'code' => 422,
                'email' => 'test@airsendmail.com',
                'password' => "pass2",
                'error' => 'The Password field needs to be at least 6 characters',
                'content' => [
                    'password' => 'The Password field needs to be at least 6 characters'
                ]
            ],

            'malformed email' => [
                'code' => 422,
                'email' => 'notanemail',
                'password' => 'anypassword',
                'error' => 'The Email field must be a valid email address',
                'content' => [
                    'email' => 'The Email field must be a valid email address'
                ]
            ],
        ];
    }
    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @throws Exception
     * @dataProvider badUserPathExamples
     */
    public function usedBadPathTest(ApiTester $I, \Codeception\Example $example)
    {
        $this->createUser($I, $example['email'] ?? "", $example['password'] ?? "");

        $I->seeResponseCodeIs($example['code']);
        $I->seeResponseContainsJson([
            'meta' => [
                'ok' => false,
                'error' => $example['error'],
            ]
        ]);
        if (isset($example['content'])) {
            $I->seeResponseContainsJson($example['content']);
        }
    }

    public function testGettingUserInfoWithEmail(ApiTester $I)
    {
        $this->jwtToken = ""; // Clear

        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $userId = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Try to get the User Info
        $this->userInfo($I, $testEmail);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['user' => ['email' => $testEmail]]);

    }

    public function testGettingUserInfoWithUserId(ApiTester $I)
    {
        $this->jwtToken = ""; // Clear

        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

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

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Try to get the User Info
        $this->userInfo($I, "", $userId);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['user' => ['email' => $testEmail]]);

    }

    public function testGettingUserInfoWithNoParams(ApiTester $I)
    {
        $this->jwtToken = ""; // Clear

        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

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

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Try to get the User Info
        $this->userInfo($I, "", "");
        //$I->seeResponseCodeIs(400);// check with devs

        //$I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }



    public function testGetUserInfoWithNoAuthentication(ApiTester $I)
    {

        $this->jwtToken = ""; // Clear
        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Try to get the User Info and it should fail
        $this->userInfo($I, $testEmail);
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();

    }


    public function testCreateDuplicateUser(ApiTester $I)
    {

        $this->jwtToken = ""; // Clear
        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // This should fail
        $this->createUser($I, $testEmail, $password, $displayName);
        $I->seeResponseCodeIs(500);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Email '.$testEmail.' exists. Cannot create a new user']]);


    }


    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testUpdateUser(ApiTester $I)
    {
        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        // Verify few things on response
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $I->seeResponseContainsJson(['user' => ['email' => $testEmail]]);
        $I->seeResponseContainsJson(['user' => ['display_name' => $displayName]]);
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

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        // Try to update the name now
        $this->update($I, 'New Name', '447700900899');

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson(['user' => ['phone' => '+447700900899']]);
        $I->seeResponseContainsJson(['user' => ['display_name' => 'New Name']]);

    }

    public function testSetAndGetProfileImage(ApiTester $I)
    {
        $testEmail = 'test@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

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

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $userid = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        $profileFile = "Image.jpg";
        $this->setProfileImage($I, $profileFile);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        $this->getProfileImage($I, $userid, 'full');
        $I->seeResponseCodeIsSuccessful();
        $data = $I->grabResponse();

        $savePath = codecept_data_dir("downloadedImage.jpg");
        $fp = fopen($savePath, 'w');
        $file = fwrite($fp, $data);
        fclose($fp);

        //Compare MD5 for full scale profile image
        $I->assertEquals('3f358dc96367eb0f27c4f45071828843',md5_file($savePath));
        unlink($savePath);

        $this->getProfileImage($I, $userid, 'medium');
        $I->seeResponseCodeIsSuccessful();
        $data = $I->grabResponse();

        $savePath = codecept_data_dir("downloadedImage.jpg");
        $fp = fopen($savePath, 'w');
        $file = fwrite($fp, $data);
        fclose($fp);

        //Compare MD5 for medium scale profile image
        $I->assertEquals('ecfa51b5c78a4e69aa77193e597c9640',md5_file($savePath));
        unlink($savePath);

        $this->getProfileImage($I, $userid, 'small');
        $I->seeResponseCodeIsSuccessful();
        $data = $I->grabResponse();

        $savePath = codecept_data_dir("downloadedImage.jpg");
        $fp = fopen($savePath, 'w');
        $file = fwrite($fp, $data);
        fclose($fp);

        //Compare MD5 for small scale profile image
        $I->assertEquals('43d169ef2225724b40cc459fac159271',md5_file($savePath));
        unlink($savePath);

    }

    public function testSetProfileImageForInvalidUser(ApiTester $I)
    {
        $profileFile = "Image.jpg";
        $this->setProfileImage($I, $profileFile);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Invalid token']]);

    }

    public function testGetProfileImageForUserWithoutProfileImage(ApiTester $I)
    {
        $testEmail = 'test1@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

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

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Try to login now
        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $userid = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        $this->getProfileImage($I, $userid, 'full');
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

    public function testUserAlerts(ApiTester $I)
    {
        $testEmail = 'test1@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $userId = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
             10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $testEmail2 = 'test2@airsendmail.com';
        $password2 = 'password1';
        $displayName2 = 'Test User2';
        $this->createUser($I, $testEmail2, $password2, $displayName2);
        $userId2 = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();


        $userId = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId2);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId2, $testEmail2, $displayName2, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $emailsToAdd = $testEmail.','.$testEmail2;
        $channelName = "test";

        $this->createChannel($I, $channelName, $emailsToAdd);
        $I->seeResponseCodeIsSuccessful();

        $channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;


        $utmessage = "This is a unit test message [$displayName2](user://$userId2)";

        $this->postMessage($I, $channelId, $utmessage);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->getAlerts($I);
        $alerts = $I->grabDataFromResponseByJsonPath('$alerts')[0];
        $I->assertEmpty($alerts);

        $this->login($I, $testEmail2, $password2);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->getAlerts($I);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $alertsFromEmail = $I->grabDataFromResponseByJsonPath('$alerts')[0][0]['from'][0]['email'];
        $I->assertEquals($testEmail, $alertsFromEmail);

        $alertsText = $I->grabDataFromResponseByJsonPath('$alerts')[0][0]['alert_text'];
        $I->assertNotEmpty($alertsText);
    }

    public function testUserAlertsAck(ApiTester $I)
    {
        $testEmail = 'test1@airsendmail.com';
        $password = 'password1';
        $displayName = 'Test User';
        $this->createUser($I, $testEmail, $password, $displayName);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $userId = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId, $testEmail, $displayName, 50, 100,0,0,
             10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $testEmail2 = 'test2@airsendmail.com';
        $password2 = 'password1';
        $displayName2 = 'Test User2';
        $this->createUser($I, $testEmail2, $password2, $displayName2);
        $userId2 = $I->grabDataFromResponseByJsonPath('user.id')[0] ;

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $userId2);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $userId2, $testEmail2, $displayName2, 50, 100,0,0,
            10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $channelName = "test";

        $this->login($I, $testEmail, $password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $emailsToAdd = $testEmail.','.$testEmail2;
        $this->createChannel($I, $channelName, $emailsToAdd);
        $I->seeResponseCodeIsSuccessful();

        $channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        $utmessage = "This is a unit test message [$displayName2](user://$userId2)";
        $this->postMessage($I, $channelId, $utmessage);

        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->getAlerts($I);
        $alerts = $I->grabDataFromResponseByJsonPath('$alerts')[0];
        $I->assertEmpty($alerts);

        $this->login($I, $testEmail2, $password2);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->getAlerts($I);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $alertsFromEmail = $I->grabDataFromResponseByJsonPath('$alerts')[0][0]['from'][0]['email'];
        $I->assertEquals($testEmail, $alertsFromEmail);

        $isRead = $I->grabDataFromResponseByJsonPath('$alerts')[0][0]['is_read'];
        $I->assertEquals($isRead, false);

        $alert_id = $I->grabDataFromResponseByJsonPath('$alerts')[0][0]['alert_id'];

        $this->alertAck($I, $alert_id);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->getAlerts($I);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $isRead = $I->grabDataFromResponseByJsonPath('$alerts')[0][0]['is_read'];
        $I->assertEquals($isRead, true);

    }







    }