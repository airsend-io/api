<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

/**
 * Describes a Service instance.
 */
class MeCommand implements CommandInterface
{

    use CommandTrait;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    public function __construct(ChatOperations $chatOperations)
    {
        $this->chatOps = $chatOperations;
    }

    protected function signature(): string
    {
        return 'me {status:.*}';
    }

    public function handle(): ?array
    {

        $botMessage = "[{$this->user->getDisplayName()}](user://{$this->user->getId()}) {$this->argument('status')}";
        $botEvent = MessageBot::create(2, $botMessage);
        $this->chatOps->raiseBotNotification($botEvent, $this->channel->getId());

        return null;
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('me', true, ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR);
    }
}