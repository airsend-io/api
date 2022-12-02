<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Auth;

use CodeLathe\Core\Data\OAuth\AccessTokenDataStore;
use CodeLathe\Core\Utility\Auth;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthOperations
{


    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var AccessTokenDataStore
     */
    protected $oauthTokenDataStore;

    public function __construct(CacheItemPoolInterface $cache, AccessTokenDataStore $oauthTokenDataStore)
    {
        $this->cache = $cache;
        $this->oauthTokenDataStore = $oauthTokenDataStore;
    }

    public function logout(ServerRequestInterface $request): void
    {
        // first get the auth object
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');

        $userId = $auth->getUserId();

        // ... clear the jwt token if exists
        $jti = $auth->getJwtJti();
        $key = "jwt.jti.{$userId}.{$jti}";
        if ($jti !== null && $this->cache->hasItem($key)) {
            $this->cache->deleteItem($key);
        }

        // ... clear the oauth token if it exists
        $oauthTokenId = $auth->getOauthTokenId();
        if ($oauthTokenId !== null) {
            $this->oauthTokenDataStore->revokeAccessToken($oauthTokenId);
        }
    }
}