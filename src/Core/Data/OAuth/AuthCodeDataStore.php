<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\OAuth;

use CodeLathe\Core\Objects\OAuth\AuthCode;
use CodeLathe\Core\Objects\OAuth\Scope;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeDataStore implements AuthCodeRepositoryInterface
{

    use DataStoreTrait;

    /**
     * @return AuthCodeEntityInterface|void
     */
    public function getNewAuthCode()
    {
        return new AuthCode();
    }

    /**
     * @param AuthCodeEntityInterface $authCodeEntity
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        $scopes = array_map(function (Scope $item) {
            return $item->getIdentifier();
        }, $authCodeEntity->getScopes() ?? []);
        $scopes = implode(',', $scopes);

        $sql = <<<sql
            INSERT INTO oauth_auth_codes(id, user_id, client_id, scopes, expires_at)
            VALUES (:id, :user_id, :client_id, :scopes, :expires_at)
sql;
        $bindings = [
            'id' => $authCodeEntity->getIdentifier(),
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => $scopes,
            'expires_at' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ];
        $this->databaseService->executeStatement($sql, $bindings);
    }

    /**
     * @param string $codeId
     */
    public function revokeAuthCode($codeId)
    {
        $sql = <<<sql
            UPDATE oauth_auth_codes
            SET revoked = true
            WHERE id = :id
sql;
        $this->databaseService->executeStatement($sql, ['id' => $codeId]);
    }

    /**
     * @param string $codeId
     * @return bool|void
     */
    public function isAuthCodeRevoked($codeId)
    {
        $sql = <<<sql
            SELECT 1
            FROM oauth_auth_codes
            WHERE ID = :id AND revoked
sql;
        return $this->databaseService->selectOne($sql, ['id' => $codeId]) !== null;
    }
}
