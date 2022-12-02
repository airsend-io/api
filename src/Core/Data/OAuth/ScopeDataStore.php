<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\OAuth;

use CodeLathe\Core\Objects\OAuth\Scope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeDataStore implements ScopeRepositoryInterface
{

    protected function scopes()
    {
        return [
            'global' => [
                'description' => 'Do anything in your name',
            ],
            'user_info' => [
                'description' => 'Get your profile info.',
                'routes' => ['user.info', 'user.logout'],
            ],
            'post_chat' => [
                'description' => 'List your channels, post messages and attachments in your name, grab info about your files (can\'t read file contents)',
                'routes' => [
                    'user.info',
                    'channel.list',
                    'chat.postmessage',
                    'file.upload',
                    'file.create',
                    'file.list',
                    'file.info',
                    'user.logout',
                    'user.image.get',
                ]
            ]

        ];
    }

    public function scopeRoutes(string $scope)
    {
        return $this->scopes()[$scope]['routes'] ?? [];
    }

    public function scopeDescription(string $scope)
    {
        return $this->scopes()[$scope]['description'] ?? [];
    }

    /**
     * @param string $identifier
     * @return ScopeEntityInterface|void|null
     */
    public function getScopeEntityByIdentifier($identifier)
    {
        if (in_array($identifier, array_keys($this->scopes()))) {
            return new Scope($identifier);
        }
    }

    /**
     * @param array $scopes
     * @param string $grantType
     * @param ClientEntityInterface $clientEntity
     * @param null $userIdentifier
     * @return ScopeEntityInterface[]|void
     */
    public function finalizeScopes(array $scopes, $grantType, ClientEntityInterface $clientEntity, $userIdentifier = null)
    {
        return array_filter($scopes, function (ScopeEntityInterface $scope) {
            return in_array($scope->getIdentifier(), array_keys($this->scopes()));
        });
    }
}
