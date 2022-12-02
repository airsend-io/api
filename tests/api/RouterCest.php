<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * This Cest should hold the general tests for routing (like errors, return codes, etc)
 *
 * Class RouterCest
 *
 * @group router
 */
class RouterCest
{
    public function _before(ApiTester $I)
    {
    }

    /**
     * @return array
     */
    protected function requestBodyParsingExamples()
    {
        return [
            'simple form-data request with valid body' => [
                'payload' => ['foo' => 'bar'],
                'expectedCode' => 200,
                'expectedResponse' => [
                    'meta' => ['ok' => true],
                    'requestPayload' => ['foo' => 'bar']
                ]
            ],
            'request without content type' => [
                'payload' => 'any body',
                'expectedCode' => 415,
                'expectedResponse' => [
                    'meta' => [
                        'ok' => false,
                        'error' => 'Content-Type not provided',
                    ]
                ]
            ],
            'request with invalid content type' => [
                'contentType' => 'application/fake',
                'payload' => 'any body',
                'expectedCode' => 415,
                'expectedResponse' => [
                    'meta' => [
                        'ok' => false,
                        'error' => 'Unsupported media type: application/fake'
                    ]
                ]
            ],
            'json request with invalid json' => [
                'contentType' => 'application/json',
                'payload' => '{"invalid":"json"',
                'expectedCode' => 415,
                'expectedResponse' => [
                    'meta' => [
                        'ok' => false,
                        'error' => 'Invalid json payload'
                    ]
                ]
            ],
            'correct json request' => [
                'contentType' => 'application/json',
                'payload' => '{"foo":"bar"}',
                'expectedCode' => 200,
                'expectedResponse' => [
                    'meta' => ['ok' => true],
                    'requestPayload' => ['foo' => 'bar']
                ]
            ],
        ];
    }

    /**
     * @param ApiTester $I
     * @param \Codeception\Example $example
     * @dataProvider requestBodyParsingExamples
     * @incomplete - The dev.* endpoints don't exists anymore, and they're not coming back, so we need to find a better way to test the app router
     */
    public function requestBodyParsingTest(ApiTester $I, \Codeception\Example $example)
    {
        if (isset($example['contentType'])) {
            $I->haveHttpHeader('content-type', $example['contentType']);
        }
        $I->sendPOST('/dev.echo', $example['payload']);
        $I->seeResponseCodeIs($example['expectedCode']);
        $I->seeResponseContainsJson($example['expectedResponse']);
    }

    /**
     * @param ApiTester $I
     */
    public function routeNotFoundTest(ApiTester $I)
    {
        $I->sendGet('nonexistentroute');
        $I->seeResponseCodeIs(404);
        // TODO - Check the json response (not working yet)
    }
}
