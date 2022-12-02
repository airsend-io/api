<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class PasswordCest
 * @group password
 */
class PasswordCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;
    protected $channelId;
    protected $testEmail;
    protected $password;
    protected $displayName;
    protected $channelName;
	protected $user_id;
	protected $updatedPassword;

    public function _before (ApiTester $I)
    {
        $this->jwtToken = "";
        $this->testEmail = 'chaatunittest@airsendmail.com';
        $this->password = 'password1';
        $this->displayName = 'chat Unit Test User';
        $this->channelName = "New Test Channel";

        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');

        // Create a test user
        $this->createUser($I, $this->testEmail, $this->password, $this->displayName);

        // This should work
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $this->user_id = $I->grabDataFromResponseByJsonPath('$user.id')[0];

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $this->user_id);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $this->user_id, $this->testEmail, $this->displayName, 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        /*$this->login($I, $this->testEmail, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();*/
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
        $I->sendPOST('/user.create', $payload);

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
        $I->sendPOST('/user.login', $payload);
        if ($token = $I->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
            $this->jwtToken = $token;
        }
    }

    /**
     * @Param ApiTester $I
     * @Param $userId
     * @Param $resetCode
     * @Param $password
     * @param ApiTester $I
     * @param string $userId
     * @param string $resetCode
     * @param string $password
     */
	protected function passwordReset (ApiTester $I, string $userId = "", string $resetCode  = "", string $password  = "")
    {
        $payload = [];
        $payload['user_id'] = $userId;
        $payload['reset_code'] = $resetCode;
		$payload['password'] = $password;

        //$I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendPOST('/password.reset', $payload);
		
    }
	/**
     * @param ApiTester $I
     * @param string $email
     * @throws Exception
     */
	protected function passwordRecovery (ApiTester $I, $email)
    {
        $payload = [];
		$payload['opt_email'] = $email;
		
		//$I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendPOST('/password.recover', $payload);
    }

    /**
     * @param ApiTester $I
     * @param string $email
     * @param string $userid
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

    protected function adminLogin(ApiTester $I, string $email, string $password)
    {
        $payload = [];
        $payload['email'] = $email;
        $payload['password'] = $password;


        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendPOST('/admin.login', $payload);
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
        $I->sendPOST('/admin.user.approve', $payload);

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
        $I->sendPOST('/admin.user.update', $payload);

    }
	
    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testPasswordRecoveryAndReset(ApiTester $I)
    {

        // Recover Password
        $this->passwordRecovery($I, $this->testEmail);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $criteria = array('user_id' => $this->user_id);
        $resetCodeArray = $I->grabColumnFromDatabase('user_codes','code',$criteria);
        $resetCode = $resetCodeArray[1];

		// Reset Password
        $newPassword = 'password2';
		$this->passwordReset($I, $this->user_id, $resetCode, $newPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->login($I, $this->testEmail, $newPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->updatedPassword = $newPassword;
    }

    public function testPasswordRecoverWithInvalidUser(ApiTester $I)
    {

        // Recover Password for user not a part of airsend user list ( it should work)
        $this->passwordRecovery($I, 'random@airsendmail.com');
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        // Recover Password without email
        $this->passwordRecovery($I, '');
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Email or Phone number is required']]);


    }

    public function testPasswordResetWithWrongResetCode(ApiTester $I)
    {

        // Recover Password
        $this->passwordRecovery($I, $this->testEmail);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $criteria = array('user_id' => $this->user_id);
        $resetCodeArray = $I->grabColumnFromDatabase('user_codes','code',$criteria);
        $resetCode = $resetCodeArray[0];

        // Reset Password with wrong resetCode
        $newPassword = 'password2';
        $this->passwordReset($I, $this->user_id, 'asddasd', $newPassword);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Reset Password Failed']]);
    }

    public function testPasswordResetWithWrongUserId(ApiTester $I)
    {
        // Recover Password
        $this->passwordRecovery($I, $this->testEmail);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $criteria = array('user_id' => $this->user_id);
        $resetCodeArray = $I->grabColumnFromDatabase('user_codes','code',$criteria);
        $resetCode = $resetCodeArray[0];

        // Reset Password with invalid user id
        $newPassword = 'password2';
        $this->passwordReset($I, 'userid', $resetCode, $newPassword);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'The User Id field must be a number without a decimal'], 'user_id' => 'The User Id field must be a number without a decimal']);

        // Reset Password with invalid user id
        $newPassword = 'password2';
        $this->passwordReset($I, '12345', $resetCode, $newPassword);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Reset Password Failed']]);
    }









}