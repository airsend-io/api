<?php

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class RtmCest
 * @group security
 */
class SecurityCest
{

    const TEST_USER = 'channelunittest@airsendmail.com';
    const TEST_USER_PASSWORD = 'password1';

    protected $adminPassword;

    public function _before()
    {
        $this->adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';
    }

    /**
     * Assert that the authentication was accepted.
     *
     * We just verify that the return code can't be some codes, because on those tests, we don't pass any parameter.
     *
     * The only errors that should be accepted are 422 (Validation error), and sometimes (not desirable because it's
     * too much generic) 400 (Bad Request).
     *
     * @param ApiTester $I
     */
    protected function checkAuthenticationWorked(ApiTester $I)
    {
        $I->dontSeeResponseCodeIs(401); // 401 it's a obvious authentication error, that can't happen
        $I->dontSeeResponseCodeIs(404); // 404 shows that the endpoint don't exist (probably an user error)
        $I->dontSeeResponseCodeIs(405); // 405 same as 404
        $I->dontSeeResponseCodeIs(415); // Malformed request (normally bad content-type)
        // $I->dontSeeResponseCodeIs(500); TODO - Put it back, and handle the 500 errors correctly (a bunch of endpoints is failing here)

    }

    /**
     * This data provider translates the authentication rules from the routes file and the API documentation, to a
     * examples provider. Each endpoint should return 3 fields:
     * - The method used to access it (get or post)
     * - The route itself (starting with a slash, without the prefix and all route params included)
     * - The expected auth behavior. This is a 3 digits binary number:
     *   - 1st digit: Is the endpoint accessible without any authentication?
     *   - 2nd digit: Is the endpoint accessible when you're logged to the system, with a real user
     *   - 3rd digit: Is the endpoint accessible for users that have a read-only token
     *
     * Important note: All tests here just check for the return code 401 (authentication errors) or for anything
     * different from 401. Checking success of the endpoints is out of the scope of this test. The only things that we
     * ensure, is that any endpoint can return 404 (Not Found) 405 (Method not allowed) or 415 (Unsupported Media Type),
     * to avoid false positives (in the case that a developer add an endpoint with a typo, or with wrong method, i.e).
     * Codes like 400 or 422 are perfectly fine, since we don't send any real parameters with the requests.
     *
     * @return array
     */
    protected function endpointsExamples(): array
    {

        return [
            [
                'method' => 'post',
                'route' => '/email.receive',
                'responses' => 0b111
            ],
            [
                'method' => 'get',
                'route' => '/oauth.google',
                'responses' => 0b111
            ],
            [
                'method' => 'get',
                'route' => '/oauth.linkedin',
                'responses' => 0b111
            ],
            [
                'method' => 'post',
                'route' => '/oauth.google',
                'responses' => 0b111
            ],
            [
                'method' => 'post',
                'route' => '/oauth.linkedin',
                'responses' => 0b111
            ],

            // USER -------------------------
            [
                'method' => 'post',
                'route' => '/user.login',
                'responses' => 0b111
            ],
            [
                'method' => 'post',
                'route' => '/user.login.refresh',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/user.create',
                'responses' => 0b111
            ],
            [
                'method' => 'post',
                'route' => '/user.finalize',
                'responses' => 0b011
            ],
            [
                'method' => 'post',
                'route' => '/user.image.set',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/user.image.get',
                'responses' => 0b011
            ],
            [
                'method' => 'post',
                'route' => '/user.verify',
                'responses' => 0b111
            ],
            [
                'method' => 'get',
                'route' => '/user.info',
                'responses' => 0b011
            ],
            [
                'method' => 'post',
                'route' => '/user.profile.set',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/user.alerts',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/user.alert.ack',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/user.notifications.manage',
                'responses' => 0b011
            ],
            [
                'method' => 'post',
                'route' => '/user.notifications.report',
                'responses' => 0b001
            ],

            // PASSWORD -------------------
            [
                'method' => 'post',
                'route' => '/password.recover',
                'responses' => 0b111
            ],
            [
                'method' => 'post',
                'route' => '/password.reset',
                'responses' => 0b111
            ],
            [
                'method' => 'post',
                'route' => '/password.update',
                'responses' => 0b010
            ],

            // FILE -----------------------
            [
                'method' => 'post',
                'route' => '/file.upload',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/file.create',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/file.delete',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/file.info',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/file.move',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/file.copy',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/file.versions',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/file.synclist',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/file.thumb',
                'responses' => 0b011
            ],
            [
                'method' => 'get',
                'route' => '/file.download',
                'responses' => 0b011
            ],
            [
                'method' => 'get',
                'route' => '/file.list',
                'responses' => 0b011
            ],

            // CHANNEL --------------------
            [
                'method' => 'get',
                'route' => '/channel.list',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/channel.members',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.create',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.invite',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.rename',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.kick',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.leave',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.close',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.remove',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.activate',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.image.set',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.user.setrole',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.update',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/channel.notifications.manage',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/channel.history',
                'responses' => 0b011
            ],
            [
                'method' => 'get',
                'route' => '/channel.info',
                'responses' => 0b011
            ],
            [
                'method' => 'get',
                'route' => '/channel.image.get',
                'responses' => 0b011
            ],

            // ACTION ---------------------
            [
                'method' => 'post',
                'route' => '/action.create',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/action.info',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/action.update',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/action.delete',
                'responses' => 0b010
            ],
            [
                'method' => 'get',
                'route' => '/action.list',
                'responses' => 0b011
            ],

            // CHAT ---------------------
            [
                'method' => 'post',
                'route' => '/chat.postmessage',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/chat.updatemessage',
                'responses' => 0b010
            ],
            [
                'method' => 'post',
                'route' => '/chat.deletemessage',
                'responses' => 0b010
            ],

            // SYSTEM -------------------
            [
                'method' => 'get',
                'route' => '/system.info',
                'responses' => 0b010
            ],

            // RTM ---------------------
            [
                'method' => 'get',
                'route' => '/rtm.connect',
                'responses' => 0b010
            ],

            // WIKI --------------------
            [
                'method' => 'get',
                'route' => '/wiki.get/mypath',
                'responses' => 0b011
            ],

            // SEARCH -------------------
            [
                'method' => 'get',
                'route' => '/search.query',
                'responses' => 0b010
            ],

        ];
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider endpointsExamples
     */
    public function testUnauthenticatedAccess(ApiTester $I, \Codeception\Example $example)
    {

        if ($example['method'] === 'get') {
            $I->sendRawGET($example['route']);
        } else {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendRawPOST($example['route']);
        }

        if ($example['responses'] & 0b100) {
            $this->checkAuthenticationWorked($I);
        } else {
            $I->seeResponseCodeIs(401);
        }

    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider endpointsExamples
     */
    public function testAuthenticatedAccess(ApiTester $I, \Codeception\Example $example)
    {
        $I->loadFixture('security/init');
        $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        if ($example['method'] === 'get') {
            $I->sendGET($example['route']);
        } else {
            $I->sendPOST($example['route']);
        }
        if ($example['responses'] & 0b010) {
            $this->checkAuthenticationWorked($I);
        } else {
            $I->seeResponseCodeIs(401);
        }
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider endpointsExamples
     */
    public function testReadOnlyAccess(ApiTester $I, \Codeception\Example $example)
    {
        $I->loadFixture('security/init');

        if ($example['method'] === 'get') {
            $I->sendRawGET($example['route'], ['token' => 'myawesometoken']);
        } else {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendRawPOST("{$example['route']}?token=myawesometoken");
        }

        if ($example['responses'] & 0b001) {
            $this->checkAuthenticationWorked($I);
        } else {
            $I->seeResponseCodeIs(401);
        }
    }

    protected function adminEndpointsExamples()
    {
        return [

            // internals endpoints that requires admin permissions...
            [
                'method' => 'get',
                'route' => '/internal/file.download'
            ],
            [
                'method' => 'post',
                'route' => '/internal/file.sidecarupload',
            ],

            // service admin system endpoints

            // USER ----------------
            [
                'method' => 'post',
                'route' => '/admin.user.approve',
            ],
            [
                'method' => 'get',
                'route' => '/admin.user.search',
            ],
            [
                'method' => 'post',
                'route' => '/admin.user.create',
            ],
            [
                'method' => 'get',
                'route' => '/admin.user.info',
            ],
            [
                'method' => 'post',
                'route' => '/admin.user.update',
            ],
            [
                'method' => 'post',
                'route' => '/admin.user.delete',
            ],

            // CHANNEL ------------------
            [
                'method' => 'get',
                'route' => '/admin.channel.search',
            ],
            [
                'method' => 'post',
                'route' => '/admin.channel.create',
            ],
            [
                'method' => 'post',
                'route' => '/admin.channel.update',
            ],
            [
                'method' => 'post',
                'route' => '/admin.channel.delete',
            ],
            [
                'method' => 'get',
                'route' => '/admin.channel.info',
            ],
            [
                'method' => 'get',
                'route' => '/admin.channel.user.list',
            ],
            [
                'method' => 'post',
                'route' => '/admin.channel.user.add',
            ],
            [
                'method' => 'post',
                'route' => '/admin.channel.user.update',
            ],

            // STATS --------------------
            [
                'method' => 'get',
                'route' => '/admin.stats.dashboard',
            ],
            [
                'method' => 'get',
                'route' => '/admin.stats.websocket',
            ],
            [
                'method' => 'get',
                'route' => '/admin.stats.redis',
            ],

            // MIGRATIONS ------
            [
                'method' => 'post',
                'route' => '/admin.dbversion.upgrade',
            ],
            [
                'method' => 'get',
                'route' => '/admin.dbversion.info',
            ],
            [
                'method' => 'get',
                'route' => '/admin.dbversion.list',
            ],

            // TEAMS ----------
            [
                'method' => 'get',
                'route' => '/admin.team.search',
            ],
            [
                'method' => 'post',
                'route' => '/admin.team.create',
            ],
            [
                'method' => 'post',
                'route' => '/admin.team.update',
            ],
            [
                'method' => 'post',
                'route' => '/admin.team.delete',
            ],
            [
                'method' => 'get',
                'route' => '/admin.team.info',
            ],
            [
                'method' => 'get',
                'route' => '/admin.team.user.list',
            ],
            [
                'method' => 'post',
                'route' => '/admin.team.user.add',
            ],
            [
                'method' => 'post',
                'route' => '/admin.team.user.delete',
            ],

            // NOTIFICATIONS ---------
            [
                'method' => 'get',
                'route' => '/admin.notification.abuse-report',
            ],
            [
                'method' => 'post',
                'route' => '/admin.notification.abuse-report.delete/1',
            ],
        ];
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider adminEndpointsExamples
     */
    public function testAdminUnauthenticatedAccess(ApiTester $I,  \Codeception\Example $example)
    {
        $I->loadFixture('security/init');

        if ($example['method'] === 'get') {
            $I->sendGET($example['route']);
        } else {
            $I->sendPOST($example['route']);
        }

        $I->seeResponseCodeIs(401);
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider adminEndpointsExamples
     */
    public function testAdminAuthenticatedNotAdminAccess(ApiTester $I,  \Codeception\Example $example)
    {
        $I->loadFixture('security/init');

        $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        if ($example['method'] === 'get') {
            $I->sendGET($example['route']);
        } else {
            $I->sendPOST($example['route']);
        }

        $I->seeResponseCodeIs(403);
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider adminEndpointsExamples
     */
    public function testAdminAuthenticatedAccess(ApiTester $I, \Codeception\Example $example)
    {
        $I->loadFixture('security/init');

        $I->loginAdmin('admin@airsend.io', $this->adminPassword);

        if ($example['method'] === 'get') {
            $I->sendGET($example['route']);
        } else {
            $I->sendPOST($example['route']);
        }

        $this->checkAuthenticationWorked($I);
    }

    protected function internalEndpointsExamples()
    {
        return [
            [
                'method' => 'post',
                'route' => '/internal/bgprocess',
            ],
            [
                'method' => 'post',
                'route' => '/internal/cron',
            ],
        ];
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider internalEndpointsExamples
     */
    public function testInternalAuthenticatedAccess(ApiTester $I, \Codeception\Example $example)
    {

        $token = "BG_MESSAGE_KEY_@#$%_@HGDDFFDG_@#$#$%@#$@4342234_55";

        if ($example['method'] === 'get') {
            $I->sendRawGET($example['route'], ['auth_token' => $token]);
        } else {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendRawPOST($example['route'], ['auth_token' => $token]);
        }

        $this->checkAuthenticationWorked($I);
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider internalEndpointsExamples
     */
    public function testInternalUnauthenticatedAccess(ApiTester $I, \Codeception\Example $example)
    {
        if ($example['method'] === 'get') {
            $I->sendRawGET($example['route']);
        } else {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendRawPOST($example['route']);
        }

        $I->seeResponseCodeIs(401);
    }

}