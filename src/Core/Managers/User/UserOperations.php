<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\User;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownIdentityException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\UserCreatedEvent;
use CodeLathe\Core\Messaging\Events\UserDeletedEvent;
use CodeLathe\Core\Messaging\Events\UserFinalizedEvent;
use CodeLathe\Core\Messaging\Events\UserLoginEvent;
use CodeLathe\Core\Messaging\Events\UserOnlineEvent;
use CodeLathe\Core\Messaging\Events\UserProfileUpdateEvent;
use CodeLathe\Core\Messaging\Events\UserUpdatedEvent;
use CodeLathe\Core\Messaging\Events\UserVerifiedEvent;
use CodeLathe\Core\Messaging\Events\VerificationRefreshEvent;
use CodeLathe\Core\Objects\Asset;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Objects\UserCode;
use CodeLathe\Core\Policy\Policies\StorageQuota;
use CodeLathe\Core\Policy\PolicyManager;
use CodeLathe\Core\Policy\PolicyTypes;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\Image;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Auth\JwtServiceInterface;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;


/**
 * Class UserOperations
 * This class is meant to be used for performing user operations by the other classes including endpoint
 * handlers. This class methods must NOT use or depend on HTTP request or response. Everything should be passed in.
 */
class UserOperations
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
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var PolicyManager
     */
    protected $policyManager;

    /**
     * @var NormalizedObjectFactory
     */
    protected $normalizedObjectFactory;

    /**
     * @var JwtServiceInterface
     */
    protected $jwt;

    /**
     * UserOperations constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param MailerServiceInterface $mailer
     * @param ConfigRegistry $config
     * @param PolicyManager $policyManager
     * @param NormalizedObjectFactory $normalizedObjectFactory
     * @param JwtServiceInterface $jwt
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                FileOperations $fOps,
                                EventManager $eventManager,
                                MailerServiceInterface $mailer,
                                ConfigRegistry $config,
                                PolicyManager $policyManager,
                                NormalizedObjectFactory $normalizedObjectFactory,
                                JwtServiceInterface $jwt)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->mailer = $mailer;
        $this->config = $config;
        $this->policyManager = $policyManager;
        $this->normalizedObjectFactory = $normalizedObjectFactory;
        $this->jwt = $jwt;
    }


    /**
     * Create a new user
     *
     * @param string|null $email
     * @param string|null $phone
     * @param string $password
     * @param string $name
     * @param int $accountStatus
     * @param int $userRole
     * @param int $approvalStatus
     * @param bool $isAutoGeneratedPassword
     * @param int|null $invitedByUserId
     * @param bool|null $triggerEvent
     * @param int|null $fromPublicChannelId
     * @param string|null $fromPublicChannelHash
     * @return User
     * @throws DatabaseException
     * @throws FSOpException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     */
    public function createUser(?string $email,
                        ?string $phone,
                        string $password,
                        string $name,
                        int $accountStatus,
                        int $userRole,
                        int $approvalStatus,
                        bool $isAutoGeneratedPassword = false,
                        ?int $invitedByUserId = null,
                        ?bool $triggerEvent = true,
                        ?int $fromPublicChannelId = null,
                        ?string $fromPublicChannelHash = null): User
    {
        if (!empty($email)) {
            if (!empty($userObj = $this->dataController->getUserByEmail($email))) {
                if ($userObj->getAccountStatus() == User::ACCOUNT_STATUS_PENDING_FINALIZE) {
                    $this->finalize($email, $name, $password);
                    return $this->dataController->getUserByEmail($email);
                }
                else
                    throw new UserOpException("Email $email exists. Cannot create a new user");
            }
        }

        if (!empty($phone)) {
            if (!empty($userObj = $this->dataController->getUserByPhone($phone))) {
                if ($userObj->getAccountStatus() == User::ACCOUNT_STATUS_PENDING_FINALIZE){
                    $this->finalize($phone, $name, $password);
                    return $this->dataController->getUserByPhone($phone);
                }
                else
                    throw new UserOpException("Phone $phone exists. Cannot create a new user");
            }
        }

        $passwordHash = "";
        if (!empty($password)) {
            // Store password hash of the supplied password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        // Create user object
        $user = User::create($email, $phone, $passwordHash, $name, $accountStatus,
            $userRole, $approvalStatus, $isAutoGeneratedPassword);
        if (!empty($invitedByUserId))
            $user->setInvitedBy($invitedByUserId);
        if (!$this->dataController->createUser($user)) {
           throw new UserOpException("Failed creating user");
        }

        // Create a team automatically for this user / and associate the user with the Team
        $team = Team::create(Team::SELF_TEAM_NAME . " " . $user->getId(), Team::TEAM_TYPE_SELF, $user->getId());
        $this->dataController->createTeam($team);

        // Create Paths to store files for this user
        if (!$this->fOps->onNewTeam($team, $user)) {
            //TODO: Roll back user and team objects
            throw new FSOpException("Filesystem failure for user $email");
        }

        // Setup all the policies for this user
        $this->setNewUserPolicies($user);

        if ($triggerEvent) {
            $event = new UserCreatedEvent($user, $fromPublicChannelId, $fromPublicChannelHash);
            $this->eventManager->publishEvent($event);
        }

        return $user;
    }

    /**
     * @param string|null $email
     * @param string|null $phone
     * @throws DatabaseException
     * @throws UserOpException
     */
    public function refreshVerifyCode(?string $email, ?string $phone)
    {
        if (!empty($email)) {
            $user = $this->dataController->getUserByEmail($email);
        }

        if (empty($user) && !empty($phone)) {
            $user = $this->dataController->getUserByPhone($phone);
        }

        if (empty($user)) {
            throw new UserOpException("The required user doesn't exists");
        }

        $event = new VerificationRefreshEvent($user);
        $this->eventManager->publishEvent($event);
    }


    /**
     *
     * Create and store thumb images for profile
     * @param User $user
     * @param string $phyFile
     * @param $extension
     * @throws UserOpException
     * @throws SecurityException
     */
    public function generateProfileThumbs(User $user, string $phyFile, $extension)
    {
        // ... Do stuff
        $basename = bin2hex(random_bytes(8));// see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('tmp_small_thumb_%s.%0.8s', $basename, $extension);
        $smallThumb = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;

        if (!Image::resizeImage($phyFile, $extension, $this->config->get('/thumbnail/small'), $this->config->get('/thumbnail/small'), $smallThumb)) {
            throw new UserOpException("Failed resizing image");
        }

        $filename = sprintf('tmp_medium_thumb_%s.%0.8s', $basename, $extension);
        $mediumThumb = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;

        if (!Image::resizeImage($phyFile, $extension, $this->config->get('/thumbnail/medium'), $this->config->get('/thumbnail/medium'), $mediumThumb)) {
            throw new UserOpException("Failed resizing image");
        }

        $smallAsset = Asset::create((int)$user->getId(),Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, Asset::ATTR_SIZE_XSMALL, "image/$extension",
            file_get_contents($smallThumb), (int)$user->getId());

        $mediumAsset = Asset::create((int)$user->getId(),Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, Asset::ATTR_SIZE_MEDIUM, "image/$extension",
            file_get_contents($mediumThumb), (int)$user->getId());

        $fullAsset = Asset::create((int)$user->getId(),Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, Asset::ATTR_SIZE_LARGE, "image/$extension",
            file_get_contents($phyFile), (int)$user->getId());


        // Create the asset if not present. Or update if already present

        if (!empty($asset = $this->dataController->getAsset((int)$user->getId(),Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, Asset::ATTR_SIZE_XSMALL))
            ) {
            $smallAsset->setId((int)$asset->getId());
            $this->dataController->updateAsset($smallAsset);
        }
        else {
            $this->dataController->createAsset($smallAsset);
        }

        if (!empty($asset =$this->dataController->getAsset((int)$user->getId(),Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, Asset::ATTR_SIZE_MEDIUM))
        ) {
            $mediumAsset->setId((int)$asset->getId());
            $this->dataController->updateAsset($mediumAsset);
        }
        else {
            $this->dataController->createAsset($mediumAsset);
        }

        if (!empty($asset =$this->dataController->getAsset((int)$user->getId(),Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, Asset::ATTR_SIZE_LARGE))
        ) {
            $fullAsset->setId((int)$asset->getId());
            $this->dataController->updateAsset($fullAsset);
        }
        else {
            $this->dataController->createAsset($fullAsset);
        }

        if (!$user->getHasAvatar()) {
            $user->setHasAvatar(true);
        }
        $this->dataController->updateUser($user);


        SafeFile::unlink($mediumThumb);
        SafeFile::unlink($smallThumb);
        SafeFile::unlink($phyFile);

        $this->notifyUserUpdatedEventToChannels($user);
    }

    /**
     * @param User $user
     */
    private function notifyUserUpdatedEventToChannels(User $user)
    {
        foreach ($this->dataController->getChannelsForUser((int)$user->getId()) as $channelRec) {
            $channel = Channel::withDBData($channelRec);
            $event = new UserProfileUpdateEvent($user, (int)$channel->getId());
            $this->eventManager->publishEvent($event);
        }

    }

    /**
     *
     * Get profile image data
     *
     * @param int $userIdOfImageToGet
     * @param int $attribute
     * @return mixed
     * @throws UserOpException
     */
    public function getProfileThumbData(int $userIdOfImageToGet, int $attribute = Asset::ATTR_SIZE_XSMALL): ?Asset
    {
        return $this->dataController->getAsset($userIdOfImageToGet,Asset::CONTEXT_TYPE_USER,
            Asset::ASSET_TYPE_PROFILE_IMAGE, $attribute);
    }

    /**
     *
     * Update a user profile
     *
     * @param User $user
     * @param string|null $phone
     * @param string|null $displayName
     * @param string|null $email
     * @param string|null $locale
     * @param int|null $userStatus
     * @param string|null $userStatusMessage
     * @return User
     * @throws DatabaseException
     * @throws UserOpException
     */
    public function setProfile(User $user,
                               ?string $phone=null,
                               ?string $displayName=null,
                               ?string $email=null,
                               ?string $locale = null,
                               ?int $userStatus = null,
                               ?string $userStatusMessage = null): User
    {

        $initialUser = clone $user;

        if (!empty($phone) && $user->getPhone() != $phone) {
            $user->setPhone($phone);
            $user->setIsPhoneVerified(false);
        }

        if (!empty($displayName)) {
            $user->setDisplayName($displayName);
        }

        if (!empty($email) && $user->getEmail() != $email) {
            $user->setEmail($email);
            $user->setIsEmailVerified(false);
        }

        if (!empty($locale) && $user->getLocale() !== $locale) {

            if (!in_array($locale, I18n::supportedLocaleList())) {
                throw new UserOpException(I18n::get('messages.unsupported_locale', ['locale' => $locale]));
            }
            $user->setLocale($locale);
        }

        if ($userStatus !== null && $userStatus !== $user->getStatus()) {
            $user->setStatus($userStatus);
        }

        if ($userStatusMessage !== null && $userStatusMessage !== $user->getStatusMessage()) {
            $user->setStatusMessage($userStatusMessage);
        }

        try {
            $this->dataController->updateUser($user);
        }
        catch(\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            throw new UserOpException (I18n::get('messages.error_updating_user'));
        }

        $this->notifyUserUpdatedEventToChannels($user);

        $event = new UserUpdatedEvent($initialUser, $user);
        $this->eventManager->publishEvent($event);

        return $user;

    }

    /**
     *
     * Setup default policies for user
     *
     * @param User $user
     * @throws UserOpException
     * @throws UnknownPolicyEntityException
     */
    public function setNewUserPolicies(User $user)
    {
        // Every user created also creates a new Team. Need to add some policies for the newly created Teams
        $team = $this->dataController->getDefaultTeamForUser((int)$user->getId());
        if (empty($team)) {
            $this->logger->error("Malformed User - NO TEAM FOUND: " . print_r($user->getArray(),true));
            throw new UserOpException ("Malformed User - NO TEAM FOUND for user " . $user->getId());
        }

        // Setup Quota for the team
        $storageQuota = $this->config->get('/app/team/default_storage_quota_gb');
        if (empty($storageQuota) || $storageQuota == 0) {
            $this->logger->error("No default team storage quota configured!.");
            throw new UserOpException ("No default team storage quota configured " . $user->getId());
        }

        $this->policyManager->setPolicyValue($team, StorageQuota::class, (string)$storageQuota);
    }

    public function roundImage(string $imageData): string
    {
        // instantiate the original image
        $img = imagecreatefromstring($imageData);

        $magicPink = imagecolorallocatealpha($img, 255, 0, 255, 127);
        $w = imagesx($img);
        $h = imagesy($img);

        // create the mask image
        $mask = imagecreatetruecolor($w, $h);
        imagealphablending($mask, true);

        // set masking colors
        $mask_black = imagecolorallocate($mask, 0, 0, 0);
        $mask_magicpink = imagecolorallocate($mask, 255, 0, 255);
        imagecolortransparent($mask, $mask_black);
        imagefill($mask, 0, 0, $mask_magicpink);

        // draw the mask circle
        imagefilledellipse($mask, (int) ($w/2), (int) ($h/2), $w, $h, $mask_black);

        // merge the mask on the original image
        imagecopymerge($img, $mask, 0, 0, 0, 0, $w, $h, 100);
        imagecolortransparent($img, $magicPink);

        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();

        return $imageData;
    }


    public function generateUserEmail(string $emailPrefix = 'user_')
    {
        /**
         * generate one and confirm it is unique. if not , generate another one
         */
        $found = false;
        do {
            $email = $emailPrefix . StringUtility::generateRandomString() . "@airsend.io";
            if (!empty($this->dataController->getUserByEmail($email))){
                $found =  true;
            }
        }while($found);

        return $email;

    }

    public function createVerificationCode(User $user) : UserCode
    {
        if (empty($user)){
            throw new UserOpException("Invalid User to generate verification code");
        }

        // ... delete the user codes if any existing
        $this->dataController->deleteUserCode($user->getId(), UserCode::USER_CODE_TYPE_ACCT_VERIFY);

        // ... generate reset code
        $code = StringUtility::generateRandomString(6,"23456789abcdefghjkmnpqrstuvwxyz");

        // ... set expiration for code
        $expires = date("Y-m-d H:i:s", strtotime('+24 hours'));

        // ... create the user code
        $userCode = UserCode::create($user->getId(), UserCode::USER_CODE_TYPE_ACCT_VERIFY, $code, $expires);
        if (!$this->dataController->createUserCode($userCode)) {
            throw new UserOpException("Cannot create Account Verification Code");
        }

        return $userCode;
    }

    /**
     * @param string|User $user
     * @param string $verificationCode
     * @return bool
     * @throws UserOpException
     * @throws DatabaseException
     */
    public function verify($user, string $verificationCode) : bool
    {

        if (!($user instanceof User)) {
            $user = $this->dataController->getUserByEmailOrPhone($user);
            if (empty($user)) {
                throw new UserOpException("Invalid User");
            }
        }

        if ($user->getAccountStatus() == User::ACCOUNT_STATUS_ACTIVE){
            throw new UserOpException("User is already verified");
        }

        // ... get the user code
        $userCode = $this->dataController->getUserCode($user->getId(), UserCode::USER_CODE_TYPE_ACCT_VERIFY);

        // ... check if reset code passed is correct
        if (empty($userCode) || strtolower($verificationCode) !== strtolower($userCode->getCode())){
            $this->logger->debug("Invalid verification code for user id " . $user->getId());
            throw new UserOpException("Verify account Failed");
        }


        //... check if reset code is expired.
        if (!empty($userCode->getExpires())) {
            if (strtotime($userCode->getExpires()) < strtotime(date('Y-m-d H:i:s'))) {
                throw new UserOpException("Verification Code is no longer valid. Please use forgot password to get new code.");
            }
        }

        // ... update the user account with new password
        $user->setAccountStatus(User::ACCOUNT_STATUS_ACTIVE);
        if (!empty($user->getEmail()) && Utility::isValidEmail($user->getEmail())){
            $user->setIsEmailVerified(true);
        }
        else if (!empty($user->getPhone()) && Utility::isValidPhoneFormat($user->getPhone())){
            $user->setIsPhoneVerified(true);
        }

        if ($user->getIsPhoneVerified() && $user->getIsEmailVerified()){
            $user->setTrustLevel(User::USER_TRUST_LEVEL_FULL);
        }
        else if ($user->getIsEmailVerified() || $user->getIsPhoneVerified()){
            $user->setTrustLevel(User::USER_TRUST_LEVEL_BASIC);
        }
        else {
            $user->setTrustLevel(User::USER_TRUST_LEVEL_UNKNOWN);
        }

        if (!$this->dataController->updateUser($user)){
            throw new UserOpException("Failed updating  password");
        }

        // ... delete the user codes for reset password so the codes are invalid
        $this->dataController->deleteUserCode($user->getId(), UserCode::USER_CODE_TYPE_ACCT_VERIFY);

        $event = new UserVerifiedEvent($user);
        $this->eventManager->publishEvent($event);

        return true;
    }

    public function finalize(string $emailOrPhone, string $displayName, string $password) : bool
    {
        $user = $this->dataController->getUserByEmailOrPhone($emailOrPhone);
        if (empty($user)){
            $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Invalid User");
            throw new UserOpException("Invalid User");
        }

        if ($user->getAccountStatus() != User::ACCOUNT_STATUS_PENDING_FINALIZE){
            $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " User not in pending finalize status");
            throw new UserOpException("Invalid User");
        }

        $passwordHash = "";
        if (!empty($password)) {
            // Store password hash of the supplied password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }


        $user->setDisplayName($displayName);
        $user->setPassword($passwordHash);
        $user->setAccountStatus(User::ACCOUNT_STATUS_ACTIVE);
        $user->setUserRole(User::USER_ROLE_EDITOR);
        $user->setIsAutoGeneratedPassword(false);

        if (Utility::isValidEmail($emailOrPhone)){
            $user->setIsEmailVerified(true);
        }
        else if (Utility::isValidPhoneFormat($emailOrPhone)){
            $user->setIsPhoneVerified(true);
        }

        if ($user->getIsPhoneVerified() && $user->getIsEmailVerified()){
            $user->setTrustLevel(User::USER_TRUST_LEVEL_FULL);
        }
        else if ($user->getIsEmailVerified() || $user->getIsPhoneVerified()){
            $user->setTrustLevel(User::USER_TRUST_LEVEL_BASIC);
        }
        else {
            $user->setTrustLevel(User::USER_TRUST_LEVEL_UNKNOWN);
        }

        if (!$this->dataController->updateUser($user)){
            $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " user update during finalization failed");
            throw new UserOpException("User Finalization Failed");
        }

        $event = new UserFinalizedEvent($user);
        $this->eventManager->publishEvent($event);

        return true;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param User $user
     * @param bool $rememberMe
     * @param string|null $timezone
     * @return ResponseInterface
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response, User $user, bool $rememberMe = true, ?string $timezone = null)
    {

        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        $jwtToken = $this->jwt->issueToken(
            $user->getId(),
            $auth->getClientIp(),
            $auth->getUserAgent(),
            false,
            $rememberMe
        );

        // Raise user logged in event
        $event = new UserLoginEvent($user->getId());
        $this->eventManager->publishEvent($event);

        if ($this->config->get('/app/authcookieenable') == '1') {
            $response = $response->withHeader('Set-Cookie', 'token=' . $jwtToken);
        }

        $this->updatedTimezone($user, $timezone, false);

        $normalizedUser = $this->normalizedObjectFactory->normalizedObject($user, null,false);
        return JsonOutput::success()->withContent('token', $jwtToken)->withContent('user', $normalizedUser)->write($response);

    }

    public function updatedTimezone(User $user, ?string $timezone, bool $force = true): void
    {

        // no timezone passed, ignore
        if ($timezone === null) {
            return;
        }

        // no force, and there is a timezone set for the user, ignore
        if (!$force && $user->getTimezone() !== null) {
            return;
        }

        // update...
        $user->setTimezone($timezone);
        $this->dataController->updateUser($user);

    }

    /**
     * Checks if user1 already have relation with user2
     * (at least one channel in common, or members of the same team)
     * @param int $userId1
     * @param int $userId2
     * @return bool
     */
    public function hasRelation(int $userId1, int $userId2): bool
    {
        return $this->dataController->userHasRelation($userId1, $userId2);
    }

    /**
     * @param User $user
     * @throws DatabaseException
     * @throws UserOpException
     */
    public function deleteUser(User $user)
    {

        $originalUser = clone $user;

        // We will be deleting the cache but not the actual db record
        $this->dataController->deleteUser($user->getId());


        $user->setEmail($this->generateUserEmail("deleted_"));
        $user->setDisplayName("Deleted User");
        $user->setPhone(null);
        $user->setPassword(password_hash(StringUtility::generateRandomString(8), PASSWORD_BCRYPT));
        $user->setIsAutoGeneratedPassword(true);
        $user->setTrustLevel(User::USER_TRUST_LEVEL_UNKNOWN);
        $user->setAccountStatus(User::ACCOUNT_STATUS_DELETED);
        if (!$this->dataController->updateUser($user)){
            throw new UserOpException("User delete failed");
        }

        $event = new UserDeletedEvent($originalUser);
        $this->eventManager->publishEvent($event);
    }
}