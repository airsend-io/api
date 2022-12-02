<?php


/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class WikiCest
 * @group wiki
 */
class WikiCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;
    protected $channelId;
    protected $testEmail;
    protected $password;
    protected $displayName;
    protected $channelName;

    public function _before(ApiTester $I)
    {
        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');

        $this->password = "password1";

        $this->testEmail = 'test0@airsendmail.com';
        $this->displayName = "WikiTest";

        $this->createUser($I, $this->testEmail, $this->password, $this->displayName);
        $I->seeResponseCodeIsSuccessful();

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

        $this->adminUserUpdate($I, $userId, $this->testEmail,$this->displayName, 50, 100,0,0
            , 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelName = "WikiTest Channel";

        $this->createChannel($I, $channelName);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;
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
     * @param string $path
     * @incomplete
     */

    protected function getWiki(ApiTester $I, string $path)
    {

        $param = ':channelresource='.$path;


        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);

        $I->sendRawGET('/wiki.get'.$path);
    }

    public function testWikiForGoodAndBadPath(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        //get Channel Info to get wiki Location
        $this->getChannelInfo($I, $this->channelId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $wikilocation = $I->grabDataFromResponseByJsonPath('$channel.channel_roots')[0][1]['location'];
        //var_dump($wikilocation);

        $this->getWiki($I,$wikilocation.'/index.md');
        $data = $I->grabResponse();
        $I->seeResponseContains('What is AirSend');
        //var_dump($data);

        //Invalid Wiki Path
        $this->getWiki($I,$wikilocation.'/abcd/index.md');
        $I->assertContains('No content in wiki' , $I->grabResponse());
    }
}