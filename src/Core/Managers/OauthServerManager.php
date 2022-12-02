<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use Carbon\CarbonInterval;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\OAuth\AccessTokenDataStore;
use CodeLathe\Core\Data\OAuth\ClientDataStore;
use CodeLathe\Core\Data\OAuth\ScopeDataStore;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Utility\JsonOutput;
use Illuminate\Support\Str;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Stream;
use Throwable;

class OauthServerManager extends ManagerBase
{

    /**
     * @var AuthorizationServer
     */
    protected $oauthServer;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var AccessTokenDataStore
     */
    protected $tokenDataStore;

    /**
     * @var ClientDataStore
     */
    protected $clientDataStore;

    /**
     * @var ScopeDataStore
     */
    protected $scopeDataStore;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * BackgroundDispatcher constructor.
     * @param AuthorizationServer $oauthServer
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param CacheItemPoolInterface $cache
     * @param AccessTokenDataStore $tokenDataStore
     * @param ClientDataStore $clientDataStore
     * @param ScopeDataStore $scopeDataStore
     * @param ConfigRegistry $config
     */
    public function __construct(AuthorizationServer $oauthServer,
                                DataController $dataController,
                                LoggerInterface $logger,
                                CacheItemPoolInterface $cache,
                                AccessTokenDataStore $tokenDataStore,
                                ClientDataStore $clientDataStore,
                                ScopeDataStore $scopeDataStore,
                                ConfigRegistry $config)
    {
        $this->oauthServer = $oauthServer;
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->tokenDataStore = $tokenDataStore;
        $this->clientDataStore = $clientDataStore;
        $this->scopeDataStore = $scopeDataStore;
        $this->config = $config;
    }

    public function clientList(Request $request, Response $response): Response
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $clients = $this->clientDataStore->getClientsByOwner($user->getId());

        return JsonOutput::success()
            ->withContent('clients', $clients)
            ->write($response);
    }

    public function createClient(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $name = $params['name'] ?? '';
        if (empty($name)) {
            return JsonOutput::error("Client name is required.", 422)->write($response);
        }

        $description = $params['description'] ?? '';
        if (empty($description)) {
            return JsonOutput::error("Client description is required.", 422)->write($response);
        }

        $grantType = $params['grant_type'] ?? '';
        if (empty($grantType) || !in_array($grantType, ['authorization_code', 'client_credentials'])) {
            return JsonOutput::error("Client grant type is required and must be 'authorization_code' or 'client_credentials.", 422)->write($response);
        }

        $redirect = isset($params['redirect']) ? trim($params['redirect']) : null;
        if ($redirect === null && $grantType === 'authorization_code') {
            return JsonOutput::error("The redirect urls are required for 'authorization_code' grant.", 422)->write($response);
        }

        $client = $this->clientDataStore->createClient((int)$user->getIdentifier(), $name, $description, $grantType, $redirect);
        return JsonOutput::success()
            ->withContent('client', $client)
            ->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws DatabaseException
     */
    public function authorize(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        $redirect = (bool)($params['redirect'] ?? true);

        try {

            // Validate the HTTP request and return an AuthorizationRequest object.
            $authRequest = $this->oauthServer->validateAuthorizationRequest($request);

            // if there is a valid token set for this user, don't need to ask for new authorization. Just return the code.
            if ($this->tokenDataStore->existsValidTokenForUserAndScope($authRequest, $user->getId())) {

                // Set the user
                $authRequest->setUser($user); // an instance of UserEntityInterface.

                // Once the user has approved or denied the client update the status
                // (true = approved, false = denied)
                $authRequest->setAuthorizationApproved(true);

                // Return the HTTP response
                return $this->completeAuthorizationRequest($authRequest, $response, $redirect);

            }

        } catch (OAuthServerException $exception) {

            return $this->handleOauthException($exception, $response, $redirect);

        } catch (Throwable $exception) {

            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);

        }

        $requestKey = Str::random(32);
        $cacheKey = "oauthserver.authorizationrequest.{$user->getIdentifier()}.$requestKey";
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set(\serialize($authRequest));
        $cacheItem->expiresAfter(CarbonInterval::fromString($this->config->get('/oauth/approve_request_key_ttl'))->totalSeconds);
        $this->cache->save($cacheItem);

        $scopes = [];
        foreach ($authRequest->getScopes() as $scope) {
            $scopeId = $scope->getIdentifier();
            $scopes[] = $this->scopeDataStore->scopeDescription($scopeId);
        }

        return JsonOutput::success()
            ->withContent('name', $authRequest->getClient()->getName())
            ->withContent('description', $authRequest->getClient()->getDescription())
            ->withContent('scopes', $scopes)
            ->withContent('request_key', $requestKey)
            ->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     */
    public function approveAuthorization(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $approve = (bool)($params['approve'] ?? false);
        $redirect = (bool)($params['redirect'] ?? true);

        $requestKey = $params['request_key'] ?? null;
        if ($requestKey === null) {
            return JsonOutput::error('Request id is required', 400)->write($response);
        }

        // try to find the previous auth request on the cache
        $cacheKey = "oauthserver.authorizationrequest.{$user->getId()}.$requestKey";
        $cacheItem = $this->cache->getItem($cacheKey);
        if (!$cacheItem->isHit()) {
            return JsonOutput::error('Invalid request id', 422)->write($response);
        }
        $authRequest = \unserialize($cacheItem->get());

        try {

            // Set the user
            $authRequest->setUser($user); // an instance of UserEntityInterface.

            // Set the request as approved
            $authRequest->setAuthorizationApproved($approve);

            if (!$approve) {
                $this->cache->deleteItem($cacheKey);
            }

            // Return the HTTP redirect response
            return $this->completeAuthorizationRequest($authRequest, $response, $redirect);

        } catch (OAuthServerException $exception) {

            return $this->handleOauthException($exception, $response, $redirect);

        } catch (Throwable $exception) {

            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);

        }
    }

    public function accessToken(Request $request, Response $response): Response
    {

        try {

            // Try to respond to the request
            return $this->oauthServer->respondToAccessTokenRequest($request, $response);

        } catch (OAuthServerException $exception) {

            // All instances of OAuthServerException can be formatted into a HTTP response
            return $exception->generateHttpResponse($response);

        } catch (Throwable $exception) {

            // Unknown exception
            $body = new Stream(fopen('php://temp', 'r+'));
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);
        }
    }

    protected function dataController(): DataController
    {
        return $this->dataController;
    }

    protected function completeAuthorizationRequest(AuthorizationRequest $authRequest, Response $response, bool $redirect): Response
    {
        $response = $this->oauthServer->completeAuthorizationRequest($authRequest, $response);

        if ($redirect) {
            return $response;
        }

        // extract the location header and change status code
        $location = $response->getHeader('location');
        $location = $location[0];
        $response = $response->withStatus(200)->withoutHeader('location');

        return JsonOutput::success()->withContent('location', $location)->write($response);
    }

    protected function handleOauthException(OAuthServerException $exception, Response $response, bool $redirect): Response
    {
        if (!$redirect) {
            return JsonOutput::error($exception->getMessage(), 422)->write($response);
        }
        return $exception->generateHttpResponse($response);
    }
}

