<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

/**
 * Describes a Service instance.
 */
class UnmuteCommand implements CommandInterface
{

    use CommandTrait;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    public function __construct(ChannelOperations $channelOps)
    {
        $this->channelOps = $channelOps;
    }

    protected function signature(): string
    {
        return 'unmute';
    }

    /**
     * @return array|null
     * @throws SlashCommandException
     */
    public function handle(): ?array
    {
        try {
            $this->channelOps->muteChannel($this->user, $this->channel, false);
        } catch (ChannelOpException $e) {
            throw new SlashCommandException(400, $e->getMessage());
        }

        return ['message' => I18n::get('messages.channel_unmute_success', ['channel' => $this->channel->getName()])];
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('unmute', false, ChannelUser::CHANNEL_USER_ROLE_VIEWER);
    }
}