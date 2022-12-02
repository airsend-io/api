<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;

use CodeLathe\Core\Data\OAuth\ClientChannelDataStore;
use CodeLathe\Core\Data\OAuth\ClientDataStore;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\ActionHistory;
use CodeLathe\Core\Objects\Call;
use CodeLathe\Core\Objects\ChannelGroup;
use CodeLathe\Core\Objects\ChannelUserPending;
use CodeLathe\Core\Objects\ContactForm;
use CodeLathe\Core\Objects\Lock;
use CodeLathe\Core\Objects\Mention;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Objects\NotificationAbuseReport;
use CodeLathe\Core\Objects\OAuth\Client;
use CodeLathe\Core\Objects\Phone;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Core\Objects\PublicHash;
use CodeLathe\Core\Objects\UserSession;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\ChannelPath;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\Timeline;
use CodeLathe\Core\Objects\ExternalIdentity;
use CodeLathe\Core\Objects\UserCode;
use CodeLathe\Core\Objects\KeyStore;
use CodeLathe\Core\Objects\Asset;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Database\DatabaseService;
use Generator;
use mysql_xdevapi\Exception;
use Psr\Container\ContainerInterface;
use CodeLathe\Core\Exception\DatabaseException;

/**
 * Facade layer to call all data store functions.
 *
 * @package CodeLathe\Core\Data *
 */
class DataController
{
    /**
     * Declare container
     *
     * @var ContainerInterface
     */
    private $container;


    private $cache;

    /**
     * DataController constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = new CacheDataController($container);

    }

    public function getCacheDataController()
    {
        return $this->cache;
    }

    #region [User Section]

    /**
     * @param User $user
     * @return bool
     * @throws DatabaseException
     */
    public function createUser(User $user) : bool
    {
        if ($result = (new UserDataStore($this->container))->createUser($user)) {
            $this->cache->setUserCache($user);
        }
        return $result;
    }

    /**
     * Update user record
     *
     * @param User $user
     * @return bool
     * @throws DatabaseException
     */
    public function updateUser(User $user) : bool
    {
        if ($result =  (new UserDataStore($this->container))->updateUser($user)) {
            $this->cache->setUserCache($user);
        }
        return $result;
    }

    /**
     * Get User Record based on user id
     *
     * @param int $id
     * @return null|User
     * @throws DatabaseException
     */
    public function getUserById(int $id) : ?User
    {
        $user = $this->cache->getUserCacheById($id);
        if (empty($user) && (!empty($user = (new UserDataStore($this->container))->getUserById($id)))){
                $this->cache->setUserCache($user);
        }
        return $user;
    }

    /**
     * Query Statement to return a user from email
     *
     * @param $email
     * @return null|User
     * @throws DatabaseException
     */
    public function getUserByEmail(string $email) : ?User
    {
        $user = $this->cache->getUserCacheByEmail($email);
        if (empty($user) && (!empty($user = (new UserDataStore($this->container))->getUserByEmail($email)))){
            $this->cache->setUserCache($user);
        }
        return $user;
    }

    /**
     * Query Statement to return a user from phone
     *
     * @param $phone
     * @return null|User
     * @throws DatabaseException
     */
    public function getUserByPhone(string $phone) : ?User
    {
        $user = $this->cache->getUserCacheByPhone($phone);
        if (empty($user) && (!empty($user = (new UserDataStore($this->container))->getUserByPhone($phone)))){
            $this->cache->setUserCache($user);
        }
        return $user;
    }

