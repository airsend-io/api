<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Service\Command\Commands\CallCommand;
use CodeLathe\Service\Command\Commands\InspireCommand;
use CodeLathe\Service\Command\Commands\KickCommand;
use CodeLathe\Service\Command\Commands\MeCommand;
use CodeLathe\Service\Command\Commands\MuteCommand;
use CodeLathe\Service\Command\Commands\SetChannelLocaleCommand;
use CodeLathe\Service\Command\Commands\SetDefaultInviteeRoleCommand;
use CodeLathe\Service\Command\Commands\SetDefaultJoinRoleCommand;
use CodeLathe\Service\Command\Commands\SetUserLocaleCommand;
use CodeLathe\Service\Command\Commands\TransferChannelOwnershipCommand;
use CodeLathe\Service\Command\Commands\UnmuteCommand;
use CodeLathe\Service\ServiceInterface;

/**
 * Describes a Service instance.
 */
class CommandService implements ServiceInterface
{

    /**
     * @var CommandInterface[]
     */
    protected $instances;

    public function name(): string
    {
        return 'Command Service';
    }

    public function description(): string
    {
        return 'Manages the slash commands';
    }

    protected $registeredCommands = [
        InspireCommand::class,
        MeCommand::class,
        CallCommand::class,
        TransferChannelOwnershipCommand::class,
        KickCommand::class,
        SetUserLocaleCommand::class,
        SetChannelLocaleCommand::class,
        SetDefaultJoinRoleCommand::class,
        SetDefaultInviteeRoleCommand::class,
        MuteCommand::class,
        UnmuteCommand::class,
    ];

    protected function getCommandInstance(string $commandClass)
    {
        if (!isset($this->instances[$commandClass])) {
            $this->instances[$commandClass] = ContainerFacade::get($commandClass);
        }
        return $this->instances[$commandClass];
    }

    /**
     * @param Channel $channel
     * @param User $user
     * @param string $commandString
     * @param string $params
     * @return CommandInterface|null
     */
    public function createCommand(Channel $channel, User $user, string $commandString, string $params): ?CommandInterface
    {

        foreach ($this->registeredCommands as $commandClass) {
            /** @var CommandInterface $command */
            $command = $this->getCommandInstance($commandClass);
            $command->setUp($channel, $user);
            if ($command->validateSignature($commandString, $params)) {
                return $command;
            }
        }

        // command not recognized
        return null;

    }

    public function getAvailableCommands(Channel $channel, User $user): array
    {
        $output = [];
        foreach ($this->registeredCommands as $commandClass) {
            /** @var CommandInterface $command */
            $command = $this->getCommandInstance($commandClass);
            $command->setUp($channel, $user);
            $output[] = $command->getUiSignature();
        }
        return $output;
    }
}