<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\OAuth;

use Carbon\Carbon;
use CodeLathe\Core\Objects\OAuth\AccessToken;
use CodeLathe\Core\Objects\OAuth\Scope;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;

class AccessTokenDataStore implements AccessTokenRepositoryInterface
{

    use DataStoreTrait;

    /**
     * @param ClientEntityInterface $clientEntity
     * @param array $scopes
     * @param null $userIdentifier
     * @return AccessTokenEntityInterface|void
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessToken();

        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier($userIdentifier);
        }

        return $accessToken;

    }

    /**
     * @param AccessTokenEntityInterface $accessTokenEntity
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {

        $scopes = array_map(function (Scope $item) {
            return $item->getIdentifier();
        }, $accessTokenEntity->getScopes() ?? []);
        $scopes = implode(',', $scopes);

        $sql = <<<sql
            INSERT INTO oauth_access_tokens(id, user_id, client_id, scopes, expires_at)
            VALUES (:id, :user_id, :client_id, :scopes, :expires_at)
sql;
        $bindings = [
            'id' => $accessTokenEntity->getIdentifier(),
            'user_id' => $accessTokenEntity->getUserIdentifier(),
            'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
            'scopes' => $scopes,
            'expires_at' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ];
        $this->databaseService->executeStatement($sql, $bindings);

    }

    /**
     * @param string $tokenId
     */
    public function revokeAccessToken($tokenId)
    {
        $sql = <<<sql
            UPDATE oauth_access_tokens
            SET revoked = true
            WHERE id = :id
sql;
        $this->databaseService->executeStatement($sql, ['id' => $tokenId]);
    }

    /**
     * @param string $tokenId
     * @return bool|void
     */
    public function isAccessTokenRevoked($tokenId)
    {
        $sql = <<<sql
            SELECT 1
            FROM oauth_access_tokens
            WHERE id = :id AND revoked
sql;
        return $this->databaseService->selectOne($sql, ['id' => $tokenId]) !== null;

    }

    public function existsValidTokenForUserAndScope(AuthorizationRequest $authRequest, int $userId): bool
    {

        // first find the tokens generated for this user
        $sql = <<<sql
            SELECT scopes
            FROM oauth_access_tokens
            WHERE client_id = :client_id 
              AND user_id = :user_id
              AND NOT revoked
              AND expires_at > :now
sql;
        $bindings = [
            'client_id' => $authRequest->getClient()->getIdentifier(),
            'user_id' => $userId,
            'now' => Carbon::now()
        ];
        $rows = $this->databaseService->select($sql, $bindings);

        // check if one of the tokens approves all the requested scopes
        $requestedScopes = array_map(function(Scope $item) {
            return $item->getIdentifier();
        }, $authRequest->getScopes());

        foreach ($rows as $row) {
            $allowedScopes = explode(AbstractGrant::SCOPE_DELIMITER_STRING, $row['scopes']);
            if (empty(array_diff($requestedScopes, $allowedScopes))) {
                return true;
            }
        }

        return false;

    }
}