    /**
     * Query Statement to return a user from phone or email
     *
     * @param string $emailOrPhone
     * @return null|User
     * @throws DatabaseException
     */
    public function getUserByEmailOrPhone(string $emailOrPhone) : ?User
    {
        $user = $this->cache->getUserCacheByEmailOrPhone($emailOrPhone);
        if (filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL)) {
            if (empty($user) && (!empty($user = (new UserDataStore($this->container))->getUserByEmail($emailOrPhone)))) {
                $this->cache->setUserCache($user);
            }
        } else {
            if (empty($user) && (!empty($user = (new UserDataStore($this->container))->getUserByPhone($emailOrPhone)))) {
                $this->cache->setUserCache($user);
            }
        }
        return $user;
    }

    /**
     * Delete user
     *
     * @param int $id
     * @return bool
     * @throws DatabaseException
     */
    public function deleteUser(int $id) : bool
    {
        $this->cache->deleteUserCache($id);
        /*
        $user = $this->getUserById($id);
        if (!empty($user) && $this->cache->deleteUserCache($id)){
            return (new UserDataStore($this->container))->deleteUser($id);
        }
        */
        return true;
    }

    /**
     * get total invitations sent by a user
     *
     * @param int $userId
     * @return int     *
     */
    public function getInvitationSentCount(int $userId) : int
    {
        return $this->cache->getInvitationSentCountCache($userId);
        //return (new UserDataStore($this->container))->getInvitationSentCount();
    }


    /**
     * Search users
     *
     * @param string|null $keyword
     * @param int|null $accountStatus
     * @param int|null $approvalStatus
     * @param int|null $offset
     * @param int|null $rowCount
     * @param string|null $sortBy
     * @param string|null $sortDirection
     * @return iterable
     * @throws DatabaseException
     */
    public function searchUsers(?string $keyword, ?int $accountStatus, ?int $approvalStatus, ?int $offset, ?int $rowCount, ?string $sortBy = 'last_active_on', ?string $sortDirection = 'desc', ?int $userType) : iterable
    {
        return (new UserDataStore($this->container))->searchUsers($keyword, $accountStatus, $approvalStatus,$offset, $rowCount, $sortBy, $sortDirection, $userType);
    }

    /**
     * @param string|null $keyword
     * @param int|null $accountStatus
     * @param int|null $approvalStatus
     * @return int
     * @throws DatabaseException
     */
    public function countUsers(?string $keyword, ?int $accountStatus, ?int $approvalStatus, ?int $userType) : int
    {
        return (new UserDataStore($this->container))->countUsers($keyword, $accountStatus, $approvalStatus, $userType);
    }

    /**
     * @param int $seconds
     * @return array
     * @throws DatabaseException
     */
    public function getNewUsers(int $seconds): array
    {
        /** @var UserDataStore $dataStore */
        $dataStore = $this->container->get(UserDataStore::class);
        return $dataStore->getNewUsers($seconds);
    }

    #endregion

    #region [User Sessions Section]
    /**
     * Statement to create user session
     *
     * @param UserSession $session
     * @return bool
     */
    public function createUserSession(UserSession $session) : bool
    {
        return (new UserSessionDataStore($this->container))->createUserSession($session);
    }

    /**
     * Statement to delete all users sessions that belong to a user
     *
     * @param int $userId
     * @return int
     */
    public function deleteIdentitySessions(int $userId) : int
    {
        return (new UserSessionDataStore($this->container))->deleteUserSessions($userId);
    }

    /**
     * get all user sessions
     *
     * @param int $userId
     * @return int
     */
    public function getIdentitySessions(int $userId) : ?array
    {
        return (new UserSessionDataStore($this->container))->getUserSessions($userId);
    }
    #endregion

    #region [Channel/Channel Users/Channel Path Section]
    /**
     * create channel
     *
     * @param Channel $channel
     * @return bool
     * @throws DatabaseException
     */
    public function createChannel(Channel $channel) : bool
    {
        if ($result =  (new ChannelDataStore($this->container))->createChannel($channel)) {
            $this->cache->setChannelCache($channel);
        }
        return $result;
    }

    /**
     * update channel
     *
     * @param Channel $channel
     * @return bool
     * @throws DatabaseException
     */
    public function updateChannel(Channel $channel) : bool
    {
        if ($result =  (new ChannelDataStore($this->container))->updateChannel($channel)) {
            $this->cache->setChannelCache($channel);
        }
        return $result;
    }

    /**
     * delete channel
     *
     * @param int $id
     * @return bool
     * @throws DatabaseException
     */
    public function deleteChannel(int $id) : bool
    {
        $this->cache->deleteChannelCache($id);
        if (!(new ChannelDataStore($this->container))->deleteChannel($id)) {
             return false;
        }
        $this->deleteAssets($id, Asset::CONTEXT_TYPE_CHANNEL);
        $this->deleteAlerts($id, Alert::CONTEXT_TYPE_CHANNEL);
        $this->deletePolicies($id, Policy::CONTEXT_TYPE_CHANNEL);
        return true;
    }

    /**
     * get channel by channel id
     *
     * @param int $id
     * @return null|Channel
     * @throws DatabaseException
     */
    public function getChannelById(int $id) : ?Channel
    {
        $channel = $this->cache->getChannelByIdCache($id);
        if (empty($channel) && (!empty($channel = (new ChannelDataStore($this->container))->getChannelById($id)))){
            $this->cache->setChannelCache($channel);
        }
        return $channel;
    }

    /**
     * get channels by team id
     *
     * @param int $team_id
     * @return iterable
     * @throws DatabaseException
     */
    public function getChannelsByTeamId(int $team_id) : iterable
    {
        return (new ChannelDataStore($this->container))->getChannelsByTeamId($team_id);
    }

    /**
     * @param int $seconds
     * @return array
     * @throws DatabaseException
     */
    public function getNewChannels(int $seconds): array
    {
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getNewChannels($seconds);
    }


    /**
     * add channel user
     *
     * @param ChannelUser $channelUser
     * @return bool
     * @throws DatabaseException
     */
    public function addChannelUser(ChannelUser $channelUser): bool
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->addChannelUser($channelUser);
    }

    /**
     * drop channel user
     *
     * @param int $channel_id
     * @param int $user_id
     * @return int
     * @throws DatabaseException
     */
    public function dropChannelUser(int $channel_id, int $user_id) : int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->dropChannelUser($channel_id, $user_id);
    }

    /**
     * drop all users from channel
     *
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function dropAllUsersFromChannel(int $channelId): int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->dropAllUsersFromChannel($channelId);
    }

    /**
     * Get Users For Channel
     *
     * @param int $channelId
     * @return iterable
     * @throws DatabaseException
     */
    public function getUsersForChannel(int $channelId) : iterable
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getUsersForChannel($channelId);
    }

    /**
     * Get Channels for User
     *
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getChannelsForUser(int $userId) : iterable
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getChannelsForUser($userId);
    }

    public function getChannelsCountForUser(int $userId): int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getChannelsCountForUser($userId);
    }

    public function getMessageSentCountForUser(int $userId): int
    {
        /** @var MessageDataStore $dataStore */
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getMessageSentCountForUser($userId);
    }

    public function getChannelsOwnedCountForUser(int $userId): int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getChannelsOwnedCountForUser($userId);
    }

    /**
     * Get the channel user information
     *
     * @param int $channelId
     * @param int $userId
     * @return ChannelUser|null
     * @throws DatabaseException
     */
    public function getUserChannel(int $channelId, int $userId) : ?ChannelUser
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getUserChannel($channelId, $userId);
    }

    /**
     * get user channels by user Id
     *
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getUserChannelsByUserId(int $userId) : iterable
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getUserChannelsByUserId($userId);
    }

    /**
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function getChannelUserCount(int $channelId) : int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getChannelUserCount($channelId);
    }

    /**
     * @param int $channelId
     * @return ChannelUser[]
     * @throws DatabaseException
     */
    public function getChannelUsersByChannelId(int $channelId): array
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getChannelUsersByChannelId($channelId);
    }


    /**
     * Set Favorite Channel
     *
     * @param int $userId
     * @param int $channelId
     * @param bool $isFavorite
     * @return bool
     * @throws DatabaseException
     */
    public function setFavoriteChannel(int $userId, int $channelId, bool $isFavorite)
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->setFavoriteChannel($userId, $channelId, $isFavorite);
    }

    /**
     * Query Statement to create channel path
     *
     * @param ChannelPath $channelPath
     * @return bool
     * @throws DatabaseException
     */
    public function createChannelPath(ChannelPath $channelPath) : bool
    {
        return (new ChannelPathDataStore($this->container))->createChannelPath($channelPath);
    }

    /**
     * update channel Path
     *
     * @param ChannelPath $channelPath
     * @return bool
     * @throws DatabaseException
     */
    public function updateChannelPath(ChannelPath $channelPath) : bool
    {
        return (new ChannelPathDataStore($this->container))->updateChannelPath($channelPath);
    }

    /**
     * select channel paths
     *
     * @param int $channelId
     * @return array
     * @throws DatabaseException
     */
    public function getChannelPathsByChannelId(int $channelId): array
    {
        return (new ChannelPathDataStore($this->container))->getChannelPathsByChannelId($channelId);
    }

    /**
     * delete channel paths
     *
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function deleteChannelPathsByChannelId(int $channelId) : int
    {
        return (new ChannelPathDataStore($this->container))->deleteChannelPathsByChannelId($channelId);
    }

    /**
     * get channel path by Id
     *
     * @param int $channelPathId
     * @return ChannelPath|null
     * @throws DatabaseException
     */
    public function getChannelPathById(int $channelPathId) :?ChannelPath
    {
        return (new ChannelPathDataStore($this->container))->getChannelPathById($channelPathId);
    }

    /**
     * delete channel path by Id
     *
     * @param int $channelPathId
     * @return int
     * @throws DatabaseException
     */
    public function deleteChannelPathById(int $channelPathId) : int
    {
        return (new ChannelPathDataStore($this->container))->deleteChannelPathById($channelPathId);
    }

    /**
     * Get Channel Paths by value
     *
     * @param string $pathValue
     * @return iterable
     * @throws DatabaseException
     */
    public function getChannelPathsByValue(string $pathValue) : iterable
    {
        return (new ChannelPathDataStore($this->container))->getChannelPathsByValue($pathValue);
    }

    /**
     * update channel user
     *
     * @param ChannelUser $channelUser
     * @return bool
     * @throws DatabaseException
     */
    public function updateChannelUser(ChannelUser $channelUser): bool
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->updateChannelUser($channelUser);
    }

    /**
     * Get Channel by channel email
     *
     * @param string $email
     * @return Channel|null
     * @throws DatabaseException
     */
    public function getChannelByEmail(string $email): ?Channel
    {
        return (new ChannelDataStore($this->container))->getChannelByEmail($email);
    }

    /**
     * Get Channel By Name and Team Id
     *
     * @param int $teamId
     * @param string $channelName
     * @return Channel|null
     * @throws DatabaseException
     */
    public function getChannelByName(int $teamId, string $channelName) : ?Channel
    {
        return (new ChannelDataStore($this->container))->getChannelByName($teamId, $channelName);
    }

    /**
     * Channel Search
     *
     * @param string $keyword
     * @param int $channelStatus
     * @param int|null $offset
     * @param int|null $rowCount
     * @param bool $onlyChannelInfo
     * @param string $sortBy
     * @param string $sortDirection
     * @return iterable
     * @throws DatabaseException
     */
    public function searchChannels(?string $keyword, ?int $channelStatus,?int $offset, ?int $rowCount, bool $onlyChannelInfo = false, string $sortBy = 'created_on', string $sortDirection = 'desc'): iterable
    {
        return (new ChannelDataStore($this->container))->search($keyword, $channelStatus, $offset, $rowCount, $onlyChannelInfo, $sortBy, $sortDirection);
    }

    /**
     * @param string|null $keyword
     * @param int|null $channelStatus
     * @return int
     * @throws DatabaseException
     */
    public function countChannels(?string $keyword, ?int $channelStatus, bool $onlyChannelInfo = false): int
    {
        return (new ChannelDataStore($this->container))->searchCount($keyword, $channelStatus, $onlyChannelInfo);
    }

    /**
     * @return iterable
     * @throws DatabaseException
     */
    public function getOpenChannelsExpired() : iterable
    {
        return (new ChannelDataStore($this->container))->getOpenChannelsExpired();
    }
    #endregion

    #region [Team/Team User Section]
    /**
     * Query to create a team
     *
     * @param Team $team
     * @throws DatabaseException
     */
    public function createTeam(Team $team): void
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        $dataStore->createTeam($team);
        $this->cache->setTeamCache($team);
    }

    /**
     * Query to update a team
     *
     * @param Team $team
     * @return bool
     * @throws DatabaseException
     */
    public function updateTeam(Team $team): bool
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        if ($result = $dataStore->updateTeam($team)) {
            $this->cache->setTeamCache($team);
        }
        return $result;
    }

    /**
     * Query to delete a team
     *
     * @param int $teamId
     * @return int
     * @throws DatabaseException
     */
    public function deleteTeam(int $teamId): int
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->deleteTeam($teamId);
    }

    /**
     * get team by team id
     *
     * @param int $teamId
     * @return Team|null
     * @throws DatabaseException
     */
    public function getTeamByTeamId(int $teamId) : ?Team
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->getTeamByTeamId($teamId);
    }

    /**
     * get teams that users belong to
     *
     * @param int $userId
     * @return TeamUser[]
     * @throws DatabaseException
     */
    public function getTeamUsersForUser(int $userId): array
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->getTeamUsersForUser($userId);
    }

    /**
     * add user to team
     *
     * @param TeamUser $teamUser
     * @return bool
     * @throws DatabaseException
     */
    public function addTeamUser(TeamUser $teamUser): bool
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->addTeamUser($teamUser);
    }

    /**
     * Query to delete a user in a team
     *
     * @param int $teamId
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    public function dropTeamUser(int $teamId, int $userId): int
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->dropTeamUser($teamId, $userId);
    }

    /**
     * Query to remove all users in a team
     *
     * @param int $team_id
     * @return int
     * @throws DatabaseException
     */
    public function dropAllUsersFromTeam(int $team_id): int
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->dropAllUsersFromTeam($team_id);
    }

    /**
     * Get Default Team for User
     *
     * @param int $user_id
     * @return Team|null
     * @throws DatabaseException
     */
    public function getDefaultTeamForUser(int $user_id) : ?Team
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->getDefaultTeamForUser($user_id);
    }

    /**
     * get Team User
     *
     * @param int $userId
     * @param int $teamId
     * @return TeamUser|null
     * @throws DatabaseException
     */
    public function getTeamUser(int $userId, int $teamId) : ?TeamUser
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->getTeamUser($userId, $teamId);
    }

    /**
     * @param int $teamId
     * @return TeamUser[]
     * @throws DatabaseException
     */
    public function getTeamUsers(int $teamId): array
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->getTeamUsers( $teamId);
    }

    /**
     * @param int $teamId
     * @return User[]
     */
    public function getTeamMembers(int $teamId): array
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->getTeamMembers($teamId);
    }

    /**
     * Team Search
     *
     * @param string|null $keyword
     * @param int|null $teamType
     * @param int|null $offset
     * @param int|null $rowCount
     * @return iterable
     * @throws DatabaseException
     */
    public function searchTeams(?string $keyword, ?int $teamType, ?int $offset, ?int $rowCount): iterable
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->search( $keyword, $teamType, $offset, $rowCount);
    }

    /**
     * Team Search
     *
     * @param string|null $keyword
     * @param int|null $teamType
     * @return int
     * @throws DatabaseException
     */
    public function searchTeamCount(?string $keyword, ?int $teamType): int
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->searchCount( $keyword, $teamType);
    }
    #endregion

    #region [Message and Timeline Section]
    /**
     * create message
     *
     * @param Message $message
     * @return bool
     * @throws DatabaseException
     */
    public function createMessage(Message $message) : bool
    {
        return $this->container->get(MessageDataStore::class)->createMessage($message);
    }

    /**
     * Update message
     *
     * @param Message $message
     * @return bool
     * @throws DatabaseException
     */
    public function updateMessage(Message $message) : bool
    {
        return $this->container->get(MessageDataStore::class)->updateMessage($message);
    }

    /**
     * Delete message
     *
     * @param $messageId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteMessage(int $messageId) : bool
    {
        return $this->container->get(MessageDataStore::class)->deleteMessage($messageId);
    }

    /**
     * Get messages by channel Id
     *
     * @param int $channelId
     * @param int|null $limit
     * @param int|null $offsetMessageId
     * @param string|null $before
     * @param bool $asc
     * @return iterable
     * @throws DatabaseException
     */
    public function getMessagesByChannelId(int $channelId, ?int $limit, ?int $offsetMessageId, ?string $before = null, bool $asc=false) : iterable
    {
        return $this->container->get(MessageDataStore::class)->getMessagesByChannelId($channelId, $limit, $offsetMessageId, $before, $asc);
    }

    /**
     * @param int $channelId
     * @return int
     * @throws DatabaseException
     */
    public function getFirstChannelMessage(int $channelId) : int
    {
        return $this->container->get(MessageDataStore::class)->getFirstChannelMessageId($channelId);
    }



    public function searchMessages(string $search): iterable
    {
        return $this->container->get(MessageDataStore::class)->searchMessages($search);
    }

    /**
     * Get next message id for cursor pagination
     *
     * @param int $channelId
     * @param int $messageId
     * @return int
     * @throws DatabaseException
     */
    public function getNextMessageId(int $channelId, int $messageId) : int
    {
        return $this->container->get(MessageDataStore::class)->getNextMessageId($channelId, $messageId);
    }

    /**
     * get message by message id
     *
     * @param $messageId
     * @return Message|null
     * @throws DatabaseException
     */
    public function getMessageById(int $messageId) : ?Message
    {
        return $this->container->get(MessageDataStore::class)->getMessageById($messageId);
    }

    /**
     * Add time line
     *
     * @param Timeline $timeline
     * @return bool
     * @throws DatabaseException
     */
    public function addTimeline(Timeline $timeline): bool
    {
        return (new TimelineDataStore($this->container))->addTimeline($timeline);
    }

    /**
     * get the total unread message by a user in the channel
     *
     * @param int $channelId
     * @param int $userId
     * @param int|null $readWatermark
     * @return int
     * @throws DatabaseException
     */
    public function getUnReadMessageCount(int $channelId, int $userId, ?int $readWatermark = null): int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getUnReadMessageCount($channelId, $userId, $readWatermark);
    }

    /**
     * Get the last message written on the channel timeline
     *
     * @param int $channelId
     * @return Message|null
     * @throws DatabaseException
     */
    public function getTimelineLastWrittenMessage(int $channelId) : ?Message
    {
        return (new TimelineDataStore($this->container))->getTimelineLastWrittenMessage($channelId);
    }

    public function getMessagesWithUserEmotions(int $userId) : iterable
    {
        return $this->container->get(MessageDataStore::class)->getMessagesWithUserEmotions($userId);
    }

    public function updateUserDisplayNameInMessages($userId, $displayName) : bool
    {
        return $this->container->get(MessageDataStore::class)->updateUserDisplayNameInMessages($userId, $displayName);
    }

    #endregion

    #region [Action Section]
    /**
     * create action
     *
     * @param Action $action
     * @return bool
     * @throws DatabaseException
     */
    public function createAction(Action $action) : bool
    {
        return (new ActionDataStore($this->container))->createAction($action);
    }

    /**
     * update action
     *
     * @param Action $action
     * @return bool
     * @throws DatabaseException
     */
    public function updateAction(Action $action) : bool
    {
        return (new ActionDataStore($this->container))->updateAction($action);
    }

    /**
     * delete action
     *
     * @param $actionId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteAction(int $actionId) : bool
    {
        return (new ActionDataStore($this->container))->deleteAction($actionId);
    }

    /**
     * get action by actionid
     *
     * @param $actionId
     * @return Action|null
     * @throws DatabaseException
     */
    public function getActionById(int $actionId) : ?Action
    {
        return (new ActionDataStore($this->container))->getActionById($actionId);
    }

    /**
     * get actions for a channel
     *
     * @param $channelId
     * @return iterable
     * @throws DatabaseException
     */
    public function getActionsForChannel(int $channelId) : iterable
    {
        return (new ActionDataStore($this->container))->getActionsForChannel($channelId);
    }

    /**
     * add action to user
     *
     * @param UserAction $userAction
     * @return bool
     * @throws DatabaseException
     */
    public function addUserAction(UserAction $userAction) : bool
    {
        return (new ActionDataStore($this->container))->addUserAction($userAction);
    }

    /**
     * Delete action from user
     *
     * @param int $actionId
     * @param $userId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteUserAction(int $actionId, $userId) : bool
    {
        return (new ActionDataStore($this->container))->deleteUserAction($actionId,$userId);
    }

    /**
     * @param int $actionId
     * @return Action|null
     * @throws DatabaseException
     */
    public function getActionWithUsers(int $actionId) : ?Action
    {
        return (new ActionDataStore($this->container))->getActionWithUsers($actionId);
    }


    /**
     * @param int|null $channelId
     * @param int|null $userId
     * @param int|null $parentId
     * @param bool $ignoreParentId
     * @return iterable
     * @throws DatabaseException
     */
    public function getActions(?int $channelId, ?int $userId, ?int $parentId = null, bool $ignoreParentId = false) : iterable
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        return $dataStore->getActions($channelId, $userId, $parentId, $ignoreParentId);
    }

    /**
     * @param int $requestedById
     * @param string $sortBy
     * @param bool $sortDesc
     * @param int|null $channelId
     * @param int|null $userId
     * @param int|null $status
     * @param array|null $searchFilter
     * @param Action|null $cursor
     * @param int|null $limitAfter
     * @param int|null $limitBefore
     * @return Action[]
     */
    public function getActionsPaginated(int $requestedById,
                                        string $sortBy,
                                        bool $sortDesc,
                                        ?int $channelId = null,
                                        ?int $userId = null,
                                        ?int $status = null,
                                        ?array $searchFilter = null,
                                        ?Action $cursor = null,
                                        ?int $limitAfter = null,
                                        ?int $limitBefore = null): array
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        return $dataStore->getActionsPaginated($requestedById, $sortBy, $sortDesc, $channelId, $userId, $status, $searchFilter, $cursor, $limitAfter, $limitBefore);
    }

    /**
     * @param int|null $channelId
     * @return int
     * @throws DatabaseException
     */
    public function getActionCount(?int $channelId= null) : int
    {
        return (new ActionDataStore($this->container))->getAllActionCount($channelId);
    }

    /**
     * @param string $from
     * @param string $to
     * @return iterable
     */
    public function getRemindersDueBetween(string $from, string $to) : iterable
    {
        return (new ActionDataStore($this->container))->getRemindersDueBetween($from, $to);
    }


    /**
     * @param int $actionId
     * @return int
     * @throws DatabaseException
     */
    public function deleteUserActions(int $actionId) : int
    {
        return (new ActionDataStore($this->container))->deleteUserActions($actionId);
    }
    #endregion

    #region "External Identity"

    /**
     * create external identity
     *
     * @param ExternalIdentity $identity
     * @return bool
     * @throws DatabaseException
     */
    public function createExternalIdentity(ExternalIdentity $identity): bool
    {
        /** @var ExternalIdentityDataStore $dataProvider */
        $dataProvider = $this->container->get(ExternalIdentityDataStore::class);
        return $dataProvider->createExternalIdentity($identity);
    }

    public function updateExternalIdentity(ExternalIdentity $identity): void
    {
        /** @var ExternalIdentityDataStore $dataProvider */
        $dataProvider = $this->container->get(ExternalIdentityDataStore::class);
        $dataProvider->updateExternalIdentity($identity);
    }

    /**
     * get external identity
     *
     * @param int $userId
     * @param string $email
     * @param int $provider
     * @return ExternalIdentity|null
     * @throws DatabaseException
     */
    public function getExternalIdentity(int $userId, string $email, int $provider): ?ExternalIdentity
    {
        /** @var ExternalIdentityDataStore $dataStore */
        $dataStore = $this->container->get(ExternalIdentityDataStore::class);
        return $dataStore->getExternalIdentity($userId, $email, $provider);
    }

    public function getExternalIdentityByEmail(string $email, int $provider): ?ExternalIdentity
    {
        /** @var ExternalIdentityDataStore $dataStore */
        $dataStore = $this->container->get(ExternalIdentityDataStore::class);
        return $dataStore->getExternalIdentityByEmail($email, $provider);
    }

    public function getExternalIdentityById(string $externalIdentifier, int $provider): ?ExternalIdentity
    {
        /** @var ExternalIdentityDataStore $dataStore */
        $dataStore = $this->container->get(ExternalIdentityDataStore::class);
        return $dataStore->getExternalIdentityById($externalIdentifier, $provider);
    }

    /**
     * get external identities for a user
     *
     * @param int $userId
     * @return iterable
     * @throws DatabaseException
     */
    public function getExternalIdentities(int $userId) : iterable
    {
        return (new ExternalIdentityDataStore($this->container))->getExternalIdentities($userId);
    }

    /**
     * get total external identities for a user
     *
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    public function getExternalIdentityCount(int $userId) : int
    {
        return (new ExternalIdentityDataStore($this->container))->getExternalIdentityCount($userId);
    }
    #endregion

    #region "User Codes"
    public function createUserCode(UserCode $code) : bool
    {
        return (new UserCodeDataStore($this->container))->createUserCode($code);
    }

    /**
     * Query to update UserCode
     *
     * @param UserCode $code
     * @return bool
     * @throws DatabaseException
     */
    public function updateUserCode(UserCode $code) : bool
    {
        return (new UserCodeDataStore($this->container))->updateUserCode($code);
    }

    /**
     * Query to get the user code
     *
     * @param int $userId
     * @param int $codeType
     * @return UserCode|null
     * @throws DatabaseException
     */
    public function getUserCode(int $userId, int $codeType) : ?UserCode
    {
        return (new UserCodeDataStore($this->container))->getUserCode($userId, $codeType);
    }

    /**
     * @param int $userId
     * @param int $codeType
     * @return bool
     * @throws DatabaseException
     */
    public function deleteUserCode(int $userId, int $codeType) : bool
    {
        return (new UserCodeDataStore($this->container))->deleteUserCode($userId, $codeType);
    }
    #endregion

    #region "key store"

    /**
     * @param KeyStore $keystore
     * @return bool
     * @throws DatabaseException
     */
    public function createKeyStore(KeyStore $keystore) : bool
    {
        return (new KeyStoreDataStore($this->container))->createKeyStore($keystore);
    }

    /**
     * delete key store
     *
     * @param string $key
     * @return bool
     * @throws DatabaseException
     */
    public function deleteKeyStore(string $key) : bool
    {
        return (new KeyStoreDataStore($this->container))->deleteKeyStore($key);
    }

    /**
     * update key store
     *
     * @param KeyStore $keystore
     * @return bool
     * @throws DatabaseException
     */
    public function updateKeyStore(KeyStore $keystore) : bool
    {
        return (new KeyStoreDataStore($this->container))->updateKeyStore($keystore);
    }

    /**
     * get key store
     *
     * @param string $key
     * @return KeyStore|null
     * @throws DatabaseException
     */
    public function getKeyStore(string $key) : ?KeyStore
    {
        return (new KeyStoreDataStore($this->container))->getKeyStore($key);
    }
    #endregion

    /**
     * @param string $token
     * @return Notification|null
     */
    public function getNotificationByToken(string $token): ?Notification
    {
        return $this->container->get(NotificationDataStore::class)->getNotificationByToken($token);
    }


    /**
     * @param Asset $asset
     * @return bool
     * @throws DatabaseException
     */
    public function createAsset(Asset $asset): bool
    {
        return (new AssetDataStore($this->container))->createAsset($asset);
    }


    /**
     * Update Asset
     *
     * @param Asset $asset
     * @return bool
     * @throws DatabaseException
     */
    public function updateAsset(Asset $asset) : bool
    {
        return (new AssetDataStore($this->container))->updateAsset($asset);
    }

    /**
     * Delete Asset
     *
     * @param int $assetId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteAsset(int $assetId): bool
    {
        return (new AssetDataStore($this->container))->deleteAsset($assetId);
    }


    /**
     * Get Asset
     *
     * @param int $contextId
     * @param int $contextType
     * @param int $assetType
     * @param int $attribute
     * @return Asset
     * @throws DatabaseException
     */
    public function getAsset(int $contextId, int $contextType, int $assetType, int $attribute): ?Asset
    {
        return (new AssetDataStore($this->container))->getAsset($contextId, $contextType, $assetType, $attribute);
    }

    public function hasAsset(int $contextId, int $contextType, int $assetType): bool
    {
        return (new AssetDataStore($this->container))->hasAsset($contextId, $contextType, $assetType);
    }



    /**
     * Get Asset By Asset Id
     *
     * @param int $assetId
     * @return Asset
     * @throws DatabaseException
     */
    public function getAssetById(int $assetId): ?Asset
    {
        return (new AssetDataStore($this->container))->getAssetById($assetId);
    }

    /**
     * @param int $contextId
     * @param int $contextType
     * @return int
     * @throws DatabaseException
     */
    public function deleteAssets(int $contextId, int $contextType) : int
    {
        return (new AssetDataStore($this->container))->deleteAssets($contextId, $contextType);
    }

    /**
     * @return array
     * @throws DatabaseException
     */
    public function getDashboardStats() : array
    {
        $stats = [];
        $stats['users'] = (new UserDataStore($this->container))->getAllUserCount();
        $stats['channels'] = (new ChannelDataStore($this->container))->getAllChannelCount();
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        $stats['teams'] = $dataStore->getAllTeamCount();
        $stats['actions'] = (new ActionDataStore($this->container))->getAllActionCount();
        $stats['messages'] = $this->container->get(MessageDataStore::class)->getAllMessageCount();
        $stats['emails_sent'] = $this->container->get(NotificationDataStore::class)->getEmailsCount(24*60);
        $activeUsers = (new UserDataStore($this->container))->getActiveUserCount();
        $stats['engaged_users'] = (new UserDataStore($this->container))->getEngagedUserCount() ." (". $activeUsers.")";


        $interval = 60 * 60 * 24; // last 24 hours
        $newAccounts = count($this->getNewUsers($interval));
        $newAccountsWithChannels = count($this->getNewUsersWithChannels($interval));
        $newChannels = count($this->getNewChannels($interval));
        $newMessagesCount = $this->countNewMessages($interval);

        $stats['users_24h'] = $newAccounts;
        $stats['users_with_channels_24h'] = $newAccountsWithChannels;
        $stats['channels_24h'] = $newChannels;
        $stats['messages_24h'] = $newMessagesCount;


        return $stats;
    }


    #region "alerts"

    /**
     * Insert or update Alert
     *
     * @param Alert $alert
     * @return bool
     * @throws DatabaseException
     */
    public function upsertAlert(Alert $alert) : bool
    {
        return (new AlertDataStore($this->container))->upsertAlert($alert);
    }


    /**
     * Get Alerts
     *
     * @param int $userId
     * @return array
     * @throws DatabaseException
     */
    public function getAlerts(int $userId) : iterable
    {
        return (new AlertDataStore($this->container))->getAlerts($userId);
    }

    public function deleteAlerts(int $contextId, int $contextType) : int
    {
        return (new AlertDataStore($this->container))->deleteAlerts($contextId, $contextType);
    }

    /**
     * @param int $alertId
     * @return Alert|null
     * @throws DatabaseException
     */
    public function getAlertById(int $alertId) : ?Alert
    {
        return (new AlertDataStore($this->container))->getAlertById($alertId);
    }

    /**
     * @param int|null $userId
     * @param int $contextId
     * @param int $contextType
     * @param string $alertText
     * @return Alert|null
     * @throws DatabaseException
     */
    public function getAlert(?int $userId, int $contextId, int $contextType, string $alertText) : ?Alert
    {
        return (new AlertDataStore($this->container))->getAlert($userId, $contextId, $contextType, $alertText);
    }

    #end region

    #region phone

    /**
     * @param Phone $phone
     * @return bool
     * @throws DatabaseException
     */
    public function createPhone(Phone $phone) : bool
    {
        return (new PhoneDataStore($this->container))->createPhone($phone);
    }

    /**
     * @param Phone $phone
     * @return bool
     * @throws DatabaseException
     */
    public function updatePhone(Phone $phone)
    {
        return (new PhoneDataStore($this->container))->updatePhone($phone);
    }

    /**
     * @param string $number
     * @return Phone|null
     * @throws DatabaseException
     */
    public function getPhone(string $number) : ?Phone
    {
        return (new PhoneDataStore($this->container))->getPhone($number);
    }
    #end region

    #region policies

    /**
     * Create Policy
     *
     * @param Policy $policy
     * @return bool
     * @throws DatabaseException
     */
    public function createPolicy(Policy $policy) : bool
    {
        return (new PolicyDataStore($this->container))->createPolicy($policy);
    }

    /**
     * Update Policy
     *
     * @param Policy $policy
     * @return bool
     * @throws DatabaseException
     */
    public function updatePolicy(Policy $policy) : bool
    {
        return (new PolicyDataStore($this->container))->updatePolicy($policy);
    }

    /**
     * Delete Policy
     *
     * @param Policy $policy
     * @return bool
     * @throws DatabaseException
     */
    public function deletePolicy(Policy $policy) : bool
    {
        return (new PolicyDataStore($this->container))->deletePolicy($policy);
    }

    /**
     * Get Policy
     *
     * @param int $contextId
     * @param int $contextType
     * @param string $policyName
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getPolicy(int $contextId, int $contextType, string $policyName) : ?Policy
    {
        return (new PolicyDataStore($this->container))->getPolicy($contextId, $contextType, $policyName);
    }

    /**
     * Get Global Policy for the policy Name
     *
     * @param string $policyName
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getPolicyGlobal(string $policyName) : ?Policy
    {
        return $this->getPolicy(0, Policy::CONTEXT_TYPE_GLOBAL, $policyName);
    }

    /**
     * Get team policy for the policy name
     *
     * @param int $teamId
     * @param string $policyName
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getPolicyTeam(int $teamId, string $policyName) : ?Policy
    {
        return $this->getPolicy($teamId, Policy::CONTEXT_TYPE_TEAM, $policyName);
    }

    /**
     * Get channel policy for policy name
     *
     * @param int $channelId
     * @param string $policyName
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getPolicyChannel(int $channelId, string $policyName) : ?Policy
    {
        return $this->getPolicy($channelId, Policy::CONTEXT_TYPE_CHANNEL, $policyName);
    }

    /**
     * Get user policy for policy name
     *
     * @param int $userId
     * @param string $policyName
     * @return Policy|null
     * @throws DatabaseException
     */
    public function getPolicyUser(int $userId, string $policyName) : ?Policy
    {
        return $this->getPolicy($userId, Policy::CONTEXT_TYPE_USER, $policyName);
    }

    public function deletePolicies(int $contextId, int $contextType) : int
    {
        return (new PolicyDataStore($this->container))->deletePolicies($contextId, $contextType);
    }

    #end region

    /**
     * @param int $userId
     * @return Channel[]
     */
    /*
    public function getReadableChannelsForUser(int $userId): array
    {
        $allChannels = (new ChannelDataStore($this->container))->getAllChannels();
        $channels = [];
        foreach ($allChannels as $channelData)
        {
            $channel = Channel::withDBData($channelData);
            $authContext = new GlobalAuthContext($this, $userId, null, $channel->getId());
            if ($authContext->getChannel()->allowRead()) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }
    */

    /**
     * @param array $messageIds
     * @return Message[]
     * @throws DatabaseException
     */
    public function getMessagesByIds(array $messageIds): array
    {
        $records = $this->container->get(MessageDataStore::class)->getMessagesByIds($messageIds);
        $output = [];
        foreach ($records as $record) {
            $output[] = Message::withDBData($record);
        }
        return $output;
    }

    /**
     * Search the messages digest data for users that never logged to the system
     * @return Generator
     * @throws DatabaseException
     */
    public function getMessageDigestDataForUnregisteredUser(): Generator
    {
        /** @var MessageDataStore $dataStore */
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getMessageDigestDataForPendingSignUpUsers();
    }

    /**
     * @return Generator
     * @throws DatabaseException
     */
    public function getMentionDigestDataForUnregisteredUser(): Generator
    {
        /** @var MessageDataStore $dataStore */
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getMentionDigestDataForPendingSignUpUsers();
    }

    /**
     * @return Generator
     * @throws DatabaseException
     */
    public function getMessageDigestDataForRegisteredUsers(): Generator
    {
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getMessageDigestDataForRegisteredUsers();
    }

    /**
     * @return Generator
     * @throws DatabaseException
     */
    public function getMentionDigestDataForRegisteredUsers(): Generator
    {
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getMentionDigestDataForRegisteredUsers();
    }

    /**
     * @param Notification $notification
     * @param int[] $messageIds
     * @return Notification|null
     */
    public function createNotification(Notification $notification, ?array $messageIds = []): ?Notification
    {
        /** @var NotificationDataStore $dataStore */
        $dataStore = $this->container->get(NotificationDataStore::class);
        return $dataStore->createNotification($notification, $messageIds);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int $configValue
     */
    public function setChannelNotificationsConfig(int $channelId, int $userId, int $configValue)
    {
        /** @var ChannelUserDataStore $ds */
        $ds = $this->container->get(ChannelUserDataStore::class);
        $ds->setNotificationsConfig($channelId, $userId, $configValue);

    }

    /**
     * @param NotificationAbuseReport $notificationAbuseReport
     * @return bool
     * @throws DatabaseException
     */
    public function createNotificationAbuseReport(NotificationAbuseReport $notificationAbuseReport): bool
    {
        /** @var NotificationAbuseReportDataStore $dataStore */
        $dataStore = $this->container->get(NotificationAbuseReportDataStore::class);
        return $dataStore->createReport($notificationAbuseReport);
    }

    /**
     * @param int $start
     * @param int $limit
     * @return Generator|NotificationAbuseReport[]
     */
    public function getNotificationAbuseReports(int $start, int $limit): Generator
    {
        /** @var NotificationAbuseReportDataStore $dataStore */
        $dataStore = $this->container->get(NotificationAbuseReportDataStore::class);
        return $dataStore->getAllReports($start, $limit);
    }

    public function deleteNotificationAbuseReport(int $id): int
    {
        /** @var NotificationAbuseReportDataStore $dataStore */
        $dataStore = $this->container->get(NotificationAbuseReportDataStore::class);
        return $dataStore->deleteReport($id);
    }

    /**
     * @param int $seconds
     * @return int
     * @throws DatabaseException
     */
    public function countNewMessages(int $seconds): int
    {
        /** @var MessageDataStore $dataStore */
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getNewMessageCount($seconds);
    }

    public function getMessageCountByChannelId(int $channelId): int
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getMessageCount($channelId);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return string|null
     * @throws DatabaseException
     */
    public function getMemberEmailForChannel(int $channelId, int $userId): ?string
    {

        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getMemberEmail($channelId, $userId);
    }

    /**
     * @param Channel $channel
     * @param string $tag
     * @return User|null
     * @throws DatabaseException
     */
    public function getUserByEmailTag(Channel $channel, string $tag): ?User
    {
        /** @var UserDataStore $dataStore */
        $dataStore = $this->container->get(UserDataStore::class);
        return $dataStore->getUserByEmailTag($channel->getId(), $tag);
    }

    /**
     * @param string $fcmDeviceId
     * @param int $getId
     * @param string $mobileApp
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function connectFcmDevice(string $fcmDeviceId, int $getId, string $mobileApp): void
    {
        /** @var FCMDevicesDataStore $dataStore */
        $dataStore = $this->container->get(FCMDevicesDataStore::class);
        $dataStore->connectDevice($fcmDeviceId, $getId, $mobileApp);
    }

    public function disconnectFCMDevice(string $fcmDeviceId)
    {
        /** @var FCMDevicesDataStore $dataStore */
        $dataStore = $this->container->get(FCMDevicesDataStore::class);
        $dataStore->disconnectDevice($fcmDeviceId);
    }

    public function connectIosDevice(string $fcmDeviceId, int $getId): void
    {
        /** @var IOSDevicesDataStore $dataStore */
        $dataStore = $this->container->get(IOSDevicesDataStore::class);
        $dataStore->connectDevice($fcmDeviceId, $getId);
    }

    public function disconnectIosDevice(string $fcmDeviceId)
    {
        /** @var IOSDevicesDataStore $dataStore */
        $dataStore = $this->container->get(IOSDevicesDataStore::class);
        $dataStore->disconnectDevice($fcmDeviceId);
    }

    /**
     * @param int $userId
     * @param string|null $clientApp - Can be android or ios, null to don't filter by client app
     * @return array
     */
    public function findFCMDevicesForUser(int $userId, ?string $clientApp = null): array
    {
        /** @var FCMDevicesDataStore $dataStore */
        $dataStore = $this->container->get(FCMDevicesDataStore::class);
        return $dataStore->findDevicesForUser($userId, $clientApp);
    }

    public function findIOSDevicesForUser(int $userId): array
    {
        /** @var IOSDevicesDataStore $dataStore */
        $dataStore = $this->container->get(IOSDevicesDataStore::class);
        return $dataStore->findDevicesForUser($userId);
    }

    /**
     * @param string $url
     * @param string|null $urlHash
     * @param string|null $resourceType
     * @param string|int|null $resourceId
     */
    public function createShortUrl(string $url, ?string $urlHash = null, ?string $resourceType = null, $resourceId = null): void
    {
        /** @var ShortUrlDataStore $dataStore */
        $dataStore = $this->container->get(ShortUrlDataStore::class);
        $dataStore->create($url, $urlHash, $resourceType, (string) $resourceId);
    }

    /**
     * @param string $urlHash
     */
    public function deleteShortUrl(string $urlHash): void
    {
        /** @var ShortUrlDataStore $dataStore */
        $dataStore = $this->container->get(ShortUrlDataStore::class);
        $dataStore->delete($urlHash);
    }

    /**
     * @param string $urlHash
     * @return string|null
     */
    public function translateShortUrl(string $urlHash): ?string
    {
        /** @var ShortUrlDataStore $dataStore */
        $dataStore = $this->container->get(ShortUrlDataStore::class);
        return $dataStore->translate($urlHash);
    }

    public function findPublicHash(string $publicHash): ?PublicHash
    {
        /** @var PublicHashDataStore $dataStore */
        $dataStore = $this->container->get(PublicHashDataStore::class);
        return $dataStore->findPublicHash($publicHash);
    }

    /**
     * @param int $formId
     * @return ContactForm|null
     * @throws DatabaseException
     */
    public function getContactFormById(int $formId): ?ContactForm
    {
        /** @var ContactFormDataStore $dataStore */
        $dataStore = $this->container->get(ContactFormDataStore::class);
        return $dataStore->findById($formId);
    }

    /**
     * @param int $ownerId
     * @return Generator
     * @throws DatabaseException
     */
    public function getContactFormsForUser(int $ownerId): Generator
    {
        /** @var ContactFormDataStore $dataStore */
        $dataStore = $this->container->get(ContactFormDataStore::class);
        return $dataStore->findForUser($ownerId);
    }

    /**
     * @param ContactForm $contactForm
     * @return ContactForm|null
     * @throws DatabaseException
     */
    public function createContactForm(ContactForm $contactForm): ?ContactForm
    {
        /** @var ContactFormDataStore $dataStore */
        $dataStore = $this->container->get(ContactFormDataStore::class);
        return $dataStore->create($contactForm);
    }

    /**
     * @param ContactForm $contactForm
     * @return ContactForm|null
     * @throws DatabaseException
     */
    public function updateContactForm(ContactForm $contactForm): ?ContactForm
    {
        /** @var ContactFormDataStore $dataStore */
        $dataStore = $this->container->get(ContactFormDataStore::class);
        return $dataStore->update($contactForm);
    }

    /**
     * @param int $contactFormId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteContactForm(int $contactFormId): bool
    {
        /** @var ContactFormDataStore $dataStore */
        $dataStore = $this->container->get(ContactFormDataStore::class);
        return $dataStore->delete($contactFormId);
    }

    /**
     * @param int $formId
     * @param string $fillerId
     * @return Channel|null
     * @throws DatabaseException
     */
    public function findChannelByContactForm(int $formId, int $fillerId): ?Channel
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->findByContactForm($formId, $fillerId);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return Notification|null
     */
    public function findInviteNotification(int $channelId, int $userId): ?Notification
    {
        /** @var NotificationDataStore $dataStore */
        $dataStore = $this->container->get(NotificationDataStore::class);
        return $dataStore->findInvite($channelId, $userId);
    }

    /**
     * @param int $lockId
     * @return Lock|null
     * @throws DatabaseException
     */
    public function getLockById(int $lockId): ?Lock
    {
        $dataStore = $this->container->get(LockDataStore::class);
        return $dataStore->getLockForId($lockId);
    }

    /**
     * @param Lock $lock
     * @return bool
     * @throws DatabaseException
     */
    public function createLock(Lock $lock): bool
    {
        $dataStore = $this->container->get(LockDataStore::class);
        return $dataStore->createLock($lock);
    }

    /**
     * @param string $path
     * @return Lock|null
     * @throws DatabaseException
     */
    public function getLock(string $path): ?Lock
    {
        $dataStore = $this->container->get(LockDataStore::class);
        $dataStore->clearExpiredLocks();
        return $dataStore->getLock($path);
    }

    /**
     * @param Lock $lock
     * @return bool
     * @throws DatabaseException
     */
    public function updateLock(Lock $lock): bool
    {
        $dataStore = $this->container->get(LockDataStore::class);
        return $dataStore->updateLock($lock);
    }

    /**
     * @param int $id
     * @throws DatabaseException
     */
    public function deleteLock(int $id): void
    {
        $dataStore = $this->container->get(LockDataStore::class);
        $dataStore->deleteLock($id);
    }

    public function getUnreadMessagesForUser(int $userId): int
    {
        /** @var MessageDataStore $dataStore */
        $dataStore = $this->container->get(MessageDataStore::class);
        return $dataStore->getUnreadMessagesForUser($userId);
    }

    public function actionLastPositionIndex(int $channelId, int $parentId = null): int
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        return $dataStore->findLastPositionIndex($channelId, $parentId);
    }

    public function actionFindPositionIndex(int $channelId, ?int $afterActionId, ?int $parentActionId): int
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        return $dataStore->findPositionIndex($channelId, $afterActionId, $parentActionId);
    }

    public function getActionKids(int $actionId): array
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        return $dataStore->getKids($actionId);
    }

    public function actionMove(int $actionId, ?int $parentActionId, int $positionIndex)
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        return $dataStore->move($actionId, $parentActionId, $positionIndex);
    }

    /**
     * @param int $userId1
     * @param int $userId2
     * @return Channel|null
     * @throws DatabaseException
     */
    public function findOneOnOneChannel(int $userId1, int $userId2): ?Channel
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->findOneOnOne($userId1, $userId2);
    }

    /**
     * @return Generator|int[]
     * @throws DatabaseException
     */
    public function getAllUsersIds(): Generator
    {
        /** @var UserDataStore $dataStore */
        $dataStore = $this->container->get(UserDataStore::class);
        return $dataStore->getAllIds();
    }

    public function getPublicChannelStats(): array
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getPublicChannelStats();
    }

    public function getCube(string $cube): array
    {
        /** @var CubesDataStore $dataStore */
        $dataStore = $this->container->get(CubesDataStore::class);
        return $dataStore->get($cube);
    }

    /**
     * @param string $email
     * @param int $channelId
     * @throws DatabaseException
     */
    public function blacklistUserOnChannel(string $email, int $channelId)
    {
        /** @var ChannelBlacklistDataStore $dataStore */
        $dataStore = $this->container->get(ChannelBlacklistDataStore::class);
        $dataStore->insert($channelId, $email);
    }

    public function removeUserFromChannelBlacklist(string $email, int $channelId)
    {
        /** @var ChannelBlacklistDataStore $dataStore */
        $dataStore = $this->container->get(ChannelBlacklistDataStore::class);
        $dataStore->remove($channelId, $email);
    }

    /**
     * @param int|string $inviteeCode
     * @param int $channelId
     * @return bool
     * @throws DatabaseException
     */
    public function isUserBlacklistedOnChannel($inviteeCode, int $channelId): bool
    {
        /** @var ChannelBlacklistDataStore $dataStore */
        $dataStore = $this->container->get(ChannelBlacklistDataStore::class);
        return $dataStore->isBlacklisted($channelId, $inviteeCode);
    }

    /**
     * @param int $userId
     * @return Team
     * @throws DatabaseException
     */
    public function getSelfTeam(int $userId): ?Team
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->getSelfTeam($userId);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @return bool
     * @throws DatabaseException
     */
    public function isChannelMember(int $channelId, int $userId): bool
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->isChannelMember($channelId, $userId);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int $watermark
     * @return Alert[]
     * @throws DatabaseException
     */
    public function findAlertsBelowWatermark(int $channelId, int $userId, int $watermark): array
    {
        /** @var AlertDataStore $dataStore */
        $dataStore = $this->container->get(AlertDataStore::class);
        return $dataStore->findAllBeforeWatermark($channelId, $userId, $watermark);

    }

    /**
     * @param User $user
     * @param Channel $channel
     * @param bool $mute
     * @return void
     * @throws DatabaseException
     */
    public function setChannelUserMuted(User $user, Channel $channel, bool $mute): void
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        $dataStore->setMuted($user->getId(), $channel->getId(), $mute);
    }

    public function isOauthClientActivatedOnChannel(int $channelId, string $clientId): bool
    {
        /** @var ClientChannelDataStore $dataStore */
        $dataStore = $this->container->get(ClientChannelDataStore::class);
        return $dataStore->isClientActivatedOnChannel($channelId, $clientId);
    }

    /**
     * @param int $channelId
     * @param string $clientId
     */
    public function addOauthClientToChannel(int $channelId, string $clientId): void
    {
        /** @var ClientChannelDataStore $dataStore */
        $dataStore = $this->container->get(ClientChannelDataStore::class);
        $dataStore->addClientToChannel($channelId, $clientId);
    }

    /**
     * @param int $channelId
     * @param string $clientId
     */
    public function removeOauthClientFromChannel(int $channelId, string $clientId): void
    {
        /** @var ClientChannelDataStore $dataStore */
        $dataStore = $this->container->get(ClientChannelDataStore::class);
        $dataStore->removeClientFromChannel($channelId, $clientId);
    }

    /**
     * @param $clientId
     * @return Client
     */
    public function getOauthClientById($clientId): Client
    {
        /** @var ClientDataStore $dataStore */
        $dataStore = $this->container->get(ClientDataStore::class);
        return $dataStore->getClientEntity($clientId);
    }

    public function getTeamByName(string $name): ?Team
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->getByName($name);
    }

    /**
     * @param int $userId1
     * @param int $userId2
     * @return bool
     */
    public function userHasRelation(int $userId1, int $userId2): bool
    {
        /** @var UserDataStore $dataStore */
        $dataStore = $this->container->get(UserDataStore::class);
        return $dataStore->hasRelation($userId1, $userId2);
    }

    /**
     * @param int $channelId
     * @param int $userId
     * @param int|null $messageId - When null, set it to the last message posted to the channel
     * @return int
     * @throws DatabaseException
     */
    public function channelUserUpdateReadWatermark(int $channelId, int $userId, ?int $messageId = null): int
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->updateReadWatermark($channelId, $userId, $messageId);
    }

    public function getReadStatusForChannel(int $channelId): array
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getReadStatus($channelId);
    }

    /**
     * @param ChannelUserPending $channelUserPending
     * @throws DatabaseException
     */
    public function addChannelUserPending(ChannelUserPending $channelUserPending): void
    {
        /** @var ChannelUserPendingDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserPendingDataStore::class);
        $dataStore->addChannelUserPending($channelUserPending);
    }

    /**
     * Return Channel members which the role is greater than the minRoleLevel provided.
     *
     * @param int $channelId
     * @param int $minRoleLevel
     * @return Generator|User[]
     * @throws DatabaseException
     */
    public function getChannelMembersByRoleLevel(int $channelId, int $minRoleLevel): Generator
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getChannelMembersByRoleLevel($channelId, $minRoleLevel);
    }

    /**
     * @param int $channelId
     * @return User[]
     * @throws DatabaseException
     */
    public function getPendingUsersForChannel(int $channelId): array
    {
        /** @var ChannelUserPendingDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserPendingDataStore::class);
        return $dataStore->getPendingUsersForChannel($channelId);
    }

    public function channelUserPendingExists(int $channelId, int $userId): bool
    {
        /** @var ChannelUserPendingDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserPendingDataStore::class);
        return $dataStore->exists($channelId, $userId);
    }

    public function removeChannelUserPending(int $channelId, int $userId): void
    {
        /** @var ChannelUserPendingDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserPendingDataStore::class);
        $dataStore->remove($channelId, $userId);
    }


    public function getNewUsersWithChannels(int $seconds): array
    {
        /** @var UserDataStore $dataStore */
        $dataStore = $this->container->get(UserDataStore::class);
        return $dataStore->newUsersWithChannels($seconds);
    }

    /**
     * @param string $resourceType
     * @param int|string $resourceId
     * @return string|null
     */
    public function getPublicHashForResource(string $resourceType, $resourceId): ?string
    {
        /** @var PublicHashDataStore $dataStore */
        $dataStore = $this->container->get(PublicHashDataStore::class);
        return $dataStore->findPublicHashForResource($resourceType, (string) $resourceId);
    }

    /**
     * @param string $resourceType
     * @param int|string $resourceId
     * @return string
     * @throws \Exception
     */
    public function createPublicHash(string $resourceType, $resourceId): string
    {
        /** @var PublicHashDataStore $dataStore */
        $dataStore = $this->container->get(PublicHashDataStore::class);
        return $dataStore->createPublicHash($resourceType, (string) $resourceId);
    }

    /**
     * @param string $resourceType
     * @param int|string $resourceId
     */
    public function deletePublicHashByResource(string $resourceType, $resourceId): void
    {
        /** @var PublicHashDataStore $dataStore */
        $dataStore = $this->container->get(PublicHashDataStore::class);
        $dataStore->deletePublicHashByResource($resourceType, (string) $resourceId);
    }

    /**
     * @param string $resourceType
     * @param string|int $resourceId
     * @return string|null
     */
    public function findShortUrlHashForResource(string $resourceType, $resourceId): ?string
    {
        /** @var ShortUrlDataStore $dataStore */
        $dataStore = $this->container->get(ShortUrlDataStore::class);
        return $dataStore->getByResource($resourceType, (string) $resourceId);
    }

    public function findPublicHashForResource(string $resourceType, string $resourceId): ?string
    {
        /** @var PublicHashDataStore $dataStore */
        $dataStore = $this->container->get(PublicHashDataStore::class);
        return $dataStore->getByResource($resourceType, $resourceId);
    }

    public function deleteShortUrlByResource(string $resourceType, $resourceId): void
    {
        /** @var ShortUrlDataStore $dataStore */
        $dataStore = $this->container->get(ShortUrlDataStore::class);
        $dataStore->deletePublicHashByResource($resourceType, (string) $resourceId);
    }

    public function generateUniqueShortUrlHash(): string
    {
        /** @var ShortUrlDataStore $dataStore */
        $dataStore = $this->container->get(ShortUrlDataStore::class);
        return $dataStore->generateUniqueHash();
    }

    public function findContactFormByHash(string $formHash): ?ContactForm
    {
        /** @var ContactFormDataStore $dataStore */
        $dataStore = $this->container->get(ContactFormDataStore::class);
        return $dataStore->findByHash($formHash);
    }

    public function createChannelGroup(string $name, int $userId, bool $virtual = false): ChannelGroup
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->createChannelGroup($name, $userId, $virtual);
    }

    public function addChannelToGroup(int $channelId, int $userId, int $groupId): void
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        $dataStore->addChannelToGroup($channelId, $userId, $groupId);
    }

    /**
     * @param int $userId
     * @return ChannelGroup[]
     * @throws DatabaseException
     */
    public function findChannelGroupsForUser(int $userId): array
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->findForUser($userId);
    }

    /**
     * @param int $groupId
     * @return ChannelGroup|null
     * @throws DatabaseException
     */
    public function getChannelGroup(int $groupId): ?ChannelGroup
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->findById($groupId);
    }

    public function channelGroupExists(int $groupId): bool
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->exists($groupId);
    }

    public function findVirtualChannelGroupId(int $userId, string $virtualKey): ?int
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->findVirtualGroupId($userId, $virtualKey);
    }

    public function moveChannelGroup(int $userId, int $groupId, int $after)
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        $dataStore->move($userId, $groupId, $after);
    }

    public function transaction($callback, int $maxRetry = 5): void
    {
        /** @var DatabaseService $dbs */
        $dbs = $this->container->get(DatabaseService::class);
        $dbs->transaction($callback, $maxRetry);
    }

    public function beginTransaction(): void
    {
        $this->container->get(DatabaseService::class)->beginTransaction();
    }

    public function commit(): void
    {
        $this->container->get(DatabaseService::class)->commit();
    }

    public function rollback(): void
    {
        $this->container->get(DatabaseService::class)->rollback();
    }

    public function findChannelGroupById(int $groupId): ?ChannelGroup
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->findById($groupId);
    }

    public function removeChannelFromGroup(int $channelId, int $userId): void
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        $dataStore->removeChannelFromGroup($channelId, $userId);
    }

    public function deleteChannelGroup(int $groupId): void
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        $dataStore->delete($groupId);
    }

    public function updateChannelGroup(int $groupId, string $name): ChannelGroup
    {
        /** @var ChannelGroupDataStore $dataStore */
        $dataStore = $this->container->get(ChannelGroupDataStore::class);
        return $dataStore->update($groupId, $name);
    }

    public function createMention(Mention $mention)
    {
        /** @var MentionDataStore $dataStore */
        $dataStore = $this->container->get(MentionDataStore::class);
        return $dataStore->create($mention);
    }

    public function createActionHistory(ActionHistory $history)
    {
        /** @var ActionHistoryDataStore $dataStore */
        $dataStore = $this->container->get(ActionHistoryDataStore::class);
        return $dataStore->create($history);
    }

    /**
     * @param int $actionId
     * @return ActionHistory[]
     * @throws DatabaseException
     */
    public function findActionHistory(int $actionId): array
    {
        /** @var ActionHistoryDataStore $dataStore */
        $dataStore = $this->container->get(ActionHistoryDataStore::class);
        return $dataStore->getHistoryForAction($actionId);
    }

    public function createMessageAttachment(MessageAttachment $messageAttachment)
    {

        /** @var MessageAttachmentDataStore $dataStore */
        $dataStore = $this->container->get(MessageAttachmentDataStore::class);
        $dataStore->create($messageAttachment);

    }

    /**
     * @param int $messageId
     * @return MessageAttachment[]
     */
    public function findAttachmentsForMessage(int $messageId): array
    {
        /** @var MessageAttachmentDataStore $dataStore */
        $dataStore = $this->container->get(MessageAttachmentDataStore::class);
        return $dataStore->findForMessage($messageId);
    }

    /**
     * @param string $type
     * @param string $key
     * @return Message[]
     * @throws DatabaseException
     */
    public function findMessagesForAttachment(string $type, string $key): array
    {
        /** @var MessageAttachmentDataStore $dataStore */
        $dataStore = $this->container->get(MessageAttachmentDataStore::class);
        return $dataStore->findMessageForAttachment($type, $key);
    }

    public function removeMessageAttachment(string $type, string $key): void
    {
        /** @var MessageAttachmentDataStore $dataStore */
        $dataStore = $this->container->get(MessageAttachmentDataStore::class);
        $dataStore->removeByAttachment($type, $key);
    }

    public function isActionMentioned(int $actionId, int $messageId): bool
    {
        /** @var ActionHistoryDataStore $dataStore */
        $dataStore = $this->container->get(ActionHistoryDataStore::class);
        return $dataStore->isActionMentioned($actionId, $messageId);
    }


    /**
     * @param $call
     * @return bool
     * @throws DatabaseException
     */
    public function createCall(Call $call): bool
    {
        /** @var CallDataStore $dataStore */
        $dataStore = $this->container->get(CallDataStore::class);
        return $dataStore->createCall($call);
    }

    /**
     * @param string $callHash
     * @return Call|null
     * @throws DatabaseException
     */
    public function getCallByHash(string $callHash): ?Call
    {
        /** @var CallDataStore $dataStore */
        $dataStore = $this->container->get(CallDataStore::class);
        return $dataStore->getCallForHash($callHash);
    }

    /**
     * @param Call $call
     * @return bool
     * @throws DatabaseException
     */
    public function updateCall(Call $call): bool
    {
        /** @var CallDataStore $dataStore */
        $dataStore = $this->container->get(CallDataStore::class);
        return $dataStore->updateCall($call);
    }

    /**
     * @param string $callHash
     * @return Call|null
     * @throws DatabaseException
     */
    public function deleteCallByHash(string $callHash): ?Call
    {
        /** @var CallDataStore $dataStore */
        $dataStore = $this->container->get(CallDataStore::class);
        return $dataStore->deleteCallUsingHash($callHash);
    }

    public function listLinksForChannel(int $channelId, ?int $cursor = null, ?int $limitAfter = null, ?int $limitBefore = null): ?array
    {
        /** @var MessageAttachmentDataStore $dataStore */
        $dataStore = $this->container->get(MessageAttachmentDataStore::class);
        return $dataStore->listLinksForChannel($channelId, $cursor, $limitAfter, $limitBefore);
    }

    /**
     * @param int $userId
     * @param int|null $userRole
     * @return Team[]
     * @throws DatabaseException
     */
    public function getTeamsForUser(int $userId, ?int $userRole = null): array
    {
        /** @var TeamDataStore $dataStore */
        $dataStore = $this->container->get(TeamDataStore::class);
        return $dataStore->getTeamsForUser($userId, $userRole);
    }

    public function getChannelPath(int $channelId, int $type): ?ChannelPath
    {
        /** @var ChannelPathDataStore $dataStore */
        $dataStore = $this->container->get(ChannelPathDataStore::class);
        return $dataStore->getChannelPath($channelId, $type);
    }

    public function removeAttachmentFromMessage(int $messageId): void
    {
        /** @var MessageAttachmentDataStore $dataStore */
        $dataStore = $this->container->get(MessageAttachmentDataStore::class);
        $dataStore->removeFromMessage($messageId);
    }

    public function getTeamUsersCount(int $teamId): int
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->getTeamUsersCount($teamId);

    }

    public function findTeamOwner(int $teamId): ?User
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->getTeamOwner($teamId);
    }

    public function isTeamMember(int $teamId, int $userId): bool
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        return $dataStore->isMember($teamId, $userId);
    }

    /**
     * @param int $teamId
     * @param int $userId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function findTeamChannelsForUser(int $teamId, int $userId): array
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->findTeamChannelsForUser($teamId, $userId);
    }

    public function unassignAllActionsOnChannel(int $userId, int $channelId): void
    {
        /** @var ActionDataStore $dataStore */
        $dataStore = $this->container->get(ActionDataStore::class);
        $dataStore->unassignAllActionsOnChannel($userId, $channelId);
    }

    /**
     * @param int $teamId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function findTeamChannels(int $teamId): array
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->findTeamChannels($teamId);
    }


    /**
     * @param int $userId
     * @return int
     * @throws DatabaseException
     */
    public function findUsedTeamSeats(int $userId): int
    {

        /** @var UserDataStore $dataStore */
        $dataStore = $this->container->get(UserDataStore::class);
        return $dataStore->usedTeamSeats($userId);

    }

    /**
     * @param int $teamId
     * @param int $userId
     * @return Channel[]
     * @throws DatabaseException
     */
    public function getTeamChannelsOwnedByUser(int $teamId, int $userId): array
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getTeamChannelsOwnedByUser($teamId, $userId);
    }

    public function getTeamChannelsForUser(int $teamId, int $userId): array
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getTeamChannelsForUser($teamId, $userId);
    }

    public function setTeamUserRole(int $teamId, int $userId, int $role): void
    {
        /** @var TeamUserDataStore $dataStore */
        $dataStore = $this->container->get(TeamUserDataStore::class);
        $dataStore->setTeamUserRole($teamId, $userId, $role);
    }

    /**
     * @param int $userId
     * @return ChannelUser[]
     * @throws DatabaseException
     */
    public function getBlockedChannelsForUser(int $userId): array
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getBlockedChannelsForUser($userId);
    }

    /**
     * @param int $channelId
     * @return ChannelUser[]
     * @throws DatabaseException
     */
    public function getBlockerUsersForChannel(int $channelId): array
    {
        /** @var ChannelUserDataStore $dataStore */
        $dataStore = $this->container->get(ChannelUserDataStore::class);
        return $dataStore->getBlockerUsersForChannel($channelId);
    }

    public function getDMSentByInviterCount(int $channelId): int
    {
        /** @var ChannelDataStore $dataStore */
        $dataStore = $this->container->get(ChannelDataStore::class);
        return $dataStore->getDMSentByInviterCount($channelId);
    }

    /**
     * @param int $channelId
     * @param int $reportedUserId
     * @param bool $isDM
     * @param int $reporterId
     * @param string $message
     * @throws DatabaseException
     */
    public function saveSpamReport(int $channelId, int $reportedUserId, bool $isDM, int $reporterId, string $message): void
    {

        /** @var SpamReportDataStore $dataStore */
        $dataStore = $this->container->get(SpamReportDataStore::class);
        $dataStore->createReport($channelId, $reportedUserId, $isDM, $reporterId, $message);

    }

}
