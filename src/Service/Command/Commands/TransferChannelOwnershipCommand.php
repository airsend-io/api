<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

/**
 * Describes a Service instance.
 */
class TransferChannelOwnershipCommand implements CommandInterface
{

    use CommandTrait;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    /**
     * @var DataController
     */
    protected $dataController;

    public function __construct(ChatOperations $chatOps, ChannelOperations $channelOps, DataController $dataController)
    {
        $this->chatOps = $chatOps;
        $this->channelOps = $channelOps;
        $this->dataController = $dataController;
    }

    protected function signature(): string
    {
        return 'transfer_ownership {to:\[[^]]+\]\(user:\/\/[0-9]+\)} {--team=}';
    }

    public function handle(): ?array
    {

        // find the user to transfer to
        $userMention = $this->argument('to');
        preg_match('/\[[^]]+\]\(user:\/\/([0-9]+)\)/', $userMention, $matches);
        $newOwnerId =  (int)$matches[1];

        // instantiate the new owner
        $newOwner = $this->dataController->getUserById($newOwnerId);
        if (empty($newOwner)) {
            throw new SlashCommandException(404, "Unknown User");
        }

        // find the team to transfer (if provided)
        $teamId = !empty($this->option('team')) ? ((int)$this->option('team')) : null;

        try {
            $this->channelOps->transferOwnership($this->user, $this->channel, $newOwnerId, $teamId);
        } catch (\Throwable $e) {
            throw new SlashCommandException('Failed to transfer the channel');
        }

        $botMessage = "[{$newOwner->getDisplayName()}](user://{$newOwner->getId()}) is the owner of this channel now.";
        $botEvent = MessageBot::create(2, $botMessage, 'bot.transfer_ownership', null, [
            'user_name' => $newOwner->getDisplayName(),
            'user_id' => $newOwner->getId(),
        ]);
        $this->chatOps->raiseBotNotification($botEvent, $this->channel->getId());

        return null;
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('transfer_ownership', true, 200); // 200 is a virtual role for owners
    }
}