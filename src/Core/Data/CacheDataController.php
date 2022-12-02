<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data;


use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Service\Cache\CacheService;
use CodeLathe\Service\Cache\CacheSvc;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class CacheDataController
{

    /**
     * Declare container
     *
     * @var ContainerInterface
     */
    private $container;

    private $cache;

    private $allowCache = true;

    private $logger;

    /**
     * DataController constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = $this->container->get(CacheService::class);
        $this->logger = $this->container->get(LoggerInterface::class);
    }

    public function flush()
    {
        $this->cache->flush();
    }

    private function allowCache()
    {
        return true;
    }

    #region "users"
    public function setUserCache(User $user) : void
    {
        if (!$this->allowCache) return;
        $key = 'users:' . $user->getId();
        $this->cache->setHash($key, $user->getArray());
        if (!empty($user->getEmail()))
            $this->cache->addSortedListItem('users:email', $user->getEmail(), $user->getId());
        if (!empty($user->getPhone()))
            $this->cache->addSortedListItem('users:phone', $user->getPhone(), $user->getId());
        if (!empty($user->getInvitedBy())) {
            $this->cache->increment("invites:" . $user->getInvitedBy());
            $this->cache->addSortedListItem("invites:timestamp", $user->getInvitedBy(), time());
            $this->cache->forget('auth:user:global:' .  $user->getInvitedBy());
        }
    }

    public function getUserCacheById(int $userId) : ?User
    {
        if (!$this->allowCache) return null;
        $record =  $this->cache->getHash('users:'.$userId);
        return (empty($record) ? null : User::withDBData($record));
    }

    public function getUserCacheByEmail(string $email) : ?User
    {
        if (!$this->allowCache) return null;
        $userId = $this->cache->getSortedListItem("users:email", $email);
        $record =  $this->cache->getHash('users:'.$userId);
        return (empty($record) ? null : User::withDBData($record));
    }

    public function getUserCacheByPhone(string $phone) : ?User
    {
        if (!$this->allowCache) return null;
        $userId = $this->cache->getSortedListItem("users:phone", $phone);
        $record =  $this->cache->getHash('users:'.$userId);
        return (empty($record) ? null : User::withDBData($record));
    }

    public function getUserCacheByEmailOrPhone(string $emailOrPhone) : ?User
    {
        if (!$this->allowCache) return null;
        $userId = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL) ?
                        $this->cache->getSortedListItem("users:email", $emailOrPhone) :
                        $this->cache->getSortedListItem("users:phone", $emailOrPhone);
        $record = $this->cache->getHash('users:' . $userId);
        return (empty($record) ? null : User::withDBData($record));

    }

    public function deleteUserCache(int $userId)
    {
        if (!$this->allowCache) return null;
        $user = $this->getUserCacheById($userId);
        $this->cache->delSortedListItem("users:email",$user->getEmail());
        $this->cache->delSortedListItem("users:phone",$user->getPhone());
        $this->cache->forget( 'users:' . $userId);
    }


    public function getInvitationSentCountCache(int $userId) : ?int
    {
        if (!$this->allowCache) return null;
        return $this->cache->get("invites:" . $userId);
    }

    #endregion

    #region "channels"
    public function setChannelCache(Channel $channel)
    {
        if (!$this->allowCache) return;
        $key = 'channels:' . $channel->getId();
        $this->cache->setHash($key, $channel->getArray());
    }

    public function deleteChannelCache(int $channelId)
    {
        if (!$this->allowCache) return;
        $this->cache->forget( 'channels:' . $channelId);
    }

    public function getChannelByIdCache(int $channelId) : ?Channel
    {
        if (!$this->allowCache) return null;
        $record =  $this->cache->getHash('channels:'.$channelId);
        return (empty($record) ? null : Channel::withDBData($record));
    }

    #endregion

    public function setTeamCache(Team $team)
    {
        if (!$this->allowCache) return;
        $key = 'teams:' . $team->getId();
        $this->cache->setHash($key, $team->getArray());
    }

    private function isSelfTeam(int $teamId)
    {
        if (!$this->allowCache) return;
        $teamType = $this->cache->getByHashField("teams:" . $teamId, "team_type");
        return ($teamType == Team::TEAM_TYPE_SELF);
    }

    public function addTeamUserCache(TeamUser $teamUser)
    {
        if (!$this->allowCache) return;
        if ($this->isSelfTeam($teamUser->getTeamId()))
            $this->cache->addSortedListItem("users:default_team:", $teamUser->getUserId(), $teamUser->getTeamId());

        $key = 'teams:users:' . $teamUser->getTeamId() . ":" . $teamUser->getUserId();
        $this->cache->setHash($key, $teamUser->getArray());
        $this->cache->forget('auth:user:global:' .  $teamUser->getUserId());
    }

    public function deleteTeamCache(Team $team)
    {
        if (!$this->allowCache) return;
        $matches = $this->cache->scanKeys('teams:user:' . $team->getId() . '.*');
        if (!empty($matches) && is_array($matches) && isset($matches[1][0])) {
            $this->cache->forget($matches[1][0]);
        }
    }


    public function setGlobalAuthContextCache(int $userId, array $authPermissions)
    {
        // do nothing
        /*
        if (!$this->allowCache) return;
        $key = 'globalauthcontext:' . $userId;
        $this->cache->put($key, $authPermissions, (60 * 20));
        */
    }

    public function getGlobalAuthContextCache(int $userId) : ?array
    {
        return null;
        /*
        if (!$this->allowCache) return null;
        $key = 'globalauthcontext:' . $userId;
        return $this->cache->get($key);
        */
    }

    public function forgetGlobalAuthContextCache(int $userId)
    {
        $this->cache->forget('globalauthcontext:' .  $userId);
    }

}