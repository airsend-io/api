<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Channel;


use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelAuthorizationException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Managers\Action\ActionOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChannelCreatedEvent;
use CodeLathe\Core\Messaging\Events\ChannelJoinUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChannelRemovedEvent;
use CodeLathe\Core\Messaging\Events\ChannelUpdateEvent;
use CodeLathe\Core\Messaging\Events\TeamAddChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamRemoveChannelEvent;
use CodeLathe\Core\Messaging\Events\TeamUpdateChannelEvent;
use CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent;
use CodeLathe\Core\Messaging\Events\UserCreatedEvent;
use CodeLathe\Core\Messaging\Events\UserRemovedFromChannelEvent;
use CodeLathe\Core\Messaging\Events\UserUpdatedInChannelEvent;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\Asset;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\FolderProps;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Objects\OAuth\Client;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use CodeLathe\Service\Storage\Exceptions\DestinyPathAlreadyExistsException;
use CodeLathe\Service\Storage\Exceptions\InvalidPathException;
use CodeLathe\Service\Storage\Exceptions\NotAFolderException;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream;

class ChannelOperations
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
     * @var UserOperations
     */
    protected $userOps;

    /**
     * @var
     */
    protected $objectFactory;

    protected $chatOps;

    /**
     * @var
     */
    protected $globalAuthContext;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var ActionOperations
     */
    protected $actionOps;
    /**
     * @var SlugifyInterface
     */
    protected $slugfier;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var StorageServiceInterface
     */
    protected $storageService;

    /**
     * ChannelOperations constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param UserOperations $userOps
     * @param NormalizedObjectFactory $objectFactory
     * @param ChatOperations $chatOps
     * @param GlobalAuthContext $globalAuthContext
     * @param ConfigRegistry $config
     * @param MailerServiceInterface $mailerService
     * @param ActionOperations $actionOps
     * @param SlugifyInterface $slugifier
     * @param StorageServiceInterface $storageService
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(
        DataController $dataController,
        LoggerInterface $logger,
        FileOperations $fOps,
        EventManager $eventManager,
        UserOperations $userOps,
        NormalizedObjectFactory $objectFactory,
        ChatOperations $chatOps,
        GlobalAuthContext $globalAuthContext,
        ConfigRegistry $config,
        MailerServiceInterface $mailerService,
        ActionOperations $actionOps,
        SlugifyInterface $slugifier,
        StorageServiceInterface $storageService,
        CacheItemPoolInterface $cache
    ) {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->userOps = $userOps;
        $this->objectFactory = $objectFactory;
        $this->chatOps = $chatOps;
        $this->globalAuthContext = $globalAuthContext;
        $this->config = $config;
        $this->mailer = $mailerService;
        $this->actionOps = $actionOps;
        $this->slugfier = $slugifier;
        $this->cache = $cache;
        $this->storageService = $storageService;
    }


    /**
     * Generate a random unique email
     *
     * @param string $channelName
     * @return string
     * @throws DatabaseException
     */
    protected function generateChannelEmail(string $channelName) : string
    {
        /**
         * generate one and confirm it is unique. if not, prefix and increment
         */

        $suffix = 0;
        do {
            $email = $this->slugfier->slugify($channelName) . ((string)($suffix ?: ''));
            if (empty($this->dataController->getChannelByEmail($email))) {
                break;
            }
            $suffix++;
        } while (true);

        return $email;
    }

    /**
     *
     * Create a new channel
     *
     * @param User $user
     * @param string $channelName is the name of the channel
     * @param string|null $blurb
     * @param null|int $createdBy
     * @param int|null $autoCloseDays
     * @param int|null $copyChannelId
     * @param bool|null $allowJoin
     * @param bool|null $requireJoinApproval
     * @param bool|null $allowExternalRead
     * @param int|null $contactFormId
     * @param int|null $contactFormFillerId
     * @param bool $oneOnOne
     * @param Team|null $team
     * @param bool $openTeamJoin
     * @return Channel
     * @throws ChannelMalformedException
     * @throws ChannelOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     * @throws TeamOpException
     * @throws UnknownResourceException
     */
    public function createChannel(
        User $user,
        string $channelName,
        ?string $blurb = '',
        ?int $createdBy = null,
        ?int $autoCloseDays = -1,
        ?int $copyChannelId = -1,
        ?bool $allowJoin = false,
        ?bool $requireJoinApproval = true,
        ?bool $allowExternalRead = false,
        ?int $contactFormId = null,
        ?int $contactFormFillerId = null,
        bool $oneOnOne = false,
        ?Team $team = null,
        bool $openTeamJoin = false
    ) : Channel {
        $userId = (int)$user->getId();

        if ($this->doesChannelNameExistInTeam($user, $channelName)) {
            $this->logger->info(__FUNCTION__ . " $channelName  is already in use for this user!");
            throw new ChannelOpException(" $channelName  is already in use for this user!");
        }

        // if no team is provided, use the default self team for the user
        if ($team === null) {
            $team = $this->dataController->getDefaultTeamForUser((int)$userId);
            if (empty($team)) {
                throw new TeamOpException("Team not found for the user $userId");
            }
        }


        $cEmail = $this->generateChannelEmail($channelName);

        $createdById = empty($createdBy) ? $userId : $createdBy;

        // Create channel object
        $channel = Channel::create(
            (int)$team->getId(),
            $channelName,
            $cEmail,
            Channel::CHANNEL_STATUS_OPEN,
            $createdById,
            $blurb,
            $requireJoinApproval,
            $allowExternalRead,
            $contactFormId,
            $contactFormFillerId,
            $openTeamJoin
        );
        if ($autoCloseDays > 0) {
            $channel->setIsAutoClosed(true);
            $channel->setCloseAfterDays($autoCloseDays);
        }

        $channel->setOneOnOne($oneOnOne);

        if (!$this->dataController->createChannel($channel)) {
            throw new ChannelOpException("Failed creating channel");
        }

        // create the public_hash and short url for public channel
        if ($allowJoin) {
            $publicHash = $this->dataController->createPublicHash('ChannelInvite', $channel->getId());
            $this->dataController->createShortUrl($this->createChannelInviteUrl($channel->getId(), $publicHash), null, 'ChannelInvite', $channel->getId());
        }

        // Add this user to the channel
        $channelUser = ChannelUser::create(
            (int)$channel->getId(),
            $userId,
            ChannelUser::CHANNEL_USER_ROLE_ADMIN,
            $createdById
        );
        if (!$this->dataController->addChannelUser($channelUser)) {
            throw new FSOpException("Failed adding user " . $userId . "to channel " . $channel->getId());
        }


        // Create channel path
        $paths = array();
        if (!$this->fOps->onNewChannel($channel, $userId, $paths)) {
            // TODO: Roll back newly created channel?
            throw new FSOpException("Failed creating channel paths!");
        }


        // Copy channel settings
        if ($copyChannelId > 0) {
            if (!empty($copyChannel = $this->dataController->getChannelById($copyChannelId))) {
                // Check if this user has access to this channel
                $authContext = $this->globalAuthContext->getEffectivePermission(
                    $createdById,
                    GlobalAuthContext::CONTEXT_TYPE_CHANNEL,
                    $copyChannelId
                );

                if ($authContext >= GlobalAuthContext::AUTH_OWNER) {
                    $this->copyChannelResources($user, $copyChannel, $channel);
                } else {
                    // Non fatal error. Just log
                    $this->logger->error(
                        __FUNCTION__ . "$createdById has owner access to $copyChannelId. Cannot copy settings to " . $channel->getName(
                        )
                    );
                }
            } else {
                // Non fatal error. Just log
                $this->logger->error(
                    __FUNCTION__ . " $copyChannelId does not exist. Cannot copy settings to " . $channel->getName()
                );
            }
        }


        // Publish this event for foreground and background users
        $event = new ChannelCreatedEvent($channel);
        $this->eventManager->publishEvent($event);

        // notify team members about the new channel (when is a standard team)
        if (!$team->isSelfTeam()) {
            $event = new TeamAddChannelEvent($team, $channel);
            $this->eventManager->publishEvent($event);
        }

        return $channel;
    }

    /**
     *
     * Copy files, action, other settings from source to target channel
     *
     * @param User $user
     * @param Channel $source
     * @param Channel $target
     * @throws ActionOpException
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws NotImplementedException
     * @throws UnknownUserException
     * @throws UnknownResourceException
     * @throws InvalidArgumentException
     */
    private function copyChannelResources(User $user, Channel $source, Channel $target)
    {
        // Copy files
        $this->logger->info("Copying files from " . $source->getId() . " to " . $target->getId());

        $sourcePaths = [];
        $targetPaths = [];
        $dummyStats = new FolderProps();

        $this->fOps->onCopyChannel($source, $target, $user);

        // Copy assets
        try {
            $bg = $this->getAsset($source, "background");

            $targetBg = Asset::create(
                $target->getId(),
                $bg->getContextType(),
                $bg->getAssetType(),
                $bg->getAttribute(),
                $bg->getMime(),
                $bg->getData(),
                $user->getId()
            );

            $this->dataController->createAsset($targetBg);
            $target->setHasBackground(true);
        } catch (ChannelOpException $ex) {
            // Ignore
        }

        try {
            $icon = $this->getAsset($source, "logo");

            $targetIcon = Asset::create(
                $target->getId(),
                $icon->getContextType(),
                $icon->getAssetType(),
                $icon->getAttribute(),
                $icon->getMime(),
                $icon->getData(),
                $user->getId()
            );

            $this->dataController->createAsset($targetIcon);
            $target->setHasLogo(true);
        } catch (ChannelOpException $ex) {
            $this->logger->info("No custom logo is set for source channel");
            // Ignore
        }

        $this->dataController->updateChannel($target);

        $records = $this->dataController->getActions((int)$source->getId(), null, null, true);

        foreach ($records as $record) {
            $action = $this->dataController->getActionWithUsers((int)$record['id']);
            $newAction = Action::create(
                $target->getId(),
                $action->getName(),
                $action->getDesc(),
                $action->getActionType(),
                $action->getActionStatus(),
                $action->getDueOn(),
                $user->getId(),
                $action->getOrderPosition(),
                $action->getParentId()
            );
            $this->dataController->createAction($newAction);
        }
    }

    /**
     *
     * Add an user to a channel. If the user doesnt exist, the user will be created with
     * viewer role and then will be added to the channel.
     * UserCreatedEvent will be raised if the user is created
     * UserAddedToChannelEvent will be raised once the user is added to channel
     *
     * @param int $channelId
     * @param mixed $inviteeCode - Can be an id, email or phone
     * @param int $inviterId
     * @param int $role
     * @param bool $oneOneMode
     * @return User
     * @throws ChannelOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidEmailAddressException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     */
    public function addUserToChannel(
        int $channelId,
        $inviteeCode,
        int $inviterId,
        int $role = ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI,
        bool $oneOneMode = false
    ): User
    {

        $channelUserCount = $this->dataController->getChannelUserCount($channelId);
        if ($channelUserCount >= 2000) {
            throw new ChannelOpException("User limit exceeded for channel " . $channelId);
        }

        // check if the user can be added to the channel (one on one channels)
        $channel = $this->dataController->getChannelById($channelId);
        if (!$oneOneMode && $channel->getOneOnOne()) {
            throw new FSOpException("Can't add a user to an 1x1 channel");
        }

        // try to find the user to add
        $userToAdd = null;

        // check the blacklist
        if ($this->dataController->isUserBlacklistedOnChannel($inviteeCode, $channelId)) {
            throw new ChannelOpException("This email is blacklisted for the channel " . $channelId);
        }

        if (preg_match('/^[0-9]+$/', ''.$inviteeCode)) { // $inviteeCode is an id

            $userToAdd = $this->dataController->getUserById((int)$inviteeCode);

            // if the inviteeCode is an integer (means id) and the user doesn't exists, throw exception
            // is up to the caller to just use this option if the user exists
            if (empty($userToAdd)) {
                throw new ChannelOpException("User with id {$inviteeCode} not found");
            }

        } elseif (Utility::isValidEmail($inviteeCode)) { // inviteeCode is an email

            $userToAdd = $this->dataController->getUserByEmail($inviteeCode);


        } elseif (Utility::isValidPhoneFormat($inviteeCode)) { // invitee code is a phone
            $userToAdd = $this->dataController->getUserByPhone($inviteeCode);
        }

        if (empty($userToAdd)) {

            $inviteeCode = trim($inviteeCode);

            // User with viewer role will be created
            if (preg_match('/^([^@]+)@/', ''.$inviteeCode, $matches)) {
                $userDisplayName = $matches[1];
                $emailToAdd = $inviteeCode;
                $phoneToAdd = null;
            } else {
                $userDisplayName = $inviteeCode;
                $emailToAdd = null;
                $phoneToAdd = $inviteeCode;
            }
            $autoPassword = StringUtility::generateRandomString(12);
            $userToAdd = $this->userOps->createUser(
                $emailToAdd,
                $phoneToAdd,
                $autoPassword,
                $userDisplayName,
                User::ACCOUNT_STATUS_PENDING_FINALIZE,
                User::USER_ROLE_VIEWER,
                User::APPROVAL_STATUS_APPROVED,
                true,
                $inviterId
            );

            if (empty($userToAdd)) {
                throw new ChannelOpException("Unable to add user $userToAdd");
            }

            // Publish this user created event
            $event = new UserCreatedEvent($userToAdd);
            $this->eventManager->publishEvent($event);
        } else {

            // Check if the user is already part of the channel
            if (!empty($this->dataController->getUserChannel($channelId, $userToAdd->getId()))) {
                // This user is already a part of this channel. Make it a no-op
                $this->logger->info(__FUNCTION__ . " : " . $userToAdd->getId() . " is already in channel $channelId");
                throw new ChannelOpException('User already in channel');
            }
        }

        // Add this user to the channel
        $channelUser = ChannelUser::create(
            $channelId,
            $userToAdd->getId(),
            $role,
            $inviterId
        );
        if (!$this->dataController->addChannelUser($channelUser)) {
            throw new FSOpException("Failed adding user " . $emailToAdd . "to channel " . $channelId);
        }

        $event = new UserAddedToChannelEvent($channelId, $channelUser);
        $this->eventManager->publishEvent($event);

        // if the channel is part of a team, trigger the team.update_channel event
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if (!$team->isSelfTeam()) {
            $event = new TeamUpdateChannelEvent($team, $channel);
            $this->eventManager->publishEvent($event);
        }

        // Dont send email for one one channels
        if (!$channel->getOneOnOne()) {
            $channel = $this->dataController->getChannelById($channelId);
            $this->sendChannelAddToUserEmail($userToAdd->getId(), $inviterId, $channel);
        }

        return $userToAdd;
    }


    /**
     * Get channel messages
     *
     * @param int $channelId
     * @param int $cursor
     * @param int $limit
     * @param string|null $before
     * @param bool $asc
     * @return array
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    public function getChannelHistory(int $channelId, int $cursor, int $limit, ?string $before = null , bool $asc = false) : array
    {
        $messages = array();
        foreach ($this->dataController->getMessagesByChannelId($channelId, $limit, $cursor, $before, $asc) as $messageRec) {
            $messages[] = $this->objectFactory->normalizedObject(Message::withDBData($messageRec));
        }

        return $messages;
    }


    /**
     *
     * Rename a channel
     *
     * @param User $user
     * @param Channel $channel
     * @param string $newName
     * @throws ChannelOpException
     * @throws ChannelAuthorizationException
     * @throws DatabaseException
     */
    public function rename(User $user, Channel $channel, string $newName) : void
    {
        if (!$this->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_MANAGE)) {
            $this->logger->error(
                __FUNCTION__ . " User " . $user->getDisplayName(
                ) . " is not allowed to modify channel " . $channel->getName()
            );
            throw new ChannelAuthorizationException(
                "User" . $user->getDisplayName() . " is not allowed to modify channel " . $channel->getName()
            );
        }

        if ($this->doesChannelNameExistInTeam($user, $newName)) {
            $this->logger->info(__FUNCTION__ . " $newName  is already in use for this user!");
            throw new ChannelOpException(" $newName  is already in use for this user!");
        }

        $channel->setName($newName);

        if (!$this->dataController->updateChannel($channel)) {
            throw new ChannelOpException("Failed updating channel");
        }

        if (!$this->fOps->onRenameChannel($channel)) {
            $this->logger->error(
                __FUNCTION__ . " Failed file operation during rename for channel " . $channel->getId()
            );
            throw new ChannelOpException(" Failed file operation during rename for channel " . $channel->getId());
        }

        $event = new ChannelUpdateEvent($channel);
        $this->eventManager->publishEvent($event);
    }

    /**
     *
     * Rename a channel
     *
     * @param User $user
     * @param Channel $channel
     * @throws ChannelAuthorizationException
     */
    public function removeChannel(User $user, Channel $channel) : void
    {

        // TODO - replace with the new authorization system
        if (!$this->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_OWNER)) {
            $this->logger->error(
                __FUNCTION__ . " User " . $user->getDisplayName(
                ) . " is not allowed to delete channel " . $channel->getName()
            );
            throw new ChannelAuthorizationException(
                "User" . $user->getDisplayName() . " is not allowed to delete channel " . $channel->getName()
            );
        }
        // Delete of the channel is done in the background after reporting to users.

        $event = new ChannelRemovedEvent($channel);
        $this->eventManager->publishEvent($event);

        // if channel belongs to a standard team, notify the team members...
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if (!$team->isSelfTeam()) {
            $event = new TeamRemoveChannelEvent($team, $channel);
            $this->eventManager->publishEvent($event);
        }
    }

    /**
     *
     * Get channel permission
     * @param User $user
     * @param Channel $channel
     * @param int $minimumGlobalAuthRequired
     * @param int|null $authIsPublicFor
     * @return bool
     */
    public function isChannelOperationAllowed(User $user, Channel $channel, int $minimumGlobalAuthRequired) : bool
    {
        $auth = $this->globalAuthContext->getEffectivePermission(
            $user,
            GlobalAuthContext::CONTEXT_TYPE_CHANNEL,
            (int)$channel->getId()
        );
        return ($auth >= $minimumGlobalAuthRequired);
    }


    /**
     * Get channel list for a team
     * @param int $teamId
     * @return array
     * @throws DatabaseException
     */
    public function getChannelsForTeam(int $teamId) : array
    {
        $channels = array();
        foreach ($this->dataController->getChannelsByTeamId($teamId) as $channelRec) {
            $channel = Channel::withDBData($channelRec);
            $channels[] = $channel;
        }

        return $channels;
    }

    /**
     * Check if the channel name is already in use inside the default team space of user
     *
     * @param User $user
     * @param $channelName
     * @return bool
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function doesChannelNameExistInTeam(User $user, $channelName) : bool
    {
        $team = $this->dataController->getDefaultTeamForUser((int)$user->getId());
        if (empty($team)) {
            throw new ChannelOpException("Team for user " . $user->getId() . " not found!");
        }

        $channels = $this->getChannelsForTeam((int)$team->getId());
        if (empty($channels)) {
            return false;
        }

        foreach ($channels as $channel) {
            if ($channel->getName() == $channelName) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * Update a channel from admin
     * TODO - Improve this.., we don't need two methods to update a channel
     *
     * @param User $user
     * @param Channel $oldChannel
     * @param Channel $newChannel
     * @throws ChannelOpException
     * @throws ChannelAuthorizationException
     * @throws DatabaseException
     */
    public function updateChannel(User $user, Channel $oldChannel, Channel $newChannel) : void
    {
        if (!$this->isChannelOperationAllowed($user, $oldChannel, GlobalAuthContext::AUTH_MANAGE)) {
            $this->logger->error(
                __FUNCTION__ . " User " . $user->getDisplayName(
                ) . " is not allowed to modify channel " . $oldChannel->getName()
            );
            throw new ChannelAuthorizationException(
                "User" . $user->getDisplayName() . " is not allowed to modify channel " . $oldChannel->getName()
            );
        }

        if ($oldChannel->getId() != $newChannel->getId()) {
            $this->logger->error(
                __FUNCTION__ . " Failed updating channel " . $newChannel->getName() . " channel id mismatch"
            );
            throw new ChannelOpException("Failed updating channel " . $newChannel->getName() . " channel id mismatch");
        }

        $channelByName = $this->dataController->getChannelByName($newChannel->getTeamId(), $newChannel->getName());
        if (!empty($channelByName) && $channelByName->getId() != $newChannel->getId()) {
            $this->logger->info(__FUNCTION__ . " " . $newChannel->getName() . " is already in use for this user!");
            throw new ChannelOpException($newChannel->getName() . " is already in use for this user!");
        }

        $channelByEmail = $this->dataController->getChannelByEmail($newChannel->getEmail());
        if (!empty($channelByEmail) && $channelByEmail->getId() != $newChannel->getId()) {
            $this->logger->info(__FUNCTION__ . " " . $newChannel->getEmail() . " is already in use for this user!");
            throw new ChannelOpException($newChannel->getEmail() . " is already in use for this user!");
        }

        if (!$this->dataController->updateChannel($newChannel)) {
            throw new ChannelOpException("Failed updating channel");
        }

        // TODO: check if this is valid or need a new function
        if ($newChannel->getName() != $oldChannel->getName()) {
            if (!$this->fOps->onRenameChannel($newChannel)) {
                $this->logger->error(
                    __FUNCTION__ . " Failed file operation during rename for channel " . $oldChannel->getId()
                );
                throw new ChannelOpException(
                    " Failed file operation during rename for channel " . $oldChannel->getId()
                );
            }
        }

        $event = new ChannelUpdateEvent($newChannel);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param User $user
     * @param Channel $channel
     * @param string|null $channelName
     * @param string|null $blurb
     * @param bool|null $allowJoin
     * @param bool|null $requireJoinApproval
     * @param bool|null $allowExternalRead
     * @param string|null $locale
     * @return Channel
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function update(
        User $user,
        Channel $channel,
        ?string $channelName = null,
        ?string $blurb = null,
        ?bool $allowJoin = null,
        ?bool $requireJoinApproval = null,
        ?bool $allowExternalRead = null,
        ?string $locale = null
    ) : Channel {

        // verify permissions
        if (!$this->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_ADMIN)) {
            $this->logger->error('User ' . $user->getId() . ' does not have authority to update ' . $channel->getId());
            throw new ChannelOpException(I18n::get('messages.channel_unauthorized_update'), 401);
        }

        if ($channelName !== null) {
            $channel->setName($channelName);
        }

        if ($blurb !== null) {
            if (StringUtility::containsProfanity($blurb, $violatingWords)) {
                $this->logger->error(__FUNCTION__ . " Blurb has profanity: " . $blurb);
                $violatingWords = implode(', ', $violatingWords);
                throw new ChannelOpException(I18n::get('messages.channel_description_profanity', ['words' => $violatingWords]), 400);
            }
            $channel->setBlurb($blurb);
        }

        // handle allow self join
        if (isset($allowJoin)) {

            if ($allowJoin === true) {

                // get current ChannelInvite public hash or create it
                $publicHash = $this->dataController->getPublicHashForResource('ChannelInvite', $channel->getId());
                if ($publicHash === null) {
                    $publicHash = $this->dataController->createPublicHash('ChannelInvite', $channel->getId());
                }

                // get the current ChannelInvite short url or create it
                $shorUrlHash = $this->dataController->findShortUrlHashForResource('ChannelInvite', $channel->getId());
                if ($shorUrlHash === null) {
                    // create the short url
                    $this->dataController->createShortUrl($this->createChannelInviteUrl($channel->getId(), $publicHash), null,'ChannelInvite', $channel->getId());
                }

            } else {

                // delete current public hash
                $this->dataController->deletePublicHashByResource('ChannelInvite', $channel->getId());

                // delete current short url
                $this->dataController->deleteShortUrlByResource('ChannelInvite', $channel->getId());

            }
        }

        if ($requireJoinApproval !== null) {
            $channel->setRequireJoinApproval($requireJoinApproval);
        }

        if ($allowExternalRead !== null) {
            $channel->setAllowExternalRead($allowExternalRead);
        }

        $localeChanged = false;
        if ($locale !== null && $locale !== $channel->getLocale()) {
            $channel->setLocale($locale);
            $localeChanged = true;
        }

        $this->dataController->updateChannel($channel);

        // Publish this event for foreground and background users
        $event = new ChannelUpdateEvent($channel, $user);
        $this->eventManager->publishEvent($event);

        // if the channel is part of a team, trigger the team.update_channel event
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if (!$team->isSelfTeam()) {
            $event = new TeamUpdateChannelEvent($team, $channel);
            $this->eventManager->publishEvent($event);
        }

        if ($localeChanged) {
            $botMessage = "[{$user->getDisplayName()}](user://{$user->getId()}) changed channel locale to \"{$locale}\"";
            $botEvent = MessageBot::create(2, $botMessage, 'bot.channel_locale_changed', null, [
                'user_id' => $user->getId(),
                'user_name' => $user->getDisplayName(),
                'locale' => $channel->getLocale(),
            ]);
            $this->chatOps->raiseBotNotification($botEvent, $channel->getId());
        }

        return $channel;
    }

    /**
     * Update Channel User
     *
     * @param User $user
     * @param ChannelUser $oldChannelUser
     * @param ChannelUser $newChannelUser
     * @throws ChannelAuthorizationException
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function updateChannelUser(User $user, ChannelUser $oldChannelUser, ChannelUser $newChannelUser) : void
    {
        if ($oldChannelUser->getChannelId() != $newChannelUser->getChannelId()) {
            $this->logger->debug(__FUNCTION__ . " Failed updating channel user channel id mismatch");
            throw new ChannelOpException("Failed updating channel channel id mismatch");
        }

        if ($oldChannelUser->getUserId() != $newChannelUser->getUserId()) {
            $this->logger->debug(__FUNCTION__ . " Failed updating channel user, user id mismatch");
            throw new ChannelOpException("Failed updating channel user, user id mismatch");
        }

        if ($oldChannelUser->getUserRole() == $newChannelUser->getUserRole()) {
            $this->logger->debug(__FUNCTION__ . " User Role is same in channel. Nothing to update");
            throw new ChannelOpException(" User Role is same in channel. Nothing to update");
        }

        $channel = $this->dataController->getChannelById($oldChannelUser->getChannelId());

        if (!$this->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_MANAGE)) {
            $this->logger->error(
                __FUNCTION__ . " User " . $user->getDisplayName(
                ) . " is not allowed to modify channel " . $channel->getName()
            );
            throw new ChannelAuthorizationException(
                "User" . $user->getDisplayName() . " is not allowed to modify channel " . $channel->getName()
            );
        }

        if (!$this->dataController->updateChannelUser($newChannelUser)) {
            throw new ChannelOpException("Failed updating channel user");
        }

        $event = new UserUpdatedInChannelEvent($oldChannelUser, $newChannelUser);
        $this->eventManager->publishEvent($event);

        $newUser = $this->dataController->getUserById($newChannelUser->getUserId());

        // Create a bot message
        $botMessage = "[{$newUser->getDisplayName()}](user://{$newChannelUser->getUserId()}) role is set to {$newChannelUser->getUserRoleAsString()} from {$oldChannelUser->getUserRoleAsString()} in this channel";
        $botEvent = MessageBot::create(2, $botMessage, 'bot.channel_change_role', null, [
            'user_name' => $newUser->getDisplayName(),
            'user_id' => $newChannelUser->getUserId(),
            'new_role' => $newChannelUser->getUserRoleAsString(),
            'old_role' => $oldChannelUser->getUserRoleAsString(),
        ]);
        $this->chatOps->raiseBotNotification($botEvent, $newChannelUser->getChannelId());
    }

    /**
     * Raise a bot message for users to see
     *
     * @param ?User $inviter
     * @param User[] $invitees
     * @param int $channelId
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function raiseUserAddedBotMessage(?User $inviter, array $invitees, int $channelId) : void
    {
        if (empty($invitees)) {
            return;
        }

        if (count($invitees) == 1) {
            if (empty($inviter)) {
                $userToAdd = $invitees[0];
                $botMessage = "[{$userToAdd->getDisplayName()}](user://{$userToAdd->getId()}) joined the channel";
                $i18nKey = 'bot.channel_user_joined';
                $i18nCount = null;
                $i18nParams = [
                    'user_name' => $userToAdd->getDisplayName(),
                    'user_id' => $userToAdd->getId(),
                ];
            } else {
                $userToAdd = $invitees[0];
                $botMessage = "[{$inviter->getDisplayName()}](user://{$inviter->getId()}) has added [{$userToAdd->getDisplayName()}](user://{$userToAdd->getId()}) to this channel";
                $i18nKey = 'bot.channel_user_added';
                $i18nCount = null;
                $i18nParams = [
                    'inviter_name' => $inviter->getDisplayName(),
                    'inviter_id' => $inviter->getId(),
                    'invitee_name' => $userToAdd->getDisplayName(),
                    'invitee_id' => $userToAdd->getId(),
                ];
            }
        } else {
            $userToAdd = array_shift($invitees);
            $count = count($invitees);
            $emails = array_map(
                function(User $item) {
                    return $item->getEmail();
                },
                $invitees
            );
            $inviteesIds = implode(',', $emails);

            if (empty($inviter)) {
                $botMessage = "[{$userToAdd->getDisplayName()}](user://{$userToAdd->getId()})  and [$count more](users://$inviteesIds) joined this channel";
                $i18nKey = 'bot.channel_user_joined_multiple';
                $i18nCount = $count;
                $i18nParams = [
                    'user_name' => $userToAdd->getDisplayName(),
                    'user_id' => $userToAdd->getId(),
                    'count' => $count,
                    'invitees_ids' => $inviteesIds,
                ];
            } else {
                $botMessage = "[{$inviter->getDisplayName()}](user://{$inviter->getId()}) has added " .
                              "[{$userToAdd->getDisplayName()}](user://{$userToAdd->getId()})  and " .
                              "[$count more](users://$inviteesIds) to this channel";
                $i18nKey = 'bot.channel_user_added_multiple';
                $i18nCount = $count;
                $i18nParams = [
                    'inviter_name' => $inviter->getDisplayName(),
                    'inviter_id' => $inviter->getId(),
                    'invitee_name' => $userToAdd->getDisplayName(),
                    'invitee_id' => $userToAdd->getId(),
                    'count' => $count,
                    'invitees_ids' => $inviteesIds,
                ];
            }
        }

        $botEvent = MessageBot::create(2, $botMessage, $i18nKey, $i18nCount, $i18nParams);
        $this->chatOps->raiseBotNotification($botEvent, $channelId);
    }

    /**
     *
     * Send email to user that was added
     *
     * @param int $userIdAdded
     * @param int $userIdAddedBy
     * @param Channel $channel
     * @throws DatabaseException
     * @throws InvalidEmailAddressException
     * @throws \Exception
     */
    public function sendChannelAddToUserEmail(int $userIdAdded, int $userIdAddedBy, Channel $channel)
    {
        $addedBy = $this->dataController->getUserById($userIdAddedBy);
        if (empty($addedBy)) {
            // Creator is not found. Malformed . ignore
            $this->logger->error("Added id $addedBy not found. Skipping notification email");
            return;
        }

        $userAdded = $this->dataController->getUserById($userIdAdded);
        if (empty($userAdded)) {
            // Creator is not found. Malformed . ignore
            $this->logger->error("Added id $userAdded not found. Skipping notification email");
            return;
        }

        $openSendingEmail = $this->dataController->getMemberEmailForChannel($channel->getId(), $userIdAdded);

        $channelName = $channel->getName();

        $channelUrl = $this->getChannelInviteUrl($channel->getId(), $userIdAdded);


        $subject = $addedBy->getDisplayName() . " has added you to a channel in AirSend!";
        $body = "<p>We thought you might like to know that " . $addedBy->getDisplayName(
            ) . " has added you to channel <b>$channelName.</b> </p>";
        $body .= "<p>Now, you can share files, send messages, complete tasks, and get work done in one space.</p>";

        if ($openSendingEmail !== null) {
            $body_after_button = "<p>You can also post messages to the channel by sending an email to:</p>";
            $body_after_button .= "<p style='text-align: center'><a href='mailto:{$openSendingEmail}'>{$openSendingEmail}</a></p>";
        } else {
            $body_after_button = "<br/>";
        }

        try {
            $this->logger->info("Sending user added to channel email to " . $userAdded->getEmail());

            $message = $this->mailer
                ->createMessage($userAdded->getDisplayName() . " <" . $userAdded->getEmail() . ">")
                ->subject($subject)
                ->from($channel->getEmail(), "AirSend - {$channel->getName()}")
                ->body(
                    'general_template',
                    [
                        'subject' => $subject,
                        'display_name' => $userAdded->getDisplayName(),
                        'byline_text' => '',
                        'html_body_text' => $body,
                        'html_body_after_button_text' => $body_after_button,
                        'button_url' => $channelUrl,
                        'button_text' => "View Channel"
                    ]
                );
            $this->mailer->send($message);
        } catch (ASException $e) {
            $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " " . $e->getMessage());
        }
    }


    /**
     * @param User $userToRemove
     * @param User $removedBy
     * @param Channel $channel
     * @param bool $blacklist
     * @param bool $allowOrphanChannel
     * @throws ChannelAuthorizationException
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function removeUserFromChannel(User $userToRemove, User $removedBy, Channel $channel, bool $blacklist = false, bool $allowOrphanChannel = false): void
    {
        // Owner cannot leave, unless we say that we allow orphans
        $orphanChannel = false;
        if ((int)$channel->getOwnedBy() == (int)$userToRemove->getId()) {
            if (!$allowOrphanChannel) {
                throw new ChannelAuthorizationException(' Owner of the channel cannot leave the channel!');
            } else {
                $orphanChannel = true;
            }
        }

        // nobody can leave 1x1 channels
        // check if the user can be added to the channel (one on one channels)
        if ($channel->getOneOnOne()) {
            throw new ChannelAuthorizationException("Can't remove a user from an 1x1 channel");
        }

        $userRemoved = false;
        // Check if this user is part of the channel
        if (!empty($this->dataController->getUserChannel((int)$channel->getId(), (int)$userToRemove->getId()))) {
            if ($blacklist) {
                $this->dataController->blacklistUserOnChannel($userToRemove->getEmail(), $channel->getId());
            }
            $this->logger->info('Removing user ' . $userToRemove->getEmail() . ' from ' . $channel->getName());
            if ($this->dataController->dropChannelUser($channel->getId(), $userToRemove->getId()) == 0) {
                // Failed
                throw new ChannelOpException('Failed removing using from channel');
            }

            $userRemoved = true;
        }

        // update channel owner if the channel if the channel became orphan
        if ($orphanChannel) {
            $channel->setOwnedBy(0);
            $this->dataController->updateChannel($channel);
        }

        // unassign the user for all actions on the channel
        $this->dataController->unassignAllActionsOnChannel($userToRemove->getId(), $channel->getId());


        $event = new UserRemovedFromChannelEvent(
            (int)$channel->getId(),
            (int)$userToRemove->getId(),
            (int)$removedBy->getId()
        );
        $this->eventManager->publishEvent($event);

        // if the channel is part of a team, trigger the team.update_channel event
        $team = $this->dataController->getTeamByTeamId($channel->getTeamId());
        if (!$team->isSelfTeam()) {
            $event = new TeamUpdateChannelEvent($team, $channel);
            $this->eventManager->publishEvent($event);
        }

        // Raise bot only if the user is really removed. if we are just forcing cache refresh
        // then ignore bot message
        if ($userRemoved) {
            if ($userToRemove->getId() !== $removedBy->getId()) {
                $botMessage = "[{$removedBy->getDisplayName()}](user://{$removedBy->getId()})  has removed [{$userToRemove->getDisplayName()}](user://{$userToRemove->getId()}) from this channel";
                $i18nKey = 'bot.channel_user_removed';
                $i18nParams = [
                    'removed_by_name' => $removedBy->getDisplayName(),
                    'removed_by_id' => $removedBy->getId(),
                    'removed_name' => $userToRemove->getDisplayName(),
                    'removed_id' => $userToRemove->getId(),
                ];
            } else {
                $botMessage = "[{$userToRemove->getDisplayName()}](user://{$userToRemove->getId()}) has left the channel";
                $i18nKey = 'bot.channel_user_left';
                $i18nParams = [
                    'user_name' => $userToRemove->getDisplayName(),
                    'user_id' => $userToRemove->getId(),
                ];
            }
            $botEvent = MessageBot::create(2, $botMessage, $i18nKey, null, $i18nParams);
            $this->chatOps->raiseBotNotification($botEvent, $channel->getId());
        }
    }

    /**
     * Called daily to perform auto closing of channel
     */
    public function onCron() : void
    {
        // Get all expired channel and auto close them
        foreach ($this->dataController->getOpenChannelsExpired() as $dataRec) {
            $channel = Channel::withDBData($dataRec);
            $this->logger->info(
                __FUNCTION__ . " : Channel [" . $channel->getId() . "] inactive for " .
                $channel->getCloseAfterDays() . " days. Closing"
            );
            if (!$this->closeChannel($channel)) {
                $this->logger->error(" Failed closing channel ");
            }
        }
    }

    /**
     * @param Channel $channel
     * @return void
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function closeChannel(Channel $channel) : void
    {
        $channel->setChannelStatus(Channel::CHANNEL_STATUS_CLOSED);

        //TODO: Need to audit record this
        try {
            $this->dataController->updateChannel($channel);
        } catch (\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            throw new ChannelOpException("Database error updating channel");
        }

        $event = new ChannelUpdateEvent($channel);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Channel $channel
     * @return void
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function activateChannel(Channel $channel) : void
    {
        $channel->setChannelStatus(Channel::CHANNEL_STATUS_OPEN);
        $channel->setLastActiveOn(date('Y-m-d H:i:s'));

        //TODO: Need to audit record this
        try {
            $this->dataController->updateChannel($channel);
        } catch (\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            throw new ChannelOpException("Database error updating channel");
        }

        $event = new ChannelUpdateEvent($channel);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Channel $channel
     * @param int $userIdToSet
     * @param int $userRole
     * @return void
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function setUserRole(Channel $channel, int $userIdToSet, int $userRole) : void
    {
        if (empty($userToSet = $this->dataController->getUserChannel((int)$channel->getId(), $userIdToSet))) {
            throw new ChannelOpException("User " . $userIdToSet . " is not a part of channel " . $channel->getId());
        }

        $userToSet->setUserRole($userRole);


        //TODO: Need to audit record this
        try {
            $this->dataController->updateChannelUser($userToSet);
        } catch (\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            throw new ChannelOpException("Database error updating channel");
        }

        $event = new ChannelUpdateEvent($channel);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Channel $channel
     * @param User $user
     * @param bool $isFavorite
     * @return void
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function setFavorite(Channel $channel, User $user, bool $isFavorite) : void
    {
        if (empty($userToSet = $this->dataController->getUserChannel((int)$channel->getId(), $user->getId()))) {
            throw new ChannelOpException("User " . $user->getId() . " is not a part of channel " . $channel->getId());
        }

        $userToSet->setIsFavorite($isFavorite);


        //TODO: Need to audit record this
        try {
            $this->dataController->updateChannelUser($userToSet);
        } catch (\PDOException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : " . $ex->getMessage());
            throw new ChannelOpException("Database error updating channel");
        }

        $event = new ChannelUpdateEvent($channel, $user);
        $this->eventManager->publishEvent($event);
    }


    /**
     *
     * Store channel asset
     *
     * @param User $user
     * @param Channel $channel
     * @param string $assetType
     * @param string $phyFile
     * @param $extension
     * @throws DatabaseException
     */
    public function storeAsset(User $user, Channel $channel, string $assetType, string $phyFile, $extension) : void
    {
        $aType = Asset::ASSET_TYPE_BACKGROUND_IMAGE;
        if ($assetType == 'logo') {
            $aType = Asset::ASSET_TYPE_CHANNEL_LOGO;
        }

        $asset = Asset::create(
            (int)$channel->getId(),
            Asset::CONTEXT_TYPE_CHANNEL,
            $aType,
            Asset::ATTR_SIZE_LARGE,
            "image/$extension",
            file_get_contents($phyFile),
            (int)$user->getId()
        );

        if (!empty(
        $as = $this->dataController->getAsset(
            (int)$channel->getId(),
            Asset::CONTEXT_TYPE_CHANNEL,
            $aType,
            Asset::ATTR_SIZE_LARGE
        )
        )
        ) {
            $asset->setId((int)$as->getId());
            $this->dataController->updateAsset($asset);
        } else {
            $this->dataController->createAsset($asset);
        }

        // always trigger update, to replace the updated_on value and ensure tha has_logo and has_background flags
        if ($assetType === 'logo') {
            $channel->setHasLogo(true);
        } else {
            $channel->setHasBackground(true);
        }
        $this->dataController->updateChannel($channel);

        $event = new ChannelUpdateEvent($channel);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Channel $channel
     * @param bool $bgClear
     * @param bool $logoClear
     * @throws DatabaseException
     */
    public function clearAsset(Channel $channel, bool $bgClear = false, bool $logoClear = false) : void
    {
        if ($bgClear) {
            $this->logger->info("Clearing Background for " . $channel->getId());
            $channel->setHasBackground(false);
        }

        if ($logoClear) {
            $this->logger->info("Clearing Logo for " . $channel->getId());

            $channel->setHasLogo(false);
        }

        $this->dataController->updateChannel($channel);

        $event = new ChannelUpdateEvent($channel);
        $this->eventManager->publishEvent($event);
    }

    /**
     *
     * Retrieve Channel asset
     *
     * @param Channel $channel
     * @param string $assetType
     * @return Asset
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function getAsset(Channel $channel, string $assetType) : Asset
    {
        $aType = Asset::ASSET_TYPE_BACKGROUND_IMAGE;
        if ($assetType == 'logo') {
            $aType = Asset::ASSET_TYPE_CHANNEL_LOGO;
        }

        $asset = $this->dataController->getAsset(
            (int)$channel->getId(),
            Asset::CONTEXT_TYPE_CHANNEL,
            $aType,
            Asset::ATTR_SIZE_LARGE
        );
        if (!empty($asset)) {
            return $asset;
        }

        throw new ChannelOpException("Asset is not found!");
    }

    public function extractHistoryParams(array $params) : array
    {
        $channelId = (int)$params['channel_id'];
        $limit = 0;
        if (isset($params['limit'])) {
            $limit = (int)$params['limit'];
        }

        $limit_newer = 0;
        if (isset($params['limit_newer'])) {
            $limit_newer = (int)$params['limit_newer'];
        }

        $e_cursor = '';
        if (isset($params['cursor'])) {
            $e_cursor = $params['cursor'];
        }

        if ($limit_newer == 0 && $limit == 0) {
            $limit = 10;
        }

        return [$channelId, $e_cursor, $limit, $limit_newer];
    }

    /**
     * @param ResponseInterface $response
     * @param $channelId
     * @param $e_cursor
     * @param $limit
     * @param $limit_newer
     * @param string|null $before
     * @return ResponseInterface
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     */
    public function handleHistoryResponse(
        ResponseInterface $response,
        $channelId,
        $e_cursor,
        $limit,
        $limit_newer,
        ?string $before = null
    ) : ResponseInterface {
        if (!empty($e_cursor)) {
            $cursor = (int)base64_decode($e_cursor);
        } else {
            $cursor = 0;
        }

        $messages = [];
        $nextCursor = "";
        $hasMore = false;

        if ($limit > 0) {
            $this->logger->debug("Getting $limit older from $cursor");
            $messages = $this->getChannelHistory($channelId, $cursor, $limit, $before);

            //TODO: Need a better way to find if there are more
            $hasMore = ($limit > count($messages)) ? false : true;
            if (count($messages) == 0) {
                $nextCursor = "";
            } else {
                $nextCursor = base64_encode('' . (($messages[count($messages) - 1])->getArray())['id']);
            }
        }
        $nextCursorNewer = "";
        $hasMoreNewer = false;
        // If cursor is not provided, we are already at newest, so limit_newer is not needed
        if (!empty($e_cursor) && ($limit_newer > 0)) {
            //$this->logger->debug("Getting $limit_newer newer from $cursor" );

            $limit_newer_mod = $limit_newer;

            if ($limit <= 0) {
                // Add one more, so we can snip off the message pointed by the current cursor since only limit_newer
                // is provided.If both limit and limit_newer are provided, we need to include the message pointed by cursor
                $limit_newer_mod = $limit_newer + 1;
            }

            $messagesNewer = $this->getChannelHistory($channelId, $cursor, $limit_newer_mod, $before, true);
            $hasMoreNewer = ($limit_newer > count($messagesNewer)) ? false : true;
            if (count($messagesNewer) == 0) {
                $nextCursorNewer = "";
            } else {
                // If only limit_newer is provided, then DO NOT include the message pointed by the supplied cursor

                if ($limit <= 0) {
                    //$this->logger->info("Removing the first element");
                    array_shift($messagesNewer);
                }

                $messages = array_merge(array_reverse($messagesNewer), $messages);
                if (!empty($messagesNewer)) {
                    $nextCursorNewer = base64_encode(
                        '' . (($messagesNewer[count($messagesNewer) - 1])->getArray())['id']
                    );
                }
            }
        }

        return JsonOutput::success()->withContent('messages', $messages)
            ->withContent('has_more', $hasMore)
            ->withContent('next_cursor', $nextCursor)
            ->withContent('has_more_newer', $hasMoreNewer)
            ->withContent('next_cursor_newer', $nextCursorNewer)
            ->write($response);
    }

    /**
     * @param User $user
     * @param Channel $channel
     * @param string $exportMode
     * @throws OverflowException
     * @throws DatabaseException
     */
    public function sendZipCallback(User $user, Channel $channel, string $exportMode)
    {
        # enable output of HTTP headers
        $options = new \ZipStream\Option\Archive();
        $options->setSendHttpHeaders(true);
        $options->setFlushOutput(true);


        $name = $channel->getName();
        $name = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $name);
        $name = mb_ereg_replace("([\.]{2,})", '', $name);


        # create a new zipstream object
        $zip = new \ZipStream\ZipStream($name . "_export.zip", $options);

        $content = <<<content
Zip generated by channel export operation from AirSend
Channel: {$channel->getName()}
content;

        $zip->addFile("readme.txt", $content);

        // If $exportMode requires Messages, add messages
        $msgCsv = $this->getMessageCsv($channel, $user);
        if (!empty($msgCsv)) {
            $zip->addFile("messages.csv", $msgCsv);
        }

        // If $exportMode requires actions, add actions
        $actionsCsv = $this->getActionsCsv($channel);
        if (!empty($actionsCsv)) {
            $zip->addFile("actions.csv", $actionsCsv);
        }

        try {
            // Add all files of this channel
            $this->addChannelFilesToZip($zip, $channel, $user);
        } catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . "Exception caught: " . $ex->getMessage());
        }

        $zip->finish();
    }

    /**
     *
     * Messages in CSV form from a channel
     *
     * @param Channel $channel
     * @param User $user
     * @return string
     * @throws DatabaseException
     */
    private function getMessageCsv(Channel $channel, User $user) : string
    {

        $before = null;
        $channelUser = $this->dataController->getUserChannel($channel->getId(), $user->getId());
        if ($channelUser instanceof ChannelUser) {
            $before = $channelUser->getBlockedOn();
        }

        $msgCsv = "When,Who,Message\n";
        $empty = true;
        foreach ($this->dataController->getMessagesByChannelId((int)$channel->getId(), null, null, $before) as $messageRec) {
            $msg = (Message::withDBData($messageRec));
            $msgTxt = $msg->getText();
            if (!empty($msgTxt) && $msg->getDisplayName() != "AirBot") {
                $msgCsv .= $msg->getCreatedOn() . ',' . $msg->getDisplayName() . ',' . $msgTxt . "\n";
                $empty = false;
            }
        }

        if (!$empty) {
            return $msgCsv;
        }
        return "";
    }


    /**
     * Get actions list as a csv string
     * @param Channel $channel
     * @return string
     * @throws DatabaseException
     */
    private function getActionsCsv(Channel $channel) : string
    {
        $actionCsv = "Name,Description,Type,Status\n";
        $records = $this->dataController->getActions((int)$channel->getId(), null, null, true);

        $empty = true;
        foreach ($records as $record) {
            $action = $this->dataController->getActionWithUsers((int)$record['id']);

            $actionType = "Unknown";
            switch ($action->getActionType()) {
                case Action::ACTION_TYPE_REMINDER:
                    $actionType = "Reminder";
                    break;
                case Action::ACTION_TYPE_REVIEW:
                    $actionType = "Review";
                    break;
                case Action::ACTION_TYPE_UPDATE:
                    $actionType = "Update";
                    break;
                case Action::ACTION_TYPE_SIGN:
                    $actionType = "Sign";
                    break;
            }

            $actionStatus = "Unknown";
            if ($action->getActionStatus() == Action::ACTION_STATUS_PENDING) {
                $actionStatus = "Pending";
            } else {
                if ($action->getActionStatus() == Action::ACTION_STATUS_COMPLETE) {
                    $actionStatus = "Complete";
                }
            }

            $actionCsv .= $action->getName() . ',' . $action->getDesc(
                ) . ',' . $actionType . ',' . $actionStatus . "\n";
            $empty = false;
        }

        if (!$empty) {
            return "$actionCsv";
        }
        return "";
    }

    /**
     *
     * Add files from a channel to the supplied zip handle
     *
     * @param ZipStream $zip
     * @param Channel $channel
     * @param User $user
     */
    private function addChannelFilesToZip(ZipStream $zip, Channel $channel, User $user): void
    {

        $paths = $this->fOps->getChannelRoots($channel);
        foreach ($paths as $path) {
            try {
                if (preg_match('/^(.*)\/([^\/]+)$/', $path->getPhysicalPath(), $matches)) {
                    $this->fOps->processZipFolder($zip, $matches[1], $user, $matches[2]);
                }
            } catch (NotAFolderException | NotFoundException | StorageServiceException $e) {
                // do nothing...
            }
        }
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return string
     * @throws \Exception
     */
    public function getChannelInviteUrl(int $channelId, int $userId) : string
    {
        // TODO - implement cache for those channel invite urls
        // is there a channel invite notification for this user?
        $inviteNotification = $this->dataController->findInviteNotification($channelId, $userId);
        if ($inviteNotification === null) {
            $inviteNotification = Notification::create(
                null,
                (int)$userId,
                Notification::NOTIFICATION_CONTEXT_TYPE_CHANNEL,
                (int)$channelId,
                Notification::NOTIFICATION_MEDIA_TYPE_EMAIL,
                Notification::NOTIFICATION_TYPE_CHANNEL_INVITE,
                ""
            );
            $inviteNotification = $this->dataController->createNotification($inviteNotification);
        }

        return rtrim(
                   $this->config->get('/app/ui/baseurl'),
                   '/'
               ) . "/channel/" . $channelId . "?token={$inviteNotification->getToken()}";
    }

    /**
     * @param int $userId1
     * @param int $userId2
     * @return Channel|null
     * @throws DatabaseException
     */
    public function findOneOnOne(int $userId1, int $userId2) : ?Channel
    {
        return $this->dataController->findOneOnOneChannel($userId1, $userId2);
    }

    /**
     * @param User $user
     * @param Channel $channel
     * @param int $newOwnerId
     * @param int|null $newTeamId
     * @throws ChannelAuthorizationException
     * @throws ChannelOpException
     * @throws DatabaseException
     */
    public function transferOwnership(User $user, Channel $channel, int $newOwnerId, ?int $newTeamId)
    {

        // check permissions
        if ($user->cannot('transfer', $channel)) {
            $message = " User {$user->getDisplayName()} is not allowed to transfer channel `{$channel->getName()}` ownership.";
            $this->logger->error(__FUNCTION__ . $message);
            throw new ChannelAuthorizationException($message);
        }

        // find the team
        if ($newTeamId === null) {

            // if no team is defined, try to find the self team for the destiny user
            $team = $this->dataController->getSelfTeam($newOwnerId);
            if ($team === null) {
                throw new ChannelOpException("Can't find self team id for user {$user->getId()}");
            }
            $newTeamId = $team->getId();

        } else {

            // provided team must exist and must be a standard team (not self team)
            $team = $this->dataController->getTeamByTeamId($newTeamId);
            if ($team === null) {
                throw new ChannelOpException("Team `$newTeamId` as not found.");
            }
            if ($team->getTeamType() !== Team::TEAM_TYPE_STANDARD) {
                throw new ChannelOpException("Team `$newTeamId` is a self team.");
            }

            // cannot transfer DM channels to standard teams (only to other users)
            if ($channel->getOneOnOne()) {
                throw new ChannelOpException("Can't transfer a DM channel to a standard team.");
            }

        }

        // check if the new owner is member of the channel
        if (!$this->dataController->isChannelMember($channel->getId(), $newOwnerId)) {
            throw new ChannelOpException("User {$newOwnerId} is not a member of this channel");
        }

        // check if the new owner is member of the destiny team
        if (!$this->dataController->isTeamMember($team->getId(), $newOwnerId)) {
            throw new ChannelOpException("User {$newOwnerId} is not a member of team {$team->getName()}");
        }

        // copy all the files to the new paths...
        // first get the current paths for the channel
        $currentPaths = $this->dataController->getChannelPathsByChannelId($channel->getId());

        // go though each path...
        foreach ($currentPaths as $currentPath) {

            // get the channel path object
            $channelPath = $this->dataController->getChannelPathById(intval($currentPath['id']));

            // find the current physical path
            $oldPath = preg_replace('/^\/f/', '', $channelPath->getPath());

            // generate the new path, using the new team id
            $newPath = preg_replace("#^/[0-9]+/#", "/$newTeamId/", $oldPath);

            // if paths are identical, just ignore
            if ($oldPath === $newPath) {
                continue;
            }

            // ensure destiny path structure exists
            $steps = explode('/', trim($newPath, '/'));
            array_pop($steps); // ignore the last step of the path (this one will be moved from destiny

            $partialPath = '';
            foreach ($steps as $step) {
                if (!$this->storageService->exists("$partialPath/$step")) {
                    try {
                        $this->storageService->createFolder($partialPath, $step, (string)$newOwnerId);
                    } catch (DestinyPathAlreadyExistsException | NotAFolderException | NotFoundException $e) {
                        $this->logger->error('Error on ownership transfer (can\'t create destiny folder): ' . get_class($e) . ' --> ' . $e->getMessage());
                        throw new ChannelOpException("Impossible to move storage ownership of channel {$channel->getId()} to user {$newOwnerId}");
                    }
                }
                $partialPath .= "/$step";
            }

            // ... move the files to the new path
            try {
                $this->storageService->move($oldPath, $newPath);
            } catch (DestinyPathAlreadyExistsException | InvalidPathException | NotFoundException | StorageServiceException $e) {
                $this->logger->error('Error on ownership transfer (can\'t move folder): ' . get_class($e) . ' --> ' . $e->getMessage());
                throw new ChannelOpException("Impossible to move storage ownership of channel {$channel->getId()} to user {$newOwnerId}");
            }

            // update the paths of the channel
            $channelPath->setPath("/f$newPath");
            $this->dataController->updateChannelPath($channelPath);

        }

        $channel->setOwnedBy($newOwnerId);
        $channel->setTeamId($newTeamId);
        $this->dataController->updateChannel($channel);

        $this->setUserRole($channel, $newOwnerId, ChannelUser::CHANNEL_USER_ROLE_ADMIN);

    }

    public function setDefaultJoinerRole(User $user, Channel $channel, int $newDefaultRoleId)
    {
        if (!$this->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_ADMIN)) {
            $message = "Not allowed to change the default joiner role for this channel";
            throw new ChannelAuthorizationException($message);
        }

        $channel->setDefaultJoinerRole($newDefaultRoleId);

        $this->dataController->updateChannel($channel);

        $event = new ChannelUpdateEvent($channel, $user);
        $this->eventManager->publishEvent($event);
    }

    public function setDefaultInviteeRole(User $user, Channel $channel, int $newDefaultRoleId)
    {
        if (!$this->isChannelOperationAllowed($user, $channel, GlobalAuthContext::AUTH_ADMIN)) {
            $message = "Not allowed to change the default invitee role for this channel";
            throw new ChannelAuthorizationException($message);
        }

        $channel->setDefaultInviteeRole($newDefaultRoleId);

        $this->dataController->updateChannel($channel);

        $event = new ChannelUpdateEvent($channel, $user);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param User $user
     * @param Channel $channel
     * @param bool $mute
     * @throws ChannelOpException
     */
    public function muteChannel(User $user, Channel $channel, bool $mute = false): void
    {
        try {
            $this->dataController->setChannelUserMuted($user, $channel, $mute);
        } catch (DatabaseException $e) {
            $message = $mute ? 'messages.channel_mute_error' : 'messages.channel_unmute_error';
            throw new ChannelOpException(I18n::get($message, ['channel' => $channel->getName()]));
        }

        $event = new ChannelUpdateEvent($channel, $user);
        $event->setSelfOnly(true);
        $this->eventManager->publishEvent($event);
    }

    public function raiseOauthClientAddedBotMessage(User $user, Client $client, int $channelId): void
    {
        $botMessage = "[{$user->getDisplayName()}](user://{$user->getId()}) has added the app/plugin {$client->getName()} to this channel";
        $i18nKey = 'bot.channel_oauth_client_added';
        $i18nParams = [
            'user_name' => $user->getDisplayName(),
            'user_id' => $user->getId(),
            'client_name' => $client->getName(),
        ];
        $botEvent = MessageBot::create(2, $botMessage, $i18nKey, null, $i18nParams);
        $this->chatOps->raiseBotNotification($botEvent, $channelId);

    }

    public function raiseOauthClientRemovedBotMessage(User $user, Client $client, int $channelId): void
    {
        $botMessage = "[{$user->getDisplayName()}](user://{$user->getId()}) has removed the app/plugin {$client->getName()} to this channel";
        $i18nKey = 'bot.channel_oauth_client_removed';
        $i18nParams = [
            'user_name' => $user->getDisplayName(),
            'user_id' => $user->getId(),
            'client_name' => $client->getName(),
        ];
        $botEvent = MessageBot::create(2, $botMessage, $i18nKey, null, $i18nParams);
        $this->chatOps->raiseBotNotification($botEvent, $channelId);

    }

    /**
     * @param Channel $channel
     * @param User $joiner
     * @param User $loggedUser
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidEmailAddressException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     */
    public function approveJoinRequest(Channel $channel, User $joiner, User $loggedUser)
    {
        if (!$this->dataController->channelUserPendingExists($channel->getId(), $joiner->getId())) {
            throw new ChannelOpException('There is no join request for this user on this channel');
        }

        // add the user to channel
        $this->addUserToChannelByJoin($channel, $joiner->getId(), $loggedUser->getId());

        // remove the pending join request
        $this->dataController->removeChannelUserPending($channel->getId(), $joiner->getId());

        // get the new list of pending users
        $pending = $this->dataController->getPendingUsersForChannel($channel->getId());
        $pending = array_map(
            function ($user) {
                return $this->objectFactory->normalizedObject($user);
            },
            $pending
        );

        // spread the news (notify all managers about the change)
        $event = new ChannelJoinUpdateEvent($channel, $joiner, $pending);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Channel $channel
     * @param User $joiner
     * @throws ChannelOpException
     */
    public function removeJoinRequest(Channel $channel, User $joiner)
    {
        if (!$this->dataController->channelUserPendingExists($channel->getId(), $joiner->getId())) {
            throw new ChannelOpException('There is no join request for this user on this channel');
        }

        // just remove the join request
        $this->dataController->removeChannelUserPending($channel->getId(), $joiner->getId());

        // get the new list of pending users
        $pending = $this->dataController->getPendingUsersForChannel($channel->getId());
        $pending = array_map(
            function ($user) {
                return $this->objectFactory->normalizedObject($user);
            },
            $pending
        );

        // spread the news (notify all managers about the change)
        $event = new ChannelJoinUpdateEvent($channel, $joiner, $pending);
        $this->eventManager->publishEvent($event);
    }

    /**
     * @param Channel $channel
     * @param int $userId
     * @param int|null $approverId
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidEmailAddressException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     */
    public function addUserToChannelByJoin(Channel $channel, int $userId, ?int $approverId = null)
    {
        $role = $channel->getDefaultJoinerRole();

        $addedUser = $this->addUserToChannel(
            $channel->getId(),
            $userId,
            $approverId ?? $channel->getOwnedBy(),
            $role
        );

        // Raise bot notification
        $this->raiseUserAddedBotMessage(null, [$addedUser], $channel->getId());
    }

    /**
     * @param int $channelId
     * @param string $hash
     * @return string
     */
    protected function createChannelInviteUrl(int $channelId, string $hash): string
    {
        return "/channel/{$channelId}/invite?hash=pub-{$hash}";
    }


    /**
     * @param int $channelId
     * @return array
     * @throws DatabaseException
     */
    public function getUsersForChannel(int $channelId): array
    {
        $users = [];
        foreach ($this->dataController->getUsersForChannel($channelId) as $userRec) {
            $users[] = User::withDBData($userRec);
        }
        return $users;
    }

    public function handlePaginatedLinks(Channel $channel, ?int $cursor = null, ?int $limitAfter = null, ?int $limitBefore = null): array
    {

        // if not limit is defined, set the limit after to 30
        if ($limitBefore === null && $limitAfter === null) {
            $limitAfter = 30;
        }

        $result = $this->dataController->listLinksForChannel($channel->getId(), $cursor, $limitAfter, $limitBefore);
        return array_map(function(array $item) {
            return [
                'id' => (int)$item['id'],
                'url_data' => Json::decode($item['content'] ?? '{}', true),
                'message_id' => (int)$item['message_id'],
            ];
        }, $result ?? []);

    }

    /**
     * Forces the change of ownership of a channel
     * @param Channel $channel
     * @param User $newOwner
     * @throws ChannelOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws InvalidEmailAddressException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UserOpException
     */
    public function setOwnership(Channel $channel, User $newOwner): void
    {

        // is the user member of the channel?
        if (!$this->dataController->isChannelMember($channel->getId(), $newOwner->getId())) {

            // not a member, so we add the user to the channel as admin
            $this->addUserToChannel($channel->getId(), $newOwner->getId(), $newOwner->getId(), ChannelUser::CHANNEL_USER_ROLE_ADMIN);

        } else {

            // already a member, so we just set the role of the user (admin)
            $this->setUserRole($channel, $newOwner->getId(), ChannelUser::CHANNEL_USER_ROLE_ADMIN);

        }

        // finally set channel ownership
        $channel->setOwnedBy($newOwner->getId());
        $this->dataController->updateChannel($channel);

    }

    /**
     * @param Channel $channel
     * @param User $loggedUser
     * @param string $reportSpamMessage
     * @throws DatabaseException
     */
    public function saveSpamReport(Channel $channel, User $loggedUser, string $reportSpamMessage): void
    {
        $channelId = $channel->getId();
        $reporterId = $loggedUser->getId();
        $isDM = $channel->getOneOnOne();
        $reportedUserId = 0;

        if ($isDM) {

            // is a DM channel, so the reported user is always the other member of the channel
            $users = $this->dataController->getUsersForChannel($channelId);
            foreach ($users as $user) {
                if ($user['id'] != $loggedUser) {
                    $reportedUserId = (int) $user['id'];
                    continue;
                }
            }

        } else {

            // on a regular channel, the user that invited the reporter is considered the reported user
            $channelUser = $this->dataController->getUserChannel($channelId, $reporterId);
            $reportedUserId = (int)$channelUser->getCreatedBy();

        }

        $this->dataController->saveSpamReport($channel->getId(), $reportedUserId, $isDM, $reporterId, $reportSpamMessage);
    }

}
