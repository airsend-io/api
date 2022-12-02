<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Auth;

use CodeLathe\Service\Auth\Exceptions\InvalidTokenException;

interface JwtServiceInterface
{
    /**
     * Issues a new token
     *
     * @param int|string $subject
     * @param string $remoteAddr
     * @param string $remoteAgent
     * @param bool $admin
     * @param bool $rememberMe
     * @param string|null $scope
     * @return string
     */
    public function issueToken($subject,
                               string $remoteAddr,
                               string $remoteAgent,
                               bool $admin,
                               ?bool $rememberMe = false,
                               ?string $scope = 'global'): string;

    /**
     * Validates and decode a given token, returning it's payload
     * @param string $token
     * @param bool|null $checkTTL
     * @return array
     */
    public function decodeToken(string $token, ?bool $checkTTL = true): array;

}