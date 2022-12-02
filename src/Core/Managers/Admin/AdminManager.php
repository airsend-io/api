<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Admin;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\PhoneOperations;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\UserApprovedEvent;
use CodeLathe\Core\Messaging\Events\UserDeletedEvent;
use CodeLathe\Core\Messaging\Events\UserUpdatedEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\NotificationAbuseReport;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Serializers\CallbackStream;
use CodeLathe\Core\Utility\App;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Cache\CacheService;
use CodeLathe\Service\Cache\KeySearchInterface;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\ServiceRegistryInterface;
use CodeLathe\Service\Storage\DB\StorageServiceDB;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class AdminManager extends ManagerBase
{

    protected $dataController;

    protected $logger;

    protected $eventManager;

    protected $userOps;

    protected $channelOps;

    protected $objectFactory;

    protected $fileController;

    protected $phoneOps;

    protected $app;

    protected $globalAuthContext;

    protected $chatOps;

    protected $storageDB;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * @var RtmOperations
     */
    protected $rtmOps;

    /**
     * @var CacheService
     */
    protected $cache;

    public function __construct(DataController $dataController,
        LoggerInterface $logger,
        UserOperations $userOps,
        ChannelOperations $channelOps,
        PhoneOperations $phoneOps,
        EventManager $eventManager,
        FileController $fileController,
        GlobalAuthContext $globalAuthContext,
        ChatOperations $chatOperations,
        App $app,
        NormalizedObjectFactory $objectFactory,
        StorageServiceDB $storageDB,
        ServiceRegistryInterface $config,
        RtmOperations $rtmOperations,
        CacheService $cache)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->userOps = $userOps;
        $this->eventManager = $eventManager;
        $this->channelOps = $channelOps;
        $this->objectFactory = $objectFactory;
        $this->fileController = $fileController;
        $this->phoneOps = $phoneOps;
        $this->app = $app;
        $this->globalAuthContext = $globalAuthContext;
        $this->chatOps = $chatOperations;
        $this->storageDB = $storageDB;
        $this->config = $config;
        $this->rtmOps = $rtmOperations;
        $this->cache = $cache;
    }

    /**
     * Derived class must give us the dataController
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }




    public function userApprove(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        if ($this->globalAuthContext->getEffectivePermission( $admin->getId(), GlobalAuthContext::CONTEXT_TYPE_GLOBAL) != GlobalAuthContext::AUTH_ADMIN) {
            return JsonOutput::error("Not Authorized", 401)->write($response);
        }

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['user_id'], $params, $response)) {
            return $response;
        }

        $userId = intval($params['user_id']);

        $user = $this->dataController->getUserById($userId);
        if (empty($user)){
            return JsonOutput::error("Invalid User", 500)->write($response);
        }

        $user->setApprovalStatus(User::APPROVAL_STATUS_APPROVED);
        if (!$this->dataController->updateUser($user)) {
            return JsonOutput::error("Approval Failed", 500)->write($response);
        }

        $event = new UserApprovedEvent($user);
        $this->eventManager->publishEvent($event);

        return JsonOutput::success(200)->write($response);
    }

    private function isValidInput($value){
        return false;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws \CodeLathe\Core\Exception\NotImplementedException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function userSearch(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['approval_status','account_status'], $params, $response)) {
            return $response;
        }

        $id = empty($params['id']) ? null : (int) $params['id'];

        $keyword = empty($params['keyword']) ? null : strval($params['keyword']);

        $accountStatus = -1;
        if (Utility::isValidParams($params, 'account_status')) {
            $accountStatus = intval($params['account_status']);
        }

        $approvalStatus = -1;
        if (Utility::isValidParams($params,'approval_status')) {
            $approvalStatus = intval($params['approval_status']);
        }

        $offset = null;
        if (Utility::isValidParams($params,'offset')) {
            $offset = intval($params['offset']);
        }

        $sortBy = 'created_on';
        if (Utility::isValidParams($params,'sort_by')) {
            $sortBy = $params['sort_by'];
        }

        $sortDirection = 'desc';
        if (Utility::isValidParams($params,'sort_direction')) {
            $sortDirection = $params['sort_direction'];
        }

        $userType = 0;
        if (Utility::isValidParams($params,'user_type')) {
            $userType = intval($params['user_type']);
        }

        $rowCount = null;
        if (Utility::isValidParams($params,'row_count'))
            $rowCount = intval($params['row_count']);

        if ($id !== null) {
            $user = $this->dataController->getUserById($id);
            if ($user !== null) {
                $users = [$this->objectFactory->normalizedUser($user, false, true)];
                return JsonOutput::success(200)->addMeta("total", 1)->withContent('users', $users)->write($response);
            } else {
                return JsonOutput::success()->addMeta("total", 0)->withContent('users', [])->write($response);
            }
        }

        $total = $this->dataController->countUsers($keyword,$accountStatus,$approvalStatus, $userType);
        $rows = $this->dataController->searchUsers($keyword,$accountStatus,$approvalStatus, $offset, $rowCount, $sortBy, $sortDirection, $userType);

        $users = [];
        foreach($rows as $item) {
            $user = User::withDBData($item);
            $normalizedUser = $this->objectFactory->normalizedUser($user, false, true);
            $users[] = $normalizedUser;
        }

        return JsonOutput::success(200)->addMeta("total",$total)->withContent('users', $users)->write($response);

    }

    public function userCreate(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['email', 'phone', 'name','password','user_role','account_status'], $params, $response)) {
            return $response;
        }

        if (!Utility::isValidParams($params, 'email')) {
            return JsonOutput::error("Invalid Email", 400)->write($response);
        }
        if (!Utility::isValidParams($params, 'name')) {
            return JsonOutput::error("Invalid Display Name", 400)->write($response);
        }
        if (!Utility::isValidParams($params, 'password')) {
            return JsonOutput::error("Invalid Password", 400)->write($response);
        }
        if (!Utility::isValidParams($params, 'user_role')) {
            return JsonOutput::error("Invalid User Role", 400)->write($response);
        }
        if (!Utility::isValidParams($params, 'account_status')) {
            return JsonOutput::error("Invalid account status", 400)->write($response);
        }

        $email = strtolower(strval($params['email']));
        $displayName = strval($params['name']);
        $password = strval($params['password']);
        $userRole = intval($params['user_role']);
        $accountStatus = intval($params['account_status']);

        $phone = null;
        if (Utility::isValidParams($params,'phone')) {
            if (!empty($validPhone = $this->phoneOps->getValidatedPhone($phone))) {
                $phone = $validPhone->getInternationalFormat();
            } else {
                return JsonOutput::error("Invalid Phone", 400)->write($response);
            }
        }

        try {
            $user = $this->userOps->createUser($email, $phone, $password, $displayName, $accountStatus, $userRole,
                User::APPROVAL_STATUS_APPROVED, false, null);
        }
        catch(ASException $ex){
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }
        $normalizedUser = ContainerFacade::get(NormalizedObjectFactory::class)->normalizedObject($user, null,false);

        return JsonOutput::success()->withContent('user', $normalizedUser)->write($response);
    }

    public function userUpdate(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['user_id', 'email', 'phone', 'name', 'user_role', 'account_status','trust_level',
            'is_locked', 'approval_status', 'is_email_verified', 'is_phone_verified', 'is_auto_pwd'], $params, $response)) {
            return $response;
        }

        $userId = intval($params['user_id']);

        $user = $this->dataController->getUserById($userId);
        if (empty($user)) {
            return JsonOutput::error("User not found", 500)->write($response);
        }

        $initialUser = clone $user;

        if (Utility::isValidParams($params, 'email')) {
            $email = strtolower(strval($params['email']));
            $user->setEmail($email);
        }

        if (Utility::isValidParams($params, 'phone')) {
            $phone = strval($params['phone']);
            if (!empty($validPhone = $this->phoneOps->getValidatedPhone($phone))) {
                $phone = $validPhone->getInternationalFormat();
            }
            else {
                return JsonOutput::error("Invalid Phone", 400)->write($response);
            }
            $user->setPhone($phone);
        }

        if (Utility::isValidParams($params, 'name')) {
            $displayName = strval($params['name']);
            $user->setDisplayName($displayName);
        }

        if (Utility::isValidParams($params, 'user_role')) {
            $userRole = intval($params['user_role']);
            if ($userRole < User::USER_ROLE_SUB_ADMIN) // ... Security
            {
                $user->setUserRole($userRole);
            }
        }

        if (Utility::isValidParams($params, 'account_status')) {
            $accountStatus = intval($params['account_status']);
            $user->setAccountStatus($accountStatus);
        }

        if (Utility::isValidParams($params, 'trust_level')) {
            $trustLevel = intval($params['trust_level']);
            $user->setTrustLevel($trustLevel);
        }

        if (Utility::isValidParams($params, 'is_locked')) {
            $isLocked = boolval($params['is_locked']);
            $user->setIsLocked($isLocked);
        }

        if (Utility::isValidParams($params, 'approval_status')) {
            $approvalStatus = intval($params['approval_status']);
            $user->setApprovalStatus($approvalStatus);
        }

        if (Utility::isValidParams($params, 'is_email_verified')) {
            $isEmailVerified = boolval($params['is_email_verified']);
            $user->setIsEmailVerified($isEmailVerified);
        }

        if (Utility::isValidParams($params, 'is_phone_verified')) {
            $isPhoneVerified = boolval($params['is_phone_verified']);
            $user->setIsPhoneVerified($isPhoneVerified);
        }

        if (Utility::isValidParams($params, 'is_auto_pwd')) {
            $isAutoPwd = boolval($params['is_auto_pwd']);
            $user->setIsAutoGeneratedPassword($isAutoPwd);
        }


        $user->setUpdatedOn(date('Y-m-d H:i:s'));
        $user->setUpdatedBy($admin->getId());
        if (!$this->dataController->updateUser($user)){
            return JsonOutput::error("Unable to update user", 500)->write($response);
        }
        $normalizedUser = ContainerFacade::get(NormalizedObjectFactory::class)->normalizedObject($user, null,false);

        $event = new UserUpdatedEvent($initialUser, $user);
        $this->eventManager->publishEvent($event);

        return JsonOutput::success()->withContent('user', $normalizedUser)->write($response);

    }

    public function userDelete(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['user_id'], $params, $response)) {
            return $response;
        }

        $userId = intval($params['user_id']);

        $user = $this->dataController->getUserById($userId);
        if (empty($user)) {
            return JsonOutput::error("User not found", 500)->write($response);
        }

        try {
            $this->userOps->deleteUser($user);
        } catch (UserOpException $e) {
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }

        return JsonOutput::success(200)->write($response);
    }

    public function userInfo(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['user_id'], $params, $response)) {
            return $response;
        }

        $userId = intval($params['user_id']);

        $user = $this->dataController->getUserById($userId);
        if (empty($user)) {
            return JsonOutput::error("Invalid user", 500)->write($response);
        }

        $userEx = $this->objectFactory->normalizedObject($user, null, false);

        // Return the channel object
        return JsonOutput::success()->withContent('user', $userEx)->write($response);

    }

    public function channelSearch(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        $id = !empty($params['id']) ? (int)$params['id'] : null;

        $keyword = $params['keyword'] ?? null;

        $channelStatus = null;
        if (Utility::isValidParams($params, 'channel_status')) {
            $channelStatus = intval($params['channel_status']);
        }

        $offset = null;
        if (Utility::isValidParams($params, 'offset')) {
            $offset = intval($params['offset']);
        }

        $rowCount = null;
        if (Utility::isValidParams($params, 'row_count')) {
            $rowCount = intval($params['row_count']);
        }

        $sortBy = 'created_on';
        if (Utility::isValidParams($params,'sort_by')) {
            $sortBy = $params['sort_by'];
        }

        $sortDirection = 'desc';
        if (Utility::isValidParams($params,'sort_direction')) {
            $sortDirection = $params['sort_direction'];
        }

        if ($id !== null) {
            $channel = $this->dataController->getChannelById($id);
            if ($channel !== null) {
                $channels = [$this->objectFactory->normalizedObject($channel, null, false)];
                return JsonOutput::success(200)->addMeta("total", 1)->withContent('channel', $channels)->write($response);
            } else {
                return JsonOutput::success()->addMeta("total", 0)->withContent('users', [])->write($response);
            }
        }

        $total = $this->dataController->countChannels($keyword, $channelStatus);
        $channels = [];
        foreach($this->dataController->searchChannels($keyword, $channelStatus, $offset, $rowCount, false, $sortBy, $sortDirection) as $record) {
            $channels[] = $this->objectFactory->normalizedObject(Channel::withDBData($record), null, false);
        }

        return JsonOutput::success()->addMeta("total", $total)->withContent('channel', $channels)->write($response);
    }

    public function channelCreate(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['channel_name', 'user_id'], $params, $response)) {
            return $response;
        }

        if (!Utility::isValidParams($params, 'channel_name')) {
            return JsonOutput::error("Invalid Channel Name", 500)->write($response);
        }

        if (!Utility::isValidParams($params, 'user_id')) {
            return JsonOutput::error("Invalid user id", 500)->write($response);
        }

        $autoClosed = false;
        if (Utility::isValidParams($params, 'auto_close')) {
            $autoClosed = boolval($params['auto_close']);
        }

        $closeAfterDays = 0;
        if (Utility::isValidParams($params, 'close_after_days')) {
            $closeAfterDays = intval($params['close_after_days']);
        }

        $invitees = [];
        if (Utility::isValidParams($params, 'invitees')) {
            $invitees = explode(',', (string)$params['invitees']);;
        }

        $channelName = strval($params['channel_name']);
        $userId = intval($params['user_id']);



        $user = $this->dataController->getUserById($userId);
        if (empty($user)){
            return JsonOutput::error("Invalid user", 500)->write($response);
        }

        foreach ($invitees as $invitee) {
            if (Utility::isValidPhoneFormat($invitee) && empty($this->phoneOps->getValidatedPhone($invitee))) {
                return JsonOutput::error("Invalid Phone for invitee", 400)->write($response);
            }
        }

        try {
            $channel = $this->channelOps->createChannel($user, $channelName, $admin->getId());
            if ($autoClosed) {
                $channel->setIsAutoClosed($autoClosed);
                $channel->setCloseAfterDays($closeAfterDays);
                if (!$this->dataController->updateChannel($channel)) {
                    return JsonOutput::error("Failed to set channel properties", 500)->write($response);
                }
            }
            foreach ($invitees as $invitee) {
                try {
                    $this->channelOps->addUserToChannel((int)$channel->getId(), $invitee, $admin->getId());
                } catch (ChannelOpException $e) {
                    // just log and skip in case of error
                    $this->logger->warning($e->getMessage());
                    continue;
                }
            }
        }
        catch(ASException $e){
            return JsonOutput::error("Error creating channel " . $e->getMessage(), 500)->write($response);
        }

        // Augment data
        $channelEx = $this->objectFactory->normalizedObject($channel, null, false);

        // Return the channel object
        return JsonOutput::success()->withContent('channel', $channelEx)->write($response);

    }

    public function channelUpdate(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!Utility::isValidParams($params, 'channel_id')) {
            return JsonOutput::error("Invalid Channel", 500)->write($response);
        }

        $channelId = intval($params['channel_id']);
        $oldChannel = $this->dataController->getChannelById($channelId);
        if (empty($oldChannel)){
            return JsonOutput::error("Invalid Channel", 500)->write($response);
        }

        $newChannel = clone $oldChannel;

        if (Utility::isValidParams($params, 'team_id')) {
            $newChannel->setTeamId(intval($params['team_id']));
        }

        if (Utility::isValidParams($params, 'channel_name')) {
            $newChannel->setName(strval($params['channel_name']));
        }

        if (Utility::isValidParams($params, 'channel_email')) {
            $newChannel->setEmail(strval($params['channel_email']));
        }

        if (Utility::isValidParams($params, 'auto_close')) {
            $newChannel->setIsAutoClosed(boolval($params['auto_close']));
        }

        if (Utility::isValidParams($params, 'close_after_days')) {
            $newChannel->setCloseAfterDays(intval($params['close_after_days']));
        }

        if (Utility::isValidParams($params, 'channel_status')) {
            $newChannel->setChannelStatus(intval($params['channel_status']));
        }

        $newChannel->setUpdatedOn(date('Y-m-d H:i:s'));
        $newChannel->setUpdatedBy($admin->getId());

        try{
            $this->channelOps->updateChannel($admin, $oldChannel, $newChannel);
        }
        catch(ASException $e) {
            return JsonOutput::error("Error updating channel " . $e->getMessage(), 500)->write($response);
        }

        // Augment data
        $channelEx = $this->objectFactory->normalizedObject($newChannel, null, false);

        // Return the channel object
        return JsonOutput::success()->withContent('channel', $channelEx)->write($response);
    }


    public function channelInfo(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) { return $response;}

        $channelId = $params['channel_id'];

        $channel = $this->dataController->getChannelById((int)$channelId);
        if (empty($channel)) {
            return JsonOutput::error("Invalid Channel", 500)->write($response);
        }

        $channelEx = $this->objectFactory->normalizedObject($channel, null, false);

        // Return the channel object
        return JsonOutput::success()->withContent('channel', $channelEx)->write($response);
    }

    public function channelUserList(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) { return $response;}

        $channelId = intval($params['channel_id']);

        $records = [];
        foreach($this->dataController->getUsersForChannel($channelId) as $record) {
            $records[] = $record;
        }

        return JsonOutput::success()->withContent('channelusers', $records)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidEmailAddressException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     * @throws ValidationErrorException
     */
    public function channelUserAdd(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id','email','user'], $params, $response)) { return $response;}

        $channelId = intval($params['channel_id']);
        $role = intval($params['channel_role']);

        $invitee = null;
        if (Utility::isValidParams($params,'email')) {
            $invitee = strval($params['email']);
        } elseif (!Utility::isValidParams($params,'user')) {
            $invitee = strval($params['user']);
        }

        try {
            $this->channelOps->addUserToChannel($channelId, $invitee, $admin->getId(), $role);
        } catch (ChannelOpException $e) {
            return JsonOutput::error($e->getMessage(), 400)->write($response);
        }

        return JsonOutput::success(200)->write($response);
    }

    public function channelUserUpdate(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id','user_id'], $params, $response)) { return $response;}

        $channelId = intval($params['channel_id']);
        $userId = intval($params['user_id']);
        $role = intval($params['channel_role']);

        $channelUser = $this->dataController->getUserChannel($channelId, $userId);
        if (empty($channelUser)){
                return JsonOutput::error("User not in channel", 500)->write($response);
        }

        $newChannelUser = clone $channelUser;
        $newChannelUser->setUserRole($role);

        try {
            $this->channelOps->updateChannelUser($admin, $channelUser, $newChannelUser);
        }
        catch(ASException $e) {
            return JsonOutput::error("Error updating user in channel " . $e->getMessage(), 500)->write($response);
        }

        return JsonOutput::success(200)->write($response);
    }

    public function channelUserDelete(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['user_id','user','channel_id'], $params, $response)) { return $response;}

        $userId = intval($params['user_id']);
        $emailOrPhone = strval($params['user']);
        $channelId = intval($params['channel_id']);

        $user = $this->dataController->getUserByEmailOrPhone($emailOrPhone);
        if (empty($user)){
            return JsonOutput::error("Invalid User", 500)->write($response);
        }

        if ($user->getId() !== $userId) {
            return JsonOutput::error("Invalid User", 500)->write($response);
        }

        $channel = $this->dataController->getChannelById($channelId);
        if (empty($channel)){
            return JsonOutput::error("Invalid Channel", 500)->write($response);
        }

        try {
            $this->channelOps->removeUserFromChannel($user, $admin, $channel);
        }
        catch(ASException $e) {
            return JsonOutput::error("Error removing user from channel " . $e->getMessage(), 500)->write($response);
        }

        return JsonOutput::success(200)->write($response);
    }

    public function channelDelete(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (!RequestValidator::validateRequest(['channel_id'], $params, $response)) { return $response;}

        if (!Utility::isValidParams($params,'channel_id')) {
            return JsonOutput::error("Invalid Channel", 500)->write($response);
        }

        $channelId = intval($params['channel_id']);
        $channel = $this->dataController->getChannelById($channelId);
        if (empty($channel)){
            return JsonOutput::error("Invalid Channel", 500)->write($response);
        }

        try {
            $this->channelOps->removeChannel($admin, $channel);
        }
        catch(ASException $e) {
            return JsonOutput::error("Error removing channel " . $e->getMessage(), 500)->write($response);
        }
        return JsonOutput::success(200)->write($response);
    }

    public function dashboardStats(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $stats = $this->dataController->getDashboardStats();

        // ... Add additional Stats
        $props = $this->fileController->folderProps("/f/");

        $interval = 60 * 60 * 24; // last 24 hours
        $filesInfo = $this->storageDB->getDashBoardStats($interval);


        $stats['filescount'] = $props->getTotalFiles();
        $stats['filescount_24h'] = $filesInfo['new_count'] . ' (' . Convert::toSizeUnit((int) $filesInfo['new_size']) . ')';
        $stats['filessize'] = $props->getTotalSize();

        $total = $this->dataController->countUsers(null,-1,0, 0);
        $stats['pendingapprovals'] = $total;

        $stats['version'] = $this->app->version();

        return JsonOutput::success()->withContent('stats', $stats)->write($response);
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
    public function notificationAbuseReport(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) {
            return $response;
        };

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['start', 'limit'], $params, $response)) {
            return $response;
        }

        $start = $params['start'] ?? 0;
        $limit = $params['limit'] ?? 30;

        $reports = [];
        /** @var NotificationAbuseReport $report */
        foreach ($this->dataController->getNotificationAbuseReports($start, $limit) as $report) {
            $reports[] = [
                'id' => $report->getId(),
                'reporter_name' => $report->getReporterName(),
                'reporter_email' => $report->getReporterEmail(),
                'reported_channel_id' => $report->getNotification()->getChannelId(),
                'reported_channel_name' => $report->getNotification()->getChannel()->getName(),
                'report_text' => $report->getReportText()
            ];
        }
        return JsonOutput::success()->addMeta('total', count($reports))->withContent('reports', $reports)->write($response);
    }

    public function notificationAbuseReportDelete(Request $request, Response $response, array $args): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) {
            return $response;
        }

        $this->dataController->deleteNotificationAbuseReport($args['id']);

        return JsonOutput::success()->withContent('message', 'Abuse report removed successfuly')->write($response);
    }

    protected function availableDbCommands(): array
    {
        return [
            'processlist' => [
                'text' => 'Process List',
                'query' => "SELECT * FROM information_schema.processlist pl WHERE pl.id <> connection_id() AND pl.state <> '';"
            ],
            'sizes' => [
                'text' => 'Table and indexes sizes',
                'query' => <<<sql
                    SELECT
                      table_schema AS `db`,
                      table_name AS `table`,
                      ROUND((data_length) / 1024 / 1024) AS `data_size_mb`,
                      ROUND((index_length) / 1024 / 1024) AS `index_size_mb`,
                      ROUND((data_length + index_length) / 1024 / 1024) AS `total_size_mb`,
                      table_rows as `rows_count`
                    FROM information_schema.tables
                    WHERE table_schema in ('asclouddb', 'asstoragedb')
                    ORDER BY
                      ROUND((data_length + index_length) / 1024 / 1024) DESC, 
                      table_rows DESC;
sql

            ],
            'engines' => [
                'text' => 'List the instaled engines',
                'query' => "SELECT * FROM information_schema.ENGINES;",
            ],
            'variables' => [
                'text' => 'List instance variables',
                'query' => 'SHOW variables',
            ],
            'explain' => [
                'text' => 'Show the access plan for the query (explain)',
                'pre_query' => 'use :database;', // command to be run before the one that generate the result set
                'query' => "explain :query;", // the query that generates the result set
                'params' => [
                    [
                        'name' => 'database',
                        'title' => 'Database',
                        'type' => 'text',
                        'bind' => 'inject',
                        'pre' => true, // parameter used on the pre-query
                    ],
                    [
                        'name' => 'query',
                        'title' => 'Query to explain',
                        'type' => 'multiline', // text, multiline, select
                        'bind' => 'inject', // parameter or inject
                    ],
                ]
            ]

        ];
    }

    public function getDbCommands(Request $request, Response $response, array $args): Response
    {
        $commands = [];
        foreach ($this->availableDbCommands() as $key => $item) {
            $params = array_map(function($item) {
                return array_intersect_key($item, array_flip(['name', 'type', 'title']));
            }, $item['params'] ?? []);
            $commands[] = [
                'value' => $key,
                'text' => $item['text'],
                'params' => $params,
            ];
        }
        return JsonOutput::success()->withContent('commands', $commands)->write($response);
    }

    public function runDbCommand(Request $request, Response $response, array $args): Response
    {
        $commands = $this->availableDbCommands();
        $command = $commands[$args['command']] ?? null;
        if ($command === null) {
            return JsonOutput::error('Invalid Command', 400)->write($response);
        }

        $params = $request->getParsedBody();

        $rootConn = $this->config->get('/db/cloud_db_root/conn');
        $rootUser = $this->config->get('/db/cloud_db_root/user');
        $rootPassword = $this->config->get('/db/cloud_db_root/password');
        $dbh = new \PDO($rootConn, $rootUser, $rootPassword);

        $preQuery = $command['pre_query'] ?? null;
        $query = $command['query'];

        // if the command requires parameters
        if (!empty($command['params'])) {

            // validate the parameters
            $failed = array_filter($command['params'], function ($item) use ($params) {
                return !in_array($item['name'], array_keys($params ?? []));
            });
            if (count($failed)) {
                return JsonOutput::error('Invalid Params', 400)->write($response);
            }

            // bind parameters by injection
            $injectionParams = array_filter($command['params'], function ($item) {
                return $item['bind'] === 'inject';
            });
            foreach ($injectionParams as $paramConfig) {
                // prevent SQL injection by removing the command terminator
                $paramValue = str_replace(';', '', $params[$paramConfig['name']]);

                if ($preQuery && ($paramConfig['pre'] ?? false)) {
                    $preQuery = str_replace(":{$paramConfig['name']}", $paramValue, $preQuery);
                } else {
                    $query = str_replace(":{$paramConfig['name']}", $paramValue, $query);
                }
            }
        }

        // execute pre-query
        if ($preQuery) {
            $dbh->exec($preQuery);
        }

        // prepare main query
        $stmt = $dbh->prepare($query);

        // bind parameters on prepared statement
        if (!empty($command['params'])) {
            $bindParams = array_filter($command['params'], function ($item) {
                return $item['bind'] === 'bind';
            });

            foreach ($bindParams as $paramConfig) {
                $stmt->bindValue(":{$paramConfig['name']}", $params[$paramConfig['name']]);
            }
        }


        $stmt->execute();
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return JsonOutput::success()->withContent('result', $result)->write($response);

    }

    public function userConnections(Request $request, Response $response, array $args): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['user_id'], $params, $response)) {
            return $response;
        }

        $userId = intval($params['user_id']);

        return JsonOutput::success()->withContent('connections', $this->rtmOps->getRtmTokens($userId))->write($response);

    }

    public function userChannels(Request $request, Response $response, array $args): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        if (!RequestValidator::validateRequest(['user_id'], $params, $response)) {
            return $response;
        }

        $userId = intval($params['user_id']);

        $channels = [];
        foreach ($this->dataController->getChannelsForUser($userId) as $row) {
            $channel = Channel::withDBData($row);
            $channels[] = $this->objectFactory->normalizedObject($channel, null, false);
        }

        return JsonOutput::success()->withContent('channels', $channels)->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function cacheKeys(Request $request, Response $response, array $args): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        $pattern = trim($params['pattern'] ?? '');

        $pattern = "*$pattern*";
        $pattern = preg_replace('/\*{2,}/', '*', $pattern);

        return JsonOutput::success()->withContent('keys', $this->cache->keys($pattern))->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getCacheKey(Request $request, Response $response, array $args): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        $key = $params['key'] ?? null;
        if (empty($key)) {
            return JsonOutput::error('A key is required', '425')->write($response);
        }

        $value = strpos($key, ':') !== false ? $this->cache->getHash($key) : $this->cache->get($key);

        return JsonOutput::success()->withContent('value', $value)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function clearCacheKey(Request $request, Response $response, array $args): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        $key =$params['key'] ?? null;
        if (empty($key)) {
            return JsonOutput::error('A key is required', '425')->write($response);
        }

        $this->cache->forget($key);

        return JsonOutput::success(204)->write($response);
    }

    public function channelExport(Request $request, Response $response, array $args): Response
    {

        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; }

        $params = $request->getParsedBody();

        // channel id must be provided
        $channelId =$params['channel_id'] ?? null;
        if (empty($channelId)) {
            return JsonOutput::error('A channel id is required', '425')->write($response);
        }

        $channel = $this->dataController->getChannelById($channelId);
        if (empty($channel)) {
            return JsonOutput::error('Channel not found', '404')->write($response);
        }

        // we're admin, so we act as the owner of the channel
        $user = $this->dataController->getUserById($channel->getOwnedBy());

        $exportMode = "";
        // Export and return
        // ... Send Callback that will be run by the response object
        $output = new CallbackStream(
            function () use ($user, $channel, $exportMode) {
                $this->channelOps->sendZipCallback($user, $channel, $exportMode);
                return '';
            }
        );

        return $response->withBody($output);

    }
}