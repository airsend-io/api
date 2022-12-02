<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\User;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Auth\AuthOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\PhoneOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\NotifyAlert;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\Asset;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Objects\NotificationAbuseReport;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\NumVerify;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;
use Psr\Http\Message\ResponseInterface as Response;

class UserManager extends ManagerBase
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
     * @var FileOperations
     */
    protected $fOps;

    /**
     * @var UserOperations
     */
    protected $userOps;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var NumVerify
     */
    protected $phoneOps;

    protected $eventManager;

    /**
     * @var AuthOperations
     */
    protected $authOps;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * UserManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param UserOperations $userOps
     * @param ConfigRegistry $config
     * @param PhoneOperations $phoneOps
     * @param EventManager $eventManager
     * @param AuthOperations $authOperations
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                FileOperations $fOps,
                                UserOperations $userOps,
                                ConfigRegistry $config,
                                PhoneOperations $phoneOps,
                                EventManager $eventManager,
                                AuthOperations $authOperations,
                                CacheItemPoolInterface $cache)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->userOps = $userOps;
        $this->phoneOps = $phoneOps;
        // To prevent circular dependency
        $this->objectFactory = ContainerFacade::get(NormalizedObjectFactory::class);
        $this->eventManager = $eventManager;
        $this->config = $config;
        $this->authOps = $authOperations;
        $this->cache = $cache;
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
     * End point to create an user account
     *
     * Auth Requirement: No Auth is required ?
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function create(Request $request, Response $response): Response
    {
        $createDisabled = (int)$this->config->get('/app/disable_account_creation');
        if ($createDisabled == 1) {
            return JsonOutput::error("Disabled", 403)->write($response);
        }

        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        // Validate request. Only email is required to create an user
        if (!RequestValidator::validateRequest(['email', 'phone', 'password', 'name'], $params, $response)) { return $response;}

        if (empty($params['email']) && empty($params['phone'])) {
            return JsonOutput::error("Email or Phone is Required", 422)->write($response);
        }

        $validatedPhone = null;
        if (!empty($params['phone']))
        {
            $phoneObj = $this->phoneOps->getValidatedPhone($params['phone']);
            if (!empty($phoneObj) && $phoneObj->isValid()){
                $validatedPhone =  $phoneObj->getInternationalFormat();
            }
            else {
                return JsonOutput::error("Invalid Mobile Phone Number", 500)->write($response);
            }
        }

        $email = empty($params['email']) ? null : strtolower($params['email']);
        $phone = empty($params['phone']) ? null : $validatedPhone;
        $password = $params['password'];
        $fromPublicChannelId = !empty($params['from_public_channel_id']) ? ((int)$params['from_public_channel_id']) : null;
        $fromPublicChannelHash = !empty($params['from_public_channel_hash']) ? $params['from_public_channel_hash'] : null;

        // validate the email domain...
        if ($email !== null) {

            // find the domain...
            if (!preg_match('/[^@]+@([^@]+)/', $email, $matches)) {
                return JsonOutput::error("Invalid Email", 422)->write($response);
            }
            $emailDomain = trim($matches[1]);

            // DNS lookup to check if the email domain exists as an email domain ...
//            $dnsRecords = dns_get_record($emailDomain);
////            $mxRecords = array_filter($dnsRecords, function ($item) {
////                return strtoupper($item['type']) === 'MX';
////            });
////            if (empty($mxRecords)) {
////                return JsonOutput::error("The Email could not be validated", 422)->write($response);
////            }

            // check against the email domain blacklist ...
            $blacklistPath = Directories::resources('email/domains_blacklist.txt');
            $fh = fopen($blacklistPath, 'r'); // read the file, line by line and check TODO - Replace this linear search with a binary search, or put it on a database table
            while(! feof($fh))  {
                if ($emailDomain == trim(fgets($fh))) {
                    return JsonOutput::error("This Email domain is disallowed", 422)->write($response);
                }
            }
            fclose($fh);

        }

        $name = empty($email) ? $phone : $email;
        if (!empty($params['name'])) {
            $name = htmlspecialchars_decode($params['name']);

            // check for profanity on display name
            if (StringUtility::containsProfanity($name, $violatingWords))
            {
                $this->logger->error(__FUNCTION__ . " Name has profanity: ". $name);
                $violatingWords = implode(', ', $violatingWords);
                return JsonOutput::error("This name violates our non-profanity policy. Violating words: $violatingWords", 422)->write($response);
            }

            // disallow special chars on display name
            $invalidChars = ['$', '%', '&', ';', ':', ',', '/', '\\', '='];
            if (str_replace($invalidChars, '', $name) !== $name) {
                return JsonOutput::error('Display name cannot have special chars.', 422)->write($response);
            }

        }

        $accountStatus = User::ACCOUNT_STATUS_PENDING_VERIFICATION;
        $approvalStatus = User::APPROVAL_STATUS_PENDING;

        if ($this->config->get('/app/approval') == 'auto') {
            $accountStatus = User::ACCOUNT_STATUS_ACTIVE;
            $approvalStatus = User::APPROVAL_STATUS_APPROVED;
        } elseif ($this->config->get('/app/approval') == 'verify') {
            $approvalStatus = User::APPROVAL_STATUS_APPROVED;
        }

        try {
            $user = $this->userOps->createUser($email, $phone, $password, $name,
                $accountStatus,
                User::USER_ROLE_EDITOR, $approvalStatus, false, null, true, $fromPublicChannelId, $fromPublicChannelHash);
        } catch (UserOpException $e) {
            return JsonOutput::error($e->getMessage(), 422)->write($response);
        } catch (ASException $ex) {
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            // Return internal server error
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }


        // ... 1 - Account is ready to login
        // ... 2 - Needs email verification
        // ... 3 - Approval and then email verification
        $force_login = false;
        $code = 3;
        $createMessage = "Thanks for signing up to try AirSend. Look for the verification email in your inbox shortly and click the link in that email to complete your sign up.";
        if ($this->config->get('/app/approval') == 'auto') {
            // If auto is enabled, then by pass email verification as well.
            $user->setIsEmailVerified(true);
            $this->dataController->updateUser($user);
            $force_login = true;

        }

        if ($user->getAccountStatus() == User::ACCOUNT_STATUS_ACTIVE &&
            $user->getApprovalStatus() == User::APPROVAL_STATUS_APPROVED &&
            ($user->getIsEmailVerified() || $user->getIsPhoneVerified())) {
                $code = 1;
                $createMessage = "Thanks for signing up to try AirSend. Your account is ready and you can start using AirSend.";
        }

        // Augment data
        $userEx = $this->objectFactory->normalizedObject($user, null,false);

        // Return the user object
        return JsonOutput::success()->addMeta("code",$code)->addMeta("message",$createMessage)->addMeta("force_login", $force_login)->withContent('user', $userEx)->write($response);
    }

    /**
     * Get information about an user
     *
     * Auth Requirement: Requires a valid login.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function info(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        // The request has to be query parameter
        $params = $request->getQueryParams();
        
        $modParams = [];
        if (!empty($params['email'])) {
            $modParams['opt_email'] = strtolower($params['email']);
        }
        if (!empty($params['user_id'])) {
            $modParams['opt_user_id'] = $params['user_id'];
        }
        if (!empty($params['phone'])) {
            $modParams['opt_phone'] = $params['phone'];
        }

        // Validate request
        if (!RequestValidator::validateRequest(['opt_email', 'opt_user_id', 'opt_phone'], $params, $response)) { return $response;}


        if (!empty($modParams['opt_email'])) {
            $email = $modParams['opt_email'];
            $email = Utility::codelathify($email);
            $reqUser = $this->dataController->getUserByEmail($email);
            if (empty($reqUser)) {
                return JsonOutput::error("Failed request", 400)->write($response);
            }
        }
        else if (!empty($modParams['opt_phone'])) {
            $phone = $modParams['opt_phone'];

            $formattedPhone = $this->phoneOps->getValidatedPhone($phone);
            if (empty($formattedPhone)) {
                return JsonOutput::error("Failed request", 400)->write($response);
            }
            $reqUser = $this->dataController->getUserByPhone($formattedPhone->getInternationalFormat());
            if (empty($reqUser)) {
                return JsonOutput::error("Failed request", 400)->write($response);
            }
        }
        else if (!empty($modParams['opt_user_id'])) {
            $userId = (int)$modParams['opt_user_id'];

            $reqUser = $this->dataController->getUserById($userId);
            if (empty($reqUser)) {
                return JsonOutput::error("Failed request", 400)->write($response);
            }
        } else {

            // no parameter send, so return the logged user
            $reqUser = $this->dataController->getUserById($user->getId());

        }

        // If this user is the creator of this channel, we will give more, else abbreviated
        $abbreviated = (int)$user->getId() != (int)$reqUser->getId();

        $userEx = $this->objectFactory->normalizedObject($reqUser, null, $abbreviated);

        // Return the channel object
        return JsonOutput::success()->withContent('user', $userEx)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function profileSet(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };


        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['phone', 'name', 'email'], $params, $response)) { return $response;}

        $phone = null;
        if (!empty($params['phone'])) {
            $phoneObj = $this->phoneOps->getValidatedPhone($params['phone']);
            if (empty($phoneObj) || !$phoneObj->isValid()) {
                return JsonOutput::error("Invalid Mobile Phone", 400)->write($response);
            }
            $phone = $phoneObj->getInternationalFormat();
        }

        $name = null;
        if (isset($params['name'])) {
            $name = htmlspecialchars_decode($params['name']);
            if (StringUtility::containsProfanity($name, $violatingWords))
            {
                $this->logger->error(__FUNCTION__ . " Name has profanity: ". $name);
                $violatingWords = implode(', ', $violatingWords);
                return JsonOutput::error("This name violates our non-profanity policy. Violating words: $violatingWords", 400)->write($response);
            }
        }

        $email = null;
        if (isset($params['email'])) {
            $email = strtolower($params['email']);
            $email = Utility::codelathify($email);

        }

        $userStatus = isset($params['status']) ? ((int)$params['status']) : null;
        if ($userStatus !== null && !in_array($userStatus, array_keys(User::STATUS_MAP))) {
            return JsonOutput::error("Invalid status code", 422)->write($response);
        }

        $userStatusMessage = $params['status_message'] ?? null;

        try {
            $user = $this->userOps->setProfile($user, $phone, $name, $email, null, $userStatus, $userStatusMessage);
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            // Return internal server error
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        $userEx = $this->objectFactory->normalizedObject($user, null, false);

        // Return the channel object
        return JsonOutput::success()->withContent('user', $userEx)->write($response);
    }

    /**
     *
     * Set a profile Photo
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function imageSet(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {return $response;};

        // handle single input with single file upload
        $uploadedFiles = $request->getUploadedFiles();
        if (count($uploadedFiles) <= 0) {
            $this->logger->error( __FUNCTION__ . " WebServer File Upload Failure, no files found : ");
            return JsonOutput::error("Upload Failure", 400)->write($response);
        }

        if (!isset($uploadedFiles['file'])) {
            $this->logger->error( __FUNCTION__ ." WebServer File Upload Failure, bad file input param name, use 'file' : " );
            return JsonOutput::error("Upload Failure", 400)->write($response);
        }

        $uploadedFile = $uploadedFiles['file'];
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
            $basename = bin2hex(random_bytes(8));
            $filename = sprintf('tmp_upload_%s.%0.8s', $basename, $extension);
            $phyFile = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
            $this->logger->info("Profile image for " . $user->getEmail() . " is at " . $phyFile);
            $uploadedFile->moveTo($phyFile);

            try {
                $this->userOps->generateProfileThumbs($user, $phyFile, $extension);
            }
            catch (ASException $ex) {
                $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
                // Return internal server error
                return JsonOutput::error($ex->getMessage(), 500)->write($response);
            }

            return JsonOutput::success()->write($response);
        }

        $this->logger->error(__FUNCTION__ . " No file uploaded");
        return JsonOutput::error("Error", 400)->write($response);
    }

    /**
     * Action to get the avatar when authenticated
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws SecurityException
     * @throws DatabaseException
     */
    public function imageGet(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }


        if (!RequestValidator::validateRequest(['image_class', 'user_id'], $params, $response)) { return $response;}

        $roundImage = $params['round'] ?? false;
        $returnFallbackImage = $params['return_fallback_image'] ?? false;

        $attr = Asset::ATTR_SIZE_XSMALL;
        if ($params['image_class'] == 'small') {
            $attr = Asset::ATTR_SIZE_XSMALL;
        }
        else if ($params['image_class'] == 'medium') {
            $attr = Asset::ATTR_SIZE_MEDIUM;
        }
        else if ($params['image_class'] == 'full') {
            $attr = Asset::ATTR_SIZE_LARGE;
        }
        else {
            return JsonOutput::error('image_class can be only small, medium or full', 400)->write($response);
        }

        $target = $params['user_id'];

        try {
            $asset = $this->userOps->getProfileThumbData((int)$target, $attr);
            if (empty($asset)) {
                $genericAvatarFile = Directories::images('genericavatar.png');

                if (!$returnFallbackImage || !SafeFile::file_exists($genericAvatarFile)) {
                    // Return 404
                    return JsonOutput::error("No generic avatar available", 404)->write($response);
                }
                $imageData = SafeFile::file_get_contents($genericAvatarFile);
                $imageMime = 'image/png';
            }
        }
        catch (UserOpException $ex) {
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
        catch (ASException $ex) {
            //$this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            // Return internal server error
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        if (isset($asset)) {
            $imageData = $asset->getData();
            $imageMime = $asset->getMime();
        }

        if ($roundImage) {
            $imageData = $this->userOps->roundImage($imageData);
            $imageMime = 'image/png';
        }

        // Need to send the $asset->getData() and $asset->getMime to caller
        $response->getBody()->write($imageData);
        return $response->withHeader('Content-Type', $imageMime);

    }

    /**
     * Get alerts for a user
     *
     * Auth Requirement: Requires a valid login.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws DatabaseException
     */
    public function getAlerts(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $alerts = $this->dataController->getAlerts((int)$user->getId());

        $alertEx = [];
        foreach ($alerts as $alertRec) {
            //$this->logger->info(print_r($alertRec,true));
            $alert = Alert::withDBData($alertRec);
            $alertEx[] = $this->objectFactory->normalizeAlertToObject($alert);
        }

        // Return the channel object
        return JsonOutput::success()->withContent('alerts', $alertEx)->write($response);
    }


    /**
     * Get alerts for a user
     *
     * Auth Requirement: Requires a valid login.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function ackAlert(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['alert_id'], $params, $response)) { return $response;}

        $alertId = $params['alert_id'];
        $alert = $this->dataController->getAlertById((int)$alertId);
        if (empty($alert)) {
            return JsonOutput::error('Invalid id', 404)->write($response);
        }

        $alert->setIsRead(true);

        try {
            $this->dataController->upsertAlert($alert);

            $event = new NotifyAlert($alert);
            $this->eventManager->publishEvent($event);

        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__." Exception: ".$ex->getMessage());
            // Return internal server error
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws DatabaseException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function manageNotifications(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['notification_option'], $params, $response)) {
            return $response;
        }

        $configValue = User::NOTIFICATIONS_CONFIG_MAP[$params['notification_option']];

        $user->setNotificationsConfig($configValue);
        $this->dataController->updateUser($user);

        return JsonOutput::success()->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function verify(Request $request, Response $response): Response
    {
        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        // Validate request. Only email is required to create an user
        if (!RequestValidator::validateRequest(['verify_code','user'], $params, $response)) { return $response;}

        $emailOrPhone = strtolower($params['user']);

        $code = strval($params['verify_code']);

        $user = $this->dataController->getUserByEmailOrPhone($emailOrPhone);
        if (empty($user)) {
            return JsonOutput::error('User not found', 404)->write($response);
        }

        try {
            if (!$this->userOps->verify($user, $code)) {
                return JsonOutput::error("Invalid Code", 400)->write($response);
            }
        }
        catch(ASException $e){
            $this->logger->info(__FUNCTION__." : ".$e->getMessage());
            return JsonOutput::error($e->getMessage(), 400)->write($response);
        }

        return $this->userOps->login($request, $response, $user);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function reportAbuse(Request $request, Response $response): Response
    {

        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        // Validate request...
        if (!RequestValidator::validateRequest(['reporter_name', 'reporter_email', 'report_text'], $params, $response)) {
            return $response;
        }

        // Get the notification
        /** @var Notification $notification */
        $notification = $request->getAttribute('notification');

        $notificationAbuseReport = NotificationAbuseReport::create($notification->getId(), $params['reporter_name'], $params['reporter_email'], $params['report_text']);

        $this->dataController->createNotificationAbuseReport($notificationAbuseReport);

        return JsonOutput::success()->write($response);
    }

    public function finalize(Request $request, Response $response): Response
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        // Validate request. Only email is required to create an user
        if (!RequestValidator::validateRequest(['user','name', 'password'], $params, $response)) { return $response;}

        $emailOrPhone = strtolower($params['user']);

        $displayName = strval($params['name']);
        $password = strval($params['password']);

        try {
            if (!$this->userOps->finalize($emailOrPhone, $displayName, $password)) {
                return JsonOutput::error("Failed Request", 400)->write($response);
            }
        }
        catch (UserOpException $ex){
            return JsonOutput::error($ex->getMessage(), 400)->write($response);
        }
        catch(ASException $e){
            $this->logger->error(__FUNCTION__." Error: ".$e->getMessage());
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }

        $user = $this->dataController->getUserByEmailOrPhone($emailOrPhone);

        // Augment data
        $userEx = $this->objectFactory->normalizedObject($user, null,false);

        // Return the user object
        return JsonOutput::success()->withContent('user', $userEx)->write($response);
    }

    /**
     * Sends a new verify code for the user
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function verifyRefresh(Request $request, Response $response): Response
    {

        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        // Validate request. Only email is required to create an user
        if (!RequestValidator::validateRequest(['email', 'phone'], $params, $response)) {
            return $response;
        }

        if (empty($params['email']) && empty($params['phone'])) {
            return JsonOutput::error("Email or Phone is Required", 422)->write($response);
        }

        $email = $params['email'] ?? null;
        $phone = $params['phone'] ?? null;

        try {
            $this->userOps->refreshVerifyCode($email, $phone);
        } catch (UserOpException $e) {
            return JsonOutput::error($e->getMessage(), 404)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    public function delete(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        $providedEmail = $params['email'] ?? null;
        if (empty($providedEmail)) {
            return JsonOutput::error('Confirmation email not provided', 422)->write($response);
        }

        if ($user->getEmail() !== trim($providedEmail)) {
            return JsonOutput::error('Invalid email', 422)->write($response);
        }

        $feedback = $params['feedback'] ?? '';
        $cacheKey = "selfdelete.feedbacks";
        $cacheItem = $this->cache->getItem($cacheKey);
        $feedbacks = $cacheItem->isHit() ? json_decode($cacheItem->get()) : [];
        $feedbacks[] = [
            'user' => $user->getEmail(),
            'feedback' => $feedback,
        ];
        $cacheItem->set(json_encode($feedbacks));
        $cacheItem->expiresAfter(60 * 60 * 24); // just keep it on cache for 24 hours (daily task must get it during this time)
        $this->cache->save($cacheItem);


        $this->userOps->deleteUser($user);

        $this->authOps->logout($request);

        // return empty response
        return JsonOutput::success(204)->write($response);
    }

}