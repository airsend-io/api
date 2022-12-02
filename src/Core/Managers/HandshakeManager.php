<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;

class HandshakeManager
{

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * HandshakeManager constructor.
     * @param ConfigRegistry $config
     */
    public function __construct(
        ConfigRegistry $config
    )
    {
        $this->config = $config;
    }

    /**
     * Return handshake info
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function handshake(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        $settings = [
            'recaptchaEnabled' => $this->config->get('/captcha/enabled'),
            'recaptchaV2SiteKey' => $this->config->get('/captcha/v2/siteKey'),
            'recaptchaV3SiteKey' => $this->config->get('/captcha/v3/siteKey'),
            'recaptchaAndroidSiteKey' => $this->config->get('/captcha/android/siteKey'),
            'giphyKey' => $this->config->get('/giphy/key'),
            'wrtcServerAddress' => $this->config->get('/app/wrtc_server_address'),
            'channel_roles' => $this->channelRoles(),
        ];

        return JsonOutput::success()
            ->withContent('settings', $settings)
            ->write($response);
    }

    protected function channelRoles(): array
    {
        // TODO - Refactor this to link with the roles defined on the ChannelUser constants
        // for now we're just returning the hard coded roles to keep all clients in sync
        return [
            10 => [
                'title' => 'viewer',
                'i18n' => 'channels.members-badge-viewer',
                'description' => 'Read Messages, Wiki and Download Files',
                'icon' => 'user',
                'perms' => [],
                'level' => 10
            ],
            20 => [
                'title' => 'collaborator',
                'i18n' => 'channels.members-badge-collaborator',
                'description' => 'Post Messages, Read Wiki and Upload Files',
                'icon' => 'user-edit',
                'perms' => [
                    'action.create' => false,
                    'action.update' => false,
                    'file.upload' => true,
                    'channel.message' => true
                ],
                'level' => 20,
            ],
            30 => [
                'title' => 'full-collaborator',
                'i18n' => 'channels.members-badge-full-collaborator',
                'description' => 'Post Messages, Edit Wiki and Upload Files',
                'icon' => 'user-tie',
                'perms' => [
                    'action.create' => true,
                    'action.update' => true,
                    'file.upload' => true,
                    'wiki.edit' => true,
                    'channel.message' => true
                ],
                'level' => 30,
            ],
            50 => [
                'title' => 'manager',
                'i18n' => 'channels.members-badge-manager',
                'description' => 'Post Messages, Edit Wiki, Upload Files and Managing users',
                'icon' => 'user-shield',
                'perms' => [
                    'channel.invite' => true,
                    'channel.kick' => true,
                    'action.create' => true,
                    'action.update' => true,
                    'wiki.edit' => true,
                    'file.upload' => true,
                    'channel.message' => true,
                    'channel.manage' => true,
                    'channel.approve' => true
                ],
                'level' => 50,

            ],
            100 => [
                'title' => 'admin',
                'i18n' => 'channels.members-badge-admin',
                'description' => 'Manage channel completely',
                'icon' => 'user-crown',
                'perms' => [
                    'super' => true
                ],
                'level' => 100,
            ]
        ];
    }

}