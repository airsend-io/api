<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware\Auth;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\OAuth\AccessTokenDataStore;
use CodeLathe\Core\Data\OAuth\ClientDataStore;
use CodeLathe\Core\Data\OAuth\ScopeDataStore;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\AuthResult;
use CodeLathe\Core\Objects\OAuth\Scope;
use CodeLathe\Core\PublicHashHandlers\AbstractPublicHashHandler;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\LoggerFacade;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Auth\Exceptions\InvalidTokenException;
use CodeLathe\Service\Auth\JwtServiceInterface;
use DateTime;
use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface;

trait AuthMiddlewareTrait
{

    abstract protected function oauthClientDataStore(): ClientDataStore;

    public function extractToken(ServerRequestInterface $request): ?string
    {
        // try to extract the token from the request
        $token = $this->tryTokenFromAuthorizationHeader($request)
            ?? $this->tryTokenFromAuthorizationAltHeader($request)
            ?? $this->tryTokenFromQueryString($request)
            ?? $this->tryTokenFromCookie($request) // TODO change it just for development env
            ?? null;

        return $token;
    }

    /**
     * Try to extract the token from the Authorization header, using the Bearer pattern
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    protected function tryTokenFromAuthorizationHeader(ServerRequestInterface $request): ?string
    {
        if (
            !empty($authHeader = $request->getHeader('Authorization')) &&
            preg_match('/^Bearer\s+(.+)$/', trim($authHeader[0]), $matches)
        ) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Try to extract the token from the Alt_Authorization header, using the Bearer pattern
     * This is needed in some cases when there is a S3 redirect and it doesn't like the Authorization header coming from the client
     *
     * @param ServerRequestInterface $request
     * @return string|null
     */
    protected function tryTokenFromAuthorizationAltHeader(ServerRequestInterface $request): ?string
    {
        if (
            !empty($authHeader = $request->getHeader('AuthorizationAlt')) &&
            preg_match('/^Bearer\s+(.+)$/', trim($authHeader[0]), $matches)
        ) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Try to extract the token from the query string
     * @param ServerRequestInterface $request
     * @param string $varName
     * @return string|null
     */
    protected function tryTokenFromQueryString(ServerRequestInterface $request, string $varName = 'token'): ?string
    {
        return $request->getQueryParams()[$varName] ?? null;
    }

    abstract protected function getConfig(): ConfigRegistry;

    /**
     * Try to extract the token from a cookie
     *
     * @param ServerRequestInterface $request
     * @param string $cookieName
     * @return string|null
     */
    protected function tryTokenFromCookie(ServerRequestInterface $request, string $cookieName = 'token')
    {
        if ($this->getConfig()->get('/app/authcookieenable') == '1') {
            $cookies = $request->getCookieParams();
            return $cookies[$cookieName] ?? null;
        }
        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @param JwtServiceInterface $jwt
     * @param bool|null $checkTTL
     * @return AuthResult
     * @throws Exception
     */
    protected function authenticateUsingJwt(ServerRequestInterface &$request, JwtServiceInterface $jwt, ?bool $checkTTL = true): AuthResult
    {
        // get the auth object from the request (initialized on the RequestMiddleware
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        // try to extract the token from the request
        $token = $this->extractToken($request);

        // if there is no token respond Unauthorized
        if ($token === null) {
            return new AuthResult(false, 'Missing token');
        }

        // try to decode the token as a JWT token, and halt the execution if it fails
        $payload = null;
        try {
            $payload = $jwt->decodeToken($token, $checkTTL);
        } catch (InvalidTokenException $e) {
            return new AuthResult(false, $e->getMessage());
        }

        // check if the token was issued for the current ip (cip claim)
        $checkIp = $this->getConfig()->get('/auth/checkip');
        if ($checkIp && !Utility::sameIp($payload['cip'], $auth->getClientIp())) {
            return new AuthResult(false, 'Invalid token');
        }

        // TODO: need to be able to allow web view components to seamlessly reuse JWTs using the same useragents
        // check if the token was issued for the current user agent (cag claim)
        //if ($payload['cag'] != $auth->getUserAgent()) {
        //  return new AuthResult(false, 'Invalid token');
        //}

        // check the token scope
        if ($payload['scp'] !== 'global' && !$this->checkScope($request->getUri()->getPath(), $payload['scp'])) {
            return new AuthResult(false, 'Invalid scope');
        }

        $auth->setUserId((int)$payload['sub']);
        $auth->setIsAdmin((bool)$payload['admin']);
        $auth->setJwtJti($payload['jti']);
        $auth->setExpirationTime($expirationTime = (int)$payload['exp']);


        $request = $request->withAttribute('auth', $auth);

        // if the there is a refresh token, include it as a request attribute
        if (isset($payload['rtk'])) {
            $request = $request->withAttribute('refresh_token', $payload['rtk']);
        }

        return new AuthResult(true);
    }

    protected function checkScope(string $uri, string $scope): bool
    {
        foreach (explode(';', $scope) as $pattern) {
            $pattern = '/^' . preg_quote(trim($pattern), '/') . '$/';
            $pattern = str_replace('\*', '.*', $pattern);
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }
        return false;
    }

    protected function authenticateUsingOauthServer(ServerRequestInterface &$request,
                                                    ResourceServer $resourceServer,
                                                    ScopeDataStore $scopeDataStore)
    {

        // get the auth object from the request (initialized on the RequestMiddleware
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        // set the Bearer token if the token was provided through the query string
        $token = $request->getQueryParams()['token'] ?? null;
        if ($token !== null) {
            $request = $request->withHeader('authorization', "Bearer $token");
        }

        try {
            $result = $resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $e) {
            return new AuthResult(false, $e->getMessage());
        }
        $userId = (int)$result->getAttribute('oauth_user_id');

        $clientId = $result->getAttribute('oauth_client_id');
        $auth->setOauthClientId($clientId);
        if (empty($userId)) {
            // TODO - Only if the app allows to act in owners name
            //$userId = $this->oauthClientDataStore()->getClientEntity($clientId)->getOwnerId();
        }

        $tokenId = $result->getAttribute('oauth_access_token_id');
        $auth->setOauthTokenId($tokenId);

        // check the token scope
        if (!$this->checkOauthScope($request->getUri()->getPath(), $result->getAttribute('oauth_scopes'), $scopeDataStore)) {
            return new AuthResult(false, 'Invalid scope');
        }

        $auth->setUserId($userId);
        $auth->setIsAdmin(false);

        $request = $request->withAttribute('auth', $auth);

        return new AuthResult(true);
    }

    protected function checkOauthScope(string $uri, array $scopes, ScopeDataStore $scopeDataStore): bool
    {
        foreach ($scopes as $scope) {
            if ($scope === 'global') {
                return true;
            }
            if (!preg_match('/^\/api\/v[0-9]+\/(.*)$/', $uri, $matches)) {
                return false;
            }
            $route = $matches[1];
            foreach ($scopeDataStore->scopeRoutes($scope) as $allowedScope) {
                $pattern = '/^' . preg_quote(trim($allowedScope), '/') . '$/';
                $pattern = str_replace('\*', '.*', $pattern);
                if (preg_match($pattern, $route)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DataController $dataController
     * @param bool|null $checkTTL
     * @return AuthResult
     * @throws Exception
     */
    protected function authenticateUsingNotificationToken(ServerRequestInterface &$request, DataController $dataController, ?bool $checkTTL = true): AuthResult
    {
        // get the auth object from the request (initialized on the RequestMiddleware
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        // try to extract the token from the request
        $token = $this->extractToken($request);

        // if there is no token respond Unauthorized
        if ($token === null) {
            return new AuthResult(false, 'Missing token');
        }

        $notification = $dataController->getNotificationByToken($token);

        if ($notification === null) {
            return new AuthResult(false, 'Invalid token.');
        }

        /** @var DateTime $expirationTime */
        $expirationTime = $notification->getExpirationTime();

        if ($checkTTL && $expirationTime < (new DateTime())) {
            return new AuthResult(false, 'Expired token');
        }

        $auth->setUserId($notification->getUserId());
        $auth->setIsAdmin(false);
        $auth->setExpirationTime($expirationTime->getTimestamp());

        // attach the notification that generated the token for the request
        $request = $request->withAttribute('notification', $notification);

        $request = $request->withAttribute('auth', $auth);

        return new AuthResult(true);
    }

    /**
     * @param ServerRequestInterface $request
     * @param DataController $dataController
     * @return AuthResult
     * @throws DatabaseException
     */
    protected function authenticateUsingPublicHash(ServerRequestInterface &$request, DataController $dataController): AuthResult
    {

        // get the auth object from the request (initialized on the RequestMiddleware
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        // try to extract the token from the request
        $token = $this->extractToken($request);

        // if there is no token respond Unauthorized
        if ($token === null) {
            return new AuthResult(false, 'Missing token');
        }

        if (!preg_match('/^pub-([a-z0-9]+)/', $token, $matches)) {
            return new AuthResult(false, 'Token is not a public hash');
        }

        $publicHash = $matches[1];

        // find the public hash object
        $publicHashObject = $dataController->findPublicHash($publicHash);
        if ($publicHashObject === null) {
            return new AuthResult(false, 'Invalid public hash');
        }

        // validate public hash permissions
        $publicHashHandlerClass = '\\CodeLathe\\Core\\PublicHashHandlers\\' . $publicHashObject->getResourceType();
        if (!class_exists($publicHashHandlerClass)) {
            // should never happen, unless we have a bug on the public hash creation
            throw new \Exception("Invalid public resource type: {$publicHashObject->getResourceType()}");
        }
        /** @var AbstractPublicHashHandler $publicHashHandler */
        $publicHashHandler = ContainerFacade::get($publicHashHandlerClass);
        if (!$publicHashHandler->allow($request, $publicHashObject->getResourceId())) {
            return new AuthResult(false, 'Unauthorized for this hash');
        }

        // set logged user to the public user
        $auth->setUserId($this->config->get('/auth/public_user_id'));
        $auth->setIsAdmin(false);
        $auth->setPublicHash($publicHash);

        $request = $request->withAttribute('auth', $auth);

        return new AuthResult(true);


    }


    /**
     * This is mainly used by public caller to get profile thumb
     * @param ServerRequestInterface $request
     * @param DataController $dataController
     * @return AuthResult
     * @throws DatabaseException
     */
    protected function authenticateUsingCallHash(ServerRequestInterface &$request, DataController $dataController): AuthResult
    {
        $callHash = $request->getQueryParams()['call_hash'] ?? null;
        if ($callHash === null) {
            return new AuthResult(false, 'Missing call info');
        }

        if (empty($call = $dataController->getCallByHash($callHash))) {
            return new AuthResult(false, 'Invalid call info');
        }

        $auth = $request->getAttribute('auth');
        // set logged user to the public user
        $auth->setUserId($this->config->get('/auth/public_user_id'));
        $auth->setIsAdmin(false);
        $request = $request->withAttribute('auth', $auth);

        return new AuthResult(true);
    }

}
