<?php

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class FileCest
 * @group action
 */
class ActionCest
{
    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;
    protected $channelId;
    protected $userId_1;
    protected $email_1;
    protected $email_0;
    protected $userId_0;
    protected $password;

    public function _before(ApiTester $I)
    {
        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');

        //Create 2 users
        $this->password = "password1";

        $this->email_0 = 'test0@airsendmail.com';
        $displayName_0 = "ActionTest0";

        $this->createUser($I, $this->email_0, $this->password, $displayName_0);
        $I->seeResponseCodeIsSuccessful();
        $this->userId_0 = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $this->userId_0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $this->userId_0, $this->email_0, $displayName_0, 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->email_1 = 'test1@airsendmail.com';
        $displayName_1 = "ActionTest1";

        $this->createUser($I, $this->email_1, $this->password, $displayName_1);
        $I->seeResponseCodeIsSuccessful();
        $this->userId_1 = $I->grabDataFromResponseByJsonPath('$user.id')[0] ;

        //Approve the user and update through admin
        $adminEmail = 'admin@airsend.io';
        $adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';

        $this->adminLogin($I, $adminEmail, $adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $this->adminUserApprove($I, $this->userId_1);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->adminUserUpdate($I, $this->userId_1, $this->email_1, $displayName_1, 50, 100,0,
            0, 10, 1,0,0);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);


        //Create 1 channel

        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelName = "ActionTest Channel";

        $this->createChannel($I, $channelName);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);

        $this->channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

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
        $I->sendRawPost('/user.create', $payload);

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
        $I->sendRawPost('/user.login', $payload);
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
        $I->sendRawPost('/channel.create', $payload);
    }

    protected function createAction(ApiTester $I, string $channelId, string $actionName, string $desc, int $actionType, int $actionStatus, string $dueDate, string $userIds )
    {
        $payload = [];
        $payload['channel_id'] = $channelId;
        $payload['action_name'] = $actionName;
        $payload['action_desc'] = $desc;
        $payload['action_type'] = $actionType;
        $payload['action_status'] = $actionStatus;
        $payload['action_due_date'] = $dueDate;
        $payload['user_ids'] = $userIds;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPost('/action.create', $payload);
    }

    protected function getAction(ApiTester $I, int $actionId)
    {
        $payload = [];
        $payload['action_id'] = $actionId;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGet('/action.info', $payload);
    }

    protected function getActionList(ApiTester $I, int $channelId, int $userId)
    {
        $payload = [];
        $payload['channel_id'] = $channelId;
        $payload['user_id'] = $userId;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawGet('/action.list', $payload);
    }

    protected function updateAction(ApiTester $I, string $actionId, string $actionName, string $desc, int $actionType, int $actionStatus, string $dueDate, string $userIds )
    {
        $payload = [];
        $payload['action_id'] = $actionId;
        $payload['action_name'] = $actionName;
        $payload['action_desc'] = $desc;
        $payload['action_type'] = $actionType;
        $payload['action_status'] = $actionStatus;
        $payload['action_due_date'] = $dueDate;
        $payload['user_ids'] = $userIds;

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPost('/action.update', $payload);
    }

  /* public function testCreateActionWithoutUserLogin(ApiTester $I)
    {
        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);



    }*/

    protected function adminLogin(ApiTester $I, string $email, string $password)
    {
        $payload = [];
        $payload['email'] = $email;
        $payload['password'] = $password;


        $I->haveHttpHeader('user-agent', self::DEFAULT_USER_AGENT);
        $I->sendRawPost('/admin.login', $payload);
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
        $I->sendRawPost('/admin.user.approve', $payload);

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
        $I->sendRawPost('/admin.user.update', $payload);

    }


    public function testSuccessfulCreateAction(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);


    }

    public function testCreateActionWithoutChannelId(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, '', $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );


        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);


    }

    public function testCreateActionWithInvalidChannelId(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = "00000000";
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );


        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);


    }

    public function testCreateActionWithInvalidUserIds(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = '1000';

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );


        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);


    }

    public function testGetAction(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);

        $actionId = $I->grabDataFromResponseByJsonPath('$action.id')[0] ;

        $this->getAction($I, $actionId);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);

    }

    public function testGetActionWithInvalidActionId(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);

        $actionId = 5555 ;

        $this->getAction($I, $actionId);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

    public function testGetActionsList(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);

        $this->getActionList($I, $channelId, $userId);
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['actions' => ['action_name' => $actionName]]);


    }

    public function testGetActionsListWithInvalidChannelId(ApiTester $I)
    {

        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);

        $channelID = 5555;
        $this->getActionList($I, $channelID, $userId);

        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);


    }


  /*  public function testSuccessfulUpdateAction(ApiTester $I)
    {
        // Login with the user now
        $this->login($I, $this->email_0, $this->password);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $channelId = $this->channelId;
        $actionName = "Review this file";
        $desc = "Review";
        $actionType = "2";
        $actionStatus = "0";
        $dueDate = "2019-10-20";
        $userId = $this->userId_0;

        $this->createAction( $I, $channelId, $actionName, $desc, $actionType, $actionStatus, $dueDate, $userId  );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName]]);

        $actionId = $I->grabDataFromResponseByJsonPath('$action.id')[0] ;

        $actionName_1 = "Update Data";
        $desc_1    = "Update Data";
        $actionType_1    = "3";
        $actionStatus_1 = "1";
        $dueOn_1  = "2019-10-20";
        $userId_1 = $this->userId_0;

        $this->updateAction($I, $actionId, $actionName_1, $desc_1, $actionType_1, $actionStatus_1, $dueOn_1, $userId_1 );
        $I->seeResponseCodeIsSuccessful();

        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['action' => ['action_name' => $actionName_1]]);
        $I->seeResponseContainsJson(['action' => ['action_type' => $actionType_1]]);

    }*/



}