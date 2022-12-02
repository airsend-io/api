<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use DateTime;
use Exception;

class Auth
{

    /**
     * @var int|null
     */
    protected $userId;

    /**
     * @var string
     */
    protected $clientIp;

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @var bool
     */
    protected $isAdmin;

    /**
     * @var int
     */
    protected $isPublicForChannelId;

    /**
     * @var DateTime
     */
    protected $expirationTime;

    /**
     * @var string
     */
    protected $oauthClientId;

    /**
     * @var string|null
     */
    protected $oauthTokenId = null;

    /**
     * @var string|null
     */
    protected $jwtJti;

    /**
     * @var string
     */
    protected $publicHash;

    /**
     * Auth constructor.
     * @param string $clientIp
     * @param string $userAgent
     */
    public function __construct(string $clientIp, string $userAgent)
    {
        $this->clientIp = $clientIp;
        $this->userAgent = $userAgent;
    }

    /**
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @param int $userId
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @return bool
     */
    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    /**
     * @param bool $isAdmin
     */
    public function setIsAdmin(bool $isAdmin) : void
    {
        $this->isAdmin = $isAdmin;
    }

    /**
     * @return bool
     */
    public function getIsAdmin() : bool
    {
        return $this->isAdmin;
    }

    /**
     * @param int $time - Timestamp that represents the expiration time
     * @throws Exception
     */
    public function setExpirationTime(int $time): void
    {
        $this->expirationTime = new DateTime();
        $this->expirationTime->setTimestamp($time);
    }

    /**
     * @return DateTime
     */
    public function getExpirationTime(): DateTime
    {
        return $this->expirationTime;
    }

    public function setOauthClientId(?string $clientId): void
    {
        $this->oauthClientId = $clientId;
    }

    public function getOauthClientId(): ?string
    {
        return $this->oauthClientId;
    }

    public function setOauthTokenId(?string $tokenId): void
    {
        $this->oauthTokenId = $tokenId;
    }

    public function getOauthTokenId(): ?string
    {
        return $this->oauthTokenId;
    }

    public function setJwtJti(?string $jti): void
    {
        $this->jwtJti = $jti;
    }

    public function getJwtJti(): ?string
    {
        return $this->jwtJti;
    }

    public function setPublicHash(string $hash): void
    {
        $this->publicHash = $hash;
    }

    public function getPublicHash(): ?string
    {
        return $this->publicHash ?? null;
    }
}
