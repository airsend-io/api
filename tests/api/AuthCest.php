<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class AuthCest
 * @group auth
 */
class AuthCest
{

    protected const DEFAULT_USER_AGENT = 'API Tests Suite';

    protected $jwtToken;

    protected $adminPassword;

    public function _before(ApiTester $I)
    {
        $I->haveHttpHeader('accept', 'application/json');
        $I->haveHttpHeader('content-type', 'application/json');
        $this->adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';
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
            $payload['user'] = $email;
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

    public function dummy(ApiTester $I)
    {
        $this->login($I, 'jeferson.almeida@codelathe.com','jeferson.almeida@codelathe.com');
        $I->seeResponseCodeIsSuccessful();
    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function successfulLoginTest(ApiTester $I)
    {
        $this->login($I, 'admin@airsend.io', $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

    }

    /**
     * @return array
     */
    protected function failedLoginExamples()
    {
        return [
            'no creds' => [
                'code' => 422,
                'error' => 'The Password field is required',
                'content' => [
              //      'email' => 'The Email field is required',
                    'password' => 'The Password field is required'
                ]
            ],
            'no email' => [
                'code' => 401,
                'email' => '',
                'password' => 'anypassword',
                'error' => 'Invalid user or password'
            ],
            'no password' => [
                'code' => 422,
                'email' => 'admin@airsend.io',
                'error' => 'The Password field is required',
                'content' => [
                    'password' => 'The Password field is required'
                ]
            ],
            'malformed email' => [
                'code' => 422,
                'email' => 'notanemail',
                'password' => 'anypassword',
                'error' => 'Invalid Email or Phone Number',
                'content' => [
                    'user' => 'Invalid Email or Phone Number'
                ]
            ],
            'non existent user' => [
                'code' => 401,
                'email' => 'johnsnow@airsend.io',
                'password' => 'anypassword',
                'error' => 'Invalid user or password'
            ],
            'wrong password' => [
                'code' => 401,
                'email' => 'admin@airsend.io',
                'password' => 'wrong password',
                'error' => 'Invalid user or password'
            ],
        ];
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @throws Exception
     * @dataProvider failedLoginExamples
     */
    public function failedLoginTest(ApiTester $I, \Codeception\Example $example)
    {
        $this->login($I, $example['email'] ?? null, $example['password'] ?? null);
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

    /**
     * @param ApiTester $I
     * @incomplete

    public function logoutTest(ApiTester $I)
    {
        // TODO
    }*/

    /**
     * @param ApiTester $I
     * @throws Exception
     * @depends successfulLoginTest
     */
    public function authenticatedAccessThroughHeader(ApiTester $I)
    {
        $this->login($I, 'admin@airsend.io', $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->haveHttpHeader('authorization', "Bearer {$this->jwtToken}");
        $I->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $I->sendGET('/system.info');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson([
            'meta' => ['ok' => true],
            'info' => ['name' => 'AirSend'],
        ]);
    }

    /**
     * @param ApiTester $I
     * @throws Exception
     * @depends successfulLoginTest
     */
    public function authenticatedAccessThroughQueryParam(ApiTester $I)
    {
        $this->login($I, 'admin@airsend.io', $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $I->sendGET("/system.info", ['token' => $this->jwtToken]);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson([
            'meta' => ['ok' => true],
            'info' => ['name' => 'AirSend'],
        ]);
    }

    /**
     * @param ApiTester $I
     * @throws Exception
     * @depends successfulLoginTest
     */
    public function authenticatedAccessThroughCookie(ApiTester $I)
    {
        $this->login($I, 'admin@airsend.io', $this->adminPassword);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->haveHttpHeader('cookie', "token={$this->jwtToken};");
        $I->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $I->sendGET('/system.info');

        if (getenv('ENABLE_COOKIE_AUTH')) {
            $I->seeResponseCodeIsSuccessful();
            $I->seeResponseContainsJson([
                'meta' => ['ok' => true],
                'info' => ['name' => 'AirSend'],
            ]);
        } else {
            $I->seeResponseCodeIs(401);
            $I->seeResponseContainsJson(['meta' => ['ok' => false]]);
        }
    }

    /**
     * @return array
     */
    protected function failedAuthenticationExamples()
    {
        return [
            'without token' => [
                'token' => null,
                'error' => 'Missing token'
            ],
            'malformed jwt token' => [
                'token' => 'invalidtoken',
                'error' => 'Invalid token'
            ],
            'malformed header 1' => [
                'token' => 'badheader.payload.signature',
                'error' => 'Invalid token'
            ],
            'bad user agent' => [
                'token' => 'badheader.payload.signature',
                'error' => 'Invalid token',
                'userAgent' => 'Another fake user agent',
            ],
        ];
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider failedAuthenticationExamples
     * @throws Exception
     */
    public function failedAuthenticationThroughHeader(ApiTester $I, \Codeception\Example $example)
    {

        if ($example['token'] !== null) {
            $header = "Bearer {$example['token']}";
            $I->haveHttpHeader('Authorization', $header);
        }

        $I->haveHttpHeader('user-agent', $example['userAgent'] ?? static::DEFAULT_USER_AGENT);

        $I->sendRawGET('/system.info');
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => $example['error']]]);
    }

}