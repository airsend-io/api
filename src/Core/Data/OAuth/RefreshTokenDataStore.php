<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\OAuth;

use CodeLathe\Core\Objects\OAuth\RefreshToken;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenDataStore implements RefreshTokenRepositoryInterface
{

    use DataStoreTrait;

    /**
     * @return RefreshTokenEntityInterface|void|null
     */
    public function getNewRefreshToken()
    {
        return new RefreshToken();
    }

    /**
     * @param RefreshTokenEntityInterface $refreshTokenEntity
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {

        //oauth_refresh_tokens
        $sql = <<<sql
            INSERT INTO oauth_refresh_tokens(id, access_token_id, expires_at)
            VALUES (:id, :access_token_id, :expires_at)
sql;
        $bindings = [
            'id' => $refreshTokenEntity->getIdentifier(),
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ];
        $this->databaseService->executeStatement($sql, $bindings);
    }

    /**
     * @param string $tokenId
     */
    public function revokeRefreshToken($tokenId)
    {
        $sql = <<<sql
            UPDATE oauth_refresh_tokens
            SET revoked = true
            WHERE id = :id
sql;
        $this->databaseService->executeStatement($sql, ['id' => $tokenId]);
    }

    /**
     * @param string $tokenId
     * @return bool|void
     */
    public function isRefreshTokenRevoked($tokenId)
    {
        $sql = <<<sql
            SELECT 1
            FROM oauth_refresh_tokens
            WHERE ID = :id AND revoked
sql;
        return $this->databaseService->selectOne($sql, ['id' => $tokenId]) !== null;
    }
}
