<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\OAuth;

use AppleSignIn\ASDecoder;
use AppleSignIn\ASPayload;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\UserLoginEvent;
use CodeLathe\Core\Objects\ExternalIdentity;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Hybridauth\Exception\Exception;
use Hybridauth\Provider\LinkedIn;
use League\OAuth2\Client\Provider\Apple;
use League\OAuth2\Client\Provider\AppleResourceOwner;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class OAuthManager  extends ManagerBase
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OAuthOperations
     */
    protected $oauthOps;

    /**
     * @var ConfigRegistry
     */
    private $configRegistry;

    /**
     * @var JwtServiceInterface
     */
    private $jwt;

    /**
     * @var EventManager
     */
    private $event;

    /**
     * OAuthManager constructor.
     *
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param JwtServiceInterface $jwt
     * @param ConfigRegistry $configRegistry
     * @param OAuthOperations $oauthOps
     * @param EventManager $eventManager
     */
    public function __construct(DataController $dataController,
        LoggerInterface $logger,
        JwtServiceInterface $jwt,
        ConfigRegistry $configRegistry,
        OAuthOperations $oauthOps,
        EventManager $eventManager)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->oauthOps = $oauthOps;
        $this->jwt = $jwt;
        $this->configRegistry = $configRegistry;
        $this->event = $eventManager;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    /**
     * Set the hybrid auth config parameters for oauth
     *
     * @return array
     */
    private function getConfig(Request $request, int $provider)
    {

        $config = [];
        switch ($provider) {
            case ExternalIdentity::IDENTITY_PROVIDER_GOOGLE:
                $config['callback'] = rtrim($this->configRegistry->get('/app/server/baseurl'), '/') . "/oauth.google";
                $config['keys']['id'] = $this->configRegistry->get('/google/keys/id');
                $config['keys']['secret'] = $this->configRegistry->get('/google/keys/secret');
                $config['scope'] = "https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email";
                $config['authorize_url_parameters']['approval_prompt'] = 'force';
                break;
            case ExternalIdentity::IDENTITY_PROVIDER_LINKEDIN:
                $config['callback'] = rtrim($this->configRegistry->get('/app/server/baseurl'), '/') . "/oauth.linkedin";
                $config['keys']['id'] = $this->configRegistry->get('/linkedin/keys/id');
                $config['keys']['secret'] = $this->configRegistry->get('/linkedin/keys/secret');
                $config['authorize_url_parameters']['approval_prompt'] = 'force';
                break;
            case ExternalIdentity::IDENTITY_PROVIDER_APPLE:
                $config['callback'] = rtrim($this->configRegistry->get('/app/server/baseurl'), '/') . "/oauth.apple";
                $config['keys']['id'] = $this->configRegistry->get('/apple/keys/id');
                $config['keys']['team_id'] = $this->configRegistry->get('/apple/keys/team_id');
                $config['keys']['key_id'] = $this->configRegistry->get('/apple/keys/key_file_id');
                $config['keys']['key_file'] = $this->configRegistry->get('/apple/keys/key_file_path');
                $config['scope'] = 'name email';
                break;
        }
        return $config;

    }


    /**
     * Linked In Login
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     */
    public function linkedInLogin(Request $request, Response $response): Response
    {

        // This step of OAuth login is done on the frontend. This method should take care of the code exchange only.
        // needs re-writing in the case that we decide to activate linked-in auth.

        try
        {
            $config = $this->getConfig($request, ExternalIdentity::IDENTITY_PROVIDER_LINKEDIN);
            $adapter = new LinkedIn($config);
            if ($adapter->isConnected() && !$adapter->hasAccessTokenExpired()) {
                $token = $adapter->getAccessToken();
                $adapter->setAccessToken($token);
            }
            else {
                $adapter->authenticate();
            }

            $profile = $adapter->getUserProfile();
            /** create and/or login to user account */
            $user = $this->oauthOps->login($adapter->getUserProfile(), ExternalIdentity::IDENTITY_PROVIDER_LINKEDIN);

            /** @var Auth $auth */
            $auth = $request->getAttribute('auth');
            $jwtToken = $this->jwt->issueToken(
                $user->getId(),
                $auth->getClientIp(),
                $auth->getUserAgent(),
                false
            );
            if ($this->configRegistry->get('/app/authcookieenable') == '1') {
                $response = $response->withHeader('Set-Cookie', 'token=' . $jwtToken);
            }

            $event = new UserLoginEvent($user->getId());
            $this->event->publishEvent($event);

            $url =   $request->getUri()->getScheme() . "://" .  $request->getUri()->getHost() .
                (($request->getUri()->getPort() == null) ? "" : ":" . $request->getUri()->getPort());
            return $response->withHeader('Location', "$url/?token=" . $jwtToken . "&email=" . $user->getEmail());
        }
        catch(ASException $asEx){
            $this->logger->error(__FUNCTION__." Exception: ".$asEx->getMessage());
            return JsonOutput::error($asEx->getMessage(), 500)->write($response);
        }
        catch(Exception $ex){
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        try {

            $config = $this->getConfig($request, ExternalIdentity::IDENTITY_PROVIDER_GOOGLE);

            $adapter = new \Hybridauth\Provider\Google($config);
            if ($adapter->isConnected() && !$adapter->hasAccessTokenExpired()) {
                $adapter->disconnect();
            }

            return JsonOutput::success()->write($response);
        }
        catch(Exception $ex){
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
    }


    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ASException
     * @throws InvalidFailedHttpCodeException
     * @throws \CodeLathe\Core\Exception\DatabaseException
     * @throws \CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException
     * @throws \CodeLathe\Core\Exception\UserOpException
     */
    public function googleLogin(Request $request, Response $response): Response
    {
        try {
            $params = $request->getParsedBody();

            if (!Utility::isValidParams($params, 'id_token')) {
                return JsonOutput::error("Id token is Required", 400)->write($response);
            }

            if (!Utility::isValidParams($params, 'client_id')) {
                return JsonOutput::error("Client Id is Required", 400)->write($response);
            }

            // get the id token and client id
            $id_token = $params['id_token'];
            $client_id = $params['client_id'];

            // check if the client id is in list of allowed client ids
            $allowedClientIds =  array_map('trim', explode(',', $this->configRegistry->get('/google/keys/id')));
            if (!in_array($client_id, $allowedClientIds)){
                return JsonOutput::error("Invalid Client Id", 404)->write($response);
            }

            // verify token
            $client = new \Google_Client(['client_id' => $client_id]);
            $payload = $client->verifyIdToken($id_token);

            if ($payload) {
                $this->logger->debug(__FUNCTION__ . " Google User info payload : " . print_r($payload,true));
                $externalIdentifier = $payload['sub'];
                $email = $payload['email'];
                $emailVerified = (bool)$payload['email_verified'];
                if (isset($payload['name'])) {
                    $displayName = $payload['name'];
                }
                else {
                    $displayName = $email;
                }

                if (!$emailVerified) {

                    return JsonOutput::error("Email is not verified. Please verify your email with 
                                                    google before using google sign in.", 401)->write($response);
                }

                // login to airsend. create account if not present.
                $accountCreated = false;
                try {
                    $user = $this->oauthOps->loginDirect($externalIdentifier, $email,$displayName, $emailVerified, ExternalIdentity::IDENTITY_PROVIDER_GOOGLE, $accountCreated);
                }
                catch (ASException $ex) {
                    $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
                    return JsonOutput::error($ex->getMessage(), 401)->write($response);
                }

                if (empty($user)){
                    return JsonOutput::error("Unable to retrieve user", 404)->write($response);
                }
                // create jwt auth token
                /** @var Auth $auth */
                $auth = $request->getAttribute('auth');
                $jwtToken = $this->jwt->issueToken(
                    $user->getId(),
                    $auth->getClientIp(),
                    $auth->getUserAgent(),
                    false,
                    true
                );
                if ($this->configRegistry->get('/app/authcookieenable') == '1') {
                    $response = $response->withHeader('Set-Cookie', 'token=' . $jwtToken);
                }

                // publish events
                $event = new UserLoginEvent($user->getId());
                $this->event->publishEvent($event);

                // normalize and return user
                $normalizedUser = ContainerFacade::get(NormalizedObjectFactory::class)->normalizedObject($user, null,false);
                return JsonOutput::success()
                    ->withContent('token', $jwtToken)
                    ->withContent('account_created', $accountCreated)
                    ->withContent('user', $normalizedUser)
                    ->write($response);
                //return JsonOutput::success()->addMeta("token",$jwtToken)->withContent('user', $normalizedUser)->write($response);
            } else {
                return JsonOutput::error("Invalid Google Id Token", 401)->write($response);
            }
        }
        catch(Exception $ex){
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
    }

    public function appleLogin(Request $request, Response $response): Response
    {

        /** @var Apple $appleProvider */
        $appleProvider = ContainerFacade::get(Apple::class);

        $params = $request->getParsedBody();

        $code = $params['code'] ?? null;
        $token = $params['token'] ?? null;
        if (empty($code) && empty($token)) {
            return JsonOutput::error("Code or identity token is required", 422)->write($response);
        }

        $clientId = $params['client_id'] ?? null;
        if (empty($clientId)) {
            return JsonOutput::error("Client Id is Required", 422)->write($response);
        }
        $clientId = trim($clientId);

        $redirectUri = $params['redirect_uri'] ?? null;

        // check if the client id is in list of allowed client ids
        $allowedClientIds =  array_map('trim', explode(',', $this->configRegistry->get('/apple/keys/id')));
        if (!in_array($clientId, $allowedClientIds)){
            $this->logger->warning("Invalid Apple Client ID: Provided: '$clientId' | Alowwed: " . print_r($allowedClientIds, true));
            return JsonOutput::error("Invalid Client Id", 404)->write($response);
        }

        // try to validate through authorization code, if provided, using our private key
        if ($code) {
            try {
                $payload = ['code' => $code];
                if (!empty($redirectUri)) {
                    $payload['redirectUri'] = $redirectUri;
                }
                $token = $appleProvider->getAccessToken('authorization_code', $payload);
            } catch (IdentityProviderException $e) {
                return JsonOutput::error("Invalid Apple Id Token", 401)->write($response);
            }

            // We have an access token.., let's get some details...
            /** @var AppleResourceOwner $resourceOwner */
            $resourceOwner = $appleProvider->getResourceOwner($token);

            $externalIdentifier = $token->getResourceOwnerId();
            $email = $resourceOwner->getEmail();
            $displayName = $resourceOwner->getFirstName() . ' ' . $resourceOwner->getLastName();

        } else { // we don't have an authorization code, so try to validade the identity token, using Apple public key

            try {
                // This method validates the token signature/expiration against Apple public key
                /** @var ASPayload $tokenPayload */
                $tokenPayload = ASDecoder::getAppleSignInPayload($token);
            } catch (\Throwable $e) {
                return JsonOutput::error("Invalid Apple Id Token", 401)->write($response);
            }

            // token is valid! First validates issuer and audience
            // issuer must be apple and audience must be the provided client_id
            if ($tokenPayload->iss !== 'https://appleid.apple.com' || $tokenPayload->aud !== $clientId) {
                return JsonOutput::error("Invalid Apple Id Token", 401)->write($response);
            }

            // grab the info
            $externalIdentifier = $tokenPayload->getUser();
            $email = $tokenPayload->getEmail();

        }


        if (empty($email)) {
            $this->logger->warning('No email returned from Apple');
            return JsonOutput::error("No email returned from Apple", 401)->write($response);
        }

        if (empty(trim($displayName ?? ''))) {
            $displayName = $email;
        }

        // login to airsend. create account if not present.
        $accountCreated = false;
        try {
            $user = $this->oauthOps->loginDirect($externalIdentifier, $email, $displayName, true, ExternalIdentity::IDENTITY_PROVIDER_APPLE, $accountCreated);
        } catch (\Throwable $e) {
            // We're not handling this error properly right now, because of Apple's "hide my email" feature, that can mess up with auth when the user
            // unauthorizes Airsend inside Apple account, and then authorizes it again.
            return JsonOutput::error("Error while logging with your Apple account. Please contact support", 400)->write($response);
        }

        // create jwt auth token
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');
        $jwtToken = $this->jwt->issueToken(
            $user->getId(),
            $auth->getClientIp(),
            $auth->getUserAgent(),
            false,
            true
        );
        if ($this->configRegistry->get('/app/authcookieenable') == '1') {
            $response = $response->withHeader('Set-Cookie', 'token=' . $jwtToken);
        }

        // publish events
        $event = new UserLoginEvent($user->getId());
        $this->event->publishEvent($event);

        // normalize and return user
        $normalizedUser = ContainerFacade::get(NormalizedObjectFactory::class)->normalizedObject($user, null,false);
        return JsonOutput::success()
            ->withContent('token', $jwtToken)
            ->withContent('account_created', $accountCreated)
            ->withContent('user', $normalizedUser)->write($response);
    }

}