<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Auth;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\OAuth\AccessTokenDataStore;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\PhoneOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\Stream\Util;

class AuthManager
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var JwtServiceInterface
     */
    protected $jwt;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var PhoneOperations
     */
    protected $phoneOps;

    /**
     * @var UserOperations
     */
    protected $userOps;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var AccessTokenDataStore
     */
    protected $oauthTokenDataStore;

    /**
     * @var AuthOperations
     */
    protected $authOps;

    /**
     * AuthManager constructor.
     * @param LoggerInterface $logger
     * @param JwtServiceInterface $jwt
     * @param DataController $dataController
     * @param ConfigRegistry $config
     * @param EventManager $eventManager
     * @param PhoneOperations $phoneOps
     * @param CacheItemPoolInterface $cache ,
     * @param UserOperations $userOps
     * @param AccessTokenDataStore $oauthTokenDataStore
     * @param AuthOperations $authOps
     */
    public function __construct(
        LoggerInterface $logger,
        JwtServiceInterface $jwt,
        DataController $dataController,
        ConfigRegistry $config,
        EventManager $eventManager,
        PhoneOperations $phoneOps,
        CacheItemPoolInterface $cache,
        UserOperations $userOps,
        AccessTokenDataStore $oauthTokenDataStore,
        AuthOperations $authOps
    )
    {
        $this->logger = $logger;
        $this->jwt = $jwt;
        $this->dataController = $dataController;
        $this->config = $config;
        $this->eventManager = $eventManager;
        $this->phoneOps = $phoneOps;
        $this->cache = $cache;
        $this->userOps = $userOps;
        $this->oauthTokenDataStore = $oauthTokenDataStore;
        $this->authOps = $authOps;
    }

    /**
     * Log the user in using an email and password
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws \CodeLathe\Core\Exception\ChannelMalformedException
     * @throws \CodeLathe\Core\Exception\NotImplementedException
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['user', 'password'], $params, $response)) {
            return $response;
        }

        // TODO : Remove support for $params['email'] only support $params['user']
        if (empty($params['email'])) {
            $emailOrPhone = strtolower($params['user']);
        } else {
            $emailOrPhone = empty($params['user']) ? strtolower($params['email']) : $params['user'];
        }

        $rememberMe = (bool)($params['remember_me'] ?? false);

        if ($timezone = $params['timezone'] ?? null) {
            $availableTimezones = timezone_identifiers_list();
            if (!in_array($timezone, $availableTimezones)) {
                $timezone = null;
                $this->logger->info("Invalid timezone `$timezone` sent on login. Ignoring...");
            }
        }

        // if phone get the formatted phone number as we store the formatted number in db
        if (Utility::isValidPhoneFormat($emailOrPhone)) {
            $validatedPhone = $this->phoneOps->getValidatedPhone($emailOrPhone);
            if (!empty($validatedPhone) && $validatedPhone->isValid()) {
                $emailOrPhone = $validatedPhone->getInternationalFormat();
            }
        }

        $emailOrPhone = Utility::codelathify($emailOrPhone);
        $user = $this->dataController->getUserByEmailOrPhone($emailOrPhone);

        // exponential back-off settings
        $freeAttempts = 3;
        $baseTime = 10; // in seconds

        // get the failed attempts history from cache
        $failedLoginAttemptsCacheKey = 'failed_login_attempts__' . base64_encode($emailOrPhone);
        $failedLoginAttemptsItem = $this->cache->getItem($failedLoginAttemptsCacheKey);
        $attempts = $failedLoginAttemptsItem->get() ?? [];
        $currentTimestamp = time();

        // block the login by failed attempts
        if (($attempts['expires_at'] ?? $currentTimestamp) > $currentTimestamp) {
            $seconds = $attempts['expires_at'] - $currentTimestamp;
            if ($seconds >= 60) {
                $ttw = ((int)($seconds / 60)) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT) . ' ' . I18n::choice('time.minute', 2);
            } else {
                $ttw = "$seconds " . I18n::choice('time.second', $seconds);
            }
            $message = I18n::get('messages.login_access_blocked', ['ttw' => $ttw]);
            return JsonOutput::error($message, 401)->write($response);
        }

        // if password is empty, means that password access is denied for this account.
        if (isset($user) && empty($user->getPassword())) {
            return JsonOutput::error(I18n::get('messages.login_password_disabled'), 401)->write($response);
        }

        // check the credentials
        if (
            (!isset($user) || !password_verify($params['password'], $user->getPassword())) ||
            ($user->getId() == $this->config->get('/app/airsend_bot_id')) ||
            ($user->getUserRole() >= User::USER_ROLE_SUB_ADMIN)
        ) {

            // save the failed attempt to the cache
            $expiration = $currentTimestamp + (((int)(2 ** (($attempts['count'] ?? 0) - ($freeAttempts-1)))) * $baseTime);
            $attempts = [
                'count' => ($attempts['count'] ?? 0) + 1,
                'expires_at' => $expiration,
            ];
            $failedLoginAttemptsItem->set($attempts);
            $failedLoginAttemptsItem->expiresAfter(60 * 60); // hard limit the expiration time in 1 hour
            $this->cache->save($failedLoginAttemptsItem);


            // Invalid user or password

            return JsonOutput::error(I18n::get('messages.login_invalid_user'), 401)->write($response);
        }

        // credentials ok, so clean up the number of attempts
        $this->cache->deleteItem($failedLoginAttemptsCacheKey);


        // In Dev mode, lets make active users. In Prod mode, verification is needed
        if ($this->config->get('/app/approval') != 'auto') {

            switch($user->getApprovalStatus())
            {
                case User::APPROVAL_STATUS_PENDING:
                    $this->logger->debug("Login Failed. Account Status Pending for user " . $user->getEmailOrPhone());
                    return JsonOutput::error(I18n::get('messages.login_waiting_for_approval', ['email_address' => $emailOrPhone]), 401)->write($response);
                    break;
            }

            switch($user->getAccountStatus())
            {
                case User::ACCOUNT_STATUS_PENDING_VERIFICATION:
                    $this->logger->debug("Login Failed. Email not verified for user " . $user->getEmailOrPhone() . ". Sending verification code");
                    return JsonOutput::error('messages.login_email_not_verified', 401)
                        ->addMeta('pending_verification', true)
                        ->write($response);
                    break;
                case User::ACCOUNT_STATUS_ACTIVE:
                    break;
                default:
                    $this->logger->debug("Login Failed. Invalid account status for user " . $user->getEmailOrPhone());
                    return JsonOutput::error(I18n::get('messages.login_failed'), 401)->write($response);
            }
            // TODO: Need to check the pending sign up status flow here.



            if (!$user->getIsEmailVerified() && !$user->getIsPhoneVerified()){
                if (!empty($user->getEmail())){
                    $this->logger->debug("Login Failed. Account not verified for user " . $user->getEmailOrPhone());
                    return JsonOutput::error(I18n::get('messages.login_email_not_verified'), 401)->write($response);
                }
                //TODO: When phone support is added, check phone too here.
            }
        }

        return $this->userOps->login($request, $response, $user, $rememberMe, $timezone);

    }

    /**
     * Refresh the token for an already logged user
     *
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidArgumentException
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        // first check if exists a refresh token, and return a 401 if not
        $refreshToken = $request->getAttribute('refresh_token');
        if ($refreshToken === null) {
            return JsonOutput::error('Unauthorized', 401)->write($response);
        }

        // check if the refresh token is valid
        $cacheKey = "refresh.token.$refreshToken";
        $cacheItem = $this->cache->getItem($cacheKey);

        // if the token don't exists on cache, return 401
        if (!$cacheItem->isHit()) {
            return JsonOutput::error('Unauthorized', 401)->write($response);
        }

        // check if the cache entry value is equal to the logged user. If not, return a 401
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');
        if ($auth->getUserId() != $cacheItem->get()) {
            return JsonOutput::error('Unauthorized', 401)->write($response);
        }

        // issue a new token
        $jwtToken = $this->jwt->issueToken(
            $auth->getUserId(),
            $auth->getClientIp(),
            $auth->getUserAgent(),
            false,
            true
        );

        // remove the entry from cache (refresh tokens can be used just one time)
        $this->cache->deleteItem($cacheKey);

        // give the new token to the client
        return JsonOutput::success()->withContent('token', $jwtToken)->write($response);
    }

    /**
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws InvalidArgumentException
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        $this->authOps->logout($request);

        // return empty success
        return JsonOutput::success(202)->write($response);
    }

}