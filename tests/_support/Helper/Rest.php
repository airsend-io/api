<?php

namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module\REST as ParentRest;
use Exception;

class Rest extends ParentRest
{

    const DEFAULT_USER_AGENT = 'API tests suite';

    /**
     * @var string
     */
    protected $jwtToken;

    public function _initialize()
    {
        parent::_initialize();
        //$this->config['url'] = 'http://web:81/api/v1/';
    }

    protected function setupDefaultHeaders()
    {
        $this->haveHttpHeader('user-agent', static::DEFAULT_USER_AGENT);
        $this->haveHttpHeader('Accept', 'application/json');
        $this->haveHttpHeader('Content-Type', 'application/json');
        $this->haveHttpHeader('X-Airsend-tests', 1);
    }

    protected function setupAuthentication()
    {
        $this->haveHttpHeader('Authorization', "Bearer {$this->jwtToken}");
    }

    /**
     * Logs the user in, saving the jwt token for future requests
     *
     * @param string $email
     * @param string $password
     * @param string|null $token
     * @return string
     * @throws Exception
     * @part json
     * @part xml
     */
    public function loginUser(string $email, string $password, ?string $token = null): string
    {

        if ($token === null) {
            // if no token is provided, send a login request and get the token
            $payload = compact('email', 'password');
            $this->sendRawPOST('/user.login', $payload);
            if (!$token = $this->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
                throw new Exception('Failed to login');
            }
        }

        // return the token to the caller, so he can store it as a cache
        $this->jwtToken = $token;
        return $this->jwtToken;

    }

    /**
     * Logs the user in as admin, saving the jwt token for future requests
     *
     * @param string $email
     * @param string $password
     * @param string|null $token
     * @return string
     * @throws Exception
     * @part json
     * @part xml
     */
    public function loginAdmin(string $email, string $password, ?string $token = null): string
    {

        if ($token === null) {
            // if no token is provided, send a login request and get the token
            $payload = compact('email', 'password');
            $this->sendRawPOST('/admin.login', $payload);
            if (!$token = $this->grabDataFromResponseByJsonPath('$.token')[0] ?? null) {
                throw new Exception('Failed to login');
            }
        }

        // return the token to the caller, so he can store it as a cache
        $this->jwtToken = $token;
        return $this->jwtToken;

    }

    /**
     * Sends a GET request to the API with the default headers set (including auth)
     * @param $url
     * @param array $params
     * @part json
     * @part xml
     */
    public function sendGET($url, $params = [])
    {
        $this->setupDefaultHeaders();
        $this->setupAuthentication();
        parent::sendGET($url, $params);
    }

    /**
     * Sends a GET request to the API, without setting any header
     * @param $url
     * @param array $params
     * @part json
     * @part xml
     */
    public function sendRawGET($url, $params = [])
    {
        $this->setupDefaultHeaders();
        parent::sendGET($url, $params);
    }

    /**
     * Sends a POST request to the API with the default headers set (including auth)
     * @param $url
     * @param array $params
     * @param array $files
     * @part json
     * @part xml
     */
    public function sendPOST($url, $params = [], $files = [])
    {
        $this->setupDefaultHeaders();
        $this->setupAuthentication();
        parent::sendPOST($url, $params, $files);
    }

    /**
     * Sends a POST request to the API, without setting any header
     *
     * @param $url
     * @param array $params
     * @param array $files
     * @part json
     * @part xml
     */
    public function sendRawPOST($url, $params = [], $files = [])
    {
        $this->setupDefaultHeaders();
        parent::sendPOST($url, $params, $files);
    }

    /**
     * Clears all client cookies
     *
     * @part json
     * @part xml
     */
    public function clearCookies()
    {
        $this->client->getCookieJar()->clear();
    }

}