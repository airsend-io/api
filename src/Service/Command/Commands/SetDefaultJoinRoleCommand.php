<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

/**
 * Describes a Service instance.
 */
class SetDefaultJoinRoleCommand implements CommandInterface
{

    use CommandTrait;

    /**
     * @var ChatOperations
     */
    protected $channelOps;

    public function __construct(ChannelOperations $channelOps)
    {
        $this->channelOps = $channelOps;
    }

    protected function signature(): string
    {
        return 'join_role {role?:.*}';
    }

    protected function roles(): array
    {
        return [
            'viewer' => ChannelUser::CHANNEL_USER_ROLE_VIEWER,
            'member' => ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR,
            'collaborator' => ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI,
        ];
    }

    /**
     * @return array|null
     * @throws SlashCommandException
     */
    public function handle(): ?array
    {
        $currentRoleId = $this->channel->getDefaultJoinerRole();
        $currentRoleName = array_search($currentRoleId, $this->roles());

        // no new role defined, just show it
        $newRoleName = $this->argument('role');
        if ($newRoleName === null) {
            return ['message' => I18n::get("messages.command_join_role_current", ['role' => $currentRoleName])];
        }

        // new role defined, verify if it's valid
        $newRoleId = $this->roles()[$newRoleName] ?? null;
        if ($newRoleId === null) {
            throw new SlashCommandException(400, I18n::get('messages.command_join_role_invalid_role', [
                'roles' => implode(', ', array_keys($this->roles()))
            ]));
        }

        if ($newRoleId === $currentRoleId) {
            throw new SlashCommandException(400, I18n::get('messages.command_join_role_already_set', [
                'role' => $newRoleName
            ]));
        }

        try {
            $this->channelOps->setDefaultJoinerRole($this->user, $this->channel, $newRoleId);
        } catch (\Throwable $e) {
            throw new SlashCommandException(400, 'Failed to set role');
        }

        return ['message' => I18n::get('messages.command_join_role_success', ['from_role' => $currentRoleName, 'to_role' => $newRoleName])];
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('join_role', true, ChannelUser::CHANNEL_USER_ROLE_ADMIN);
    }
}