<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\OAuth;

use CodeLathe\Core\Objects\OAuth\Client;
use CodeLathe\Service\Database\DatabaseService;
use Illuminate\Support\Str;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Ramsey\Uuid\Uuid;

class ClientDataStore implements ClientRepositoryInterface
{

    use DataStoreTrait;

    /**
     * @param string $clientIdentifier
     * @return Client|void|null
     */
    public function getClientEntity($clientIdentifier)
    {
            $sql = <<<sql
                SELECT * 
                FROM oauth_clients
                WHERE id = :id
sql;
            $result = $this->databaseService->selectOne($sql, ['id' => $clientIdentifier]);
            return $result !== null ? Client::withDbData($result) : null;

    }

    /**
     * @param string $clientIdentifier
     * @param string|null $clientSecret
     * @param string|null $grantType
     * @return bool|void
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        $sql = <<<sql
                SELECT secret, grant_type
                FROM oauth_clients
                WHERE id = :id
                AND secret = :secret
sql;
        $result = $this->databaseService->selectOne($sql, ['id' => $clientIdentifier, 'secret' => $clientSecret]);
        if (empty($result)) {
            return false;
        }
        return $grantType === 'refresh_token' || trim($result['grant_type']) === $grantType;
    }

    /**
     * @param int $ownerId
     * @param string $name
     * @param string $description
     * @param string $grantType
     * @param string|null $redirect
     * @return Client
     */
    public function createClient(int $ownerId,
                                 string $name,
                                 string $description,
                                 string $grantType,
                                 ?string $redirect = null): Client
    {
        $sql = <<<sql
            INSERT INTO oauth_clients(id, owner_id, name, description, secret, redirect, grant_type)
            VALUES (:id, :owner_id, :name, :description, :secret, :redirect, :grant_type)
sql;
        $clientId = Uuid::uuid4();
        $bindings = [
            'id' => $clientId,
            'owner_id' => $ownerId,
            'name' => $name,
            'description' => $description,
            'secret' => Str::random(32),
            'redirect' => $redirect,
            'grant_type' => $grantType
        ];
        $this->databaseService->executeStatement($sql, $bindings);

        // return the inserted client
        return $this->getClientEntity($clientId);

    }

    /**
     * @param int $ownerId
     * @return Client[]
     */
    public function getClientsByOwner(int $ownerId)
    {
        $sql = <<<sql
                SELECT * 
                FROM oauth_clients
                WHERE owner_id = :owner_id
sql;
        $rows = $this->databaseService->select($sql, ['owner_id' => $ownerId]);
        return array_map(function ($row) {
            return Client::withDbData($row);
        }, $rows);
    }

}
