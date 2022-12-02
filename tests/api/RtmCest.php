<?php

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * Class RtmCest
 * @group rtm
 */
class RtmCest
{

    protected $adminPassword;

    public function _before(ApiTester $I)
    {
        $this->adminPassword = getenv('APP_ADMIN_PASSWORD') ?: '3vfP2u89zbSs8x4w';
    }

    /**
     * @param ApiTester $I
     */
    public function testRtmConnect(ApiTester $I)
    {
        $I->loginUser('admin@airsend.io', $this->adminPassword);

        $I->sendGET('/rtm.connect');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $protocol = getenv('AIRSEND_WEBSOCKET_PROTOCOL') ?: 'ws';
        $host = getenv('AIRSEND_DEPLOYMENT_HOSTNAME') ?: 'localhost';
        $port = getenv('AIRSEND_WEBSOCKET_PORT' ?: '9001');

        $endpoint = "$protocol://$host:$port";

        $I->seeResponseContainsJson([
            'meta' => ['ok' => true],
            'rtm' => ['ws_endpoint' => $endpoint]
        ]);

    }


}