<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelAuthorizationException;
use CodeLathe\Core\Exception\HttpException;
use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

/**
 * Describes a Service instance.
 */
class KickCommand implements CommandInterface
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
        return 'kick {user:\[[^]]+\]\(user:\/\/[0-9]+\)} {--ban}';
    }

    /**
     * @return array|null
     * @throws SlashCommandException
     */
    public function handle(): ?array
    {

        // find the user to kick
        $userMention = $this->argument('user');
        preg_match('/\[[^]]+\]\(user:\/\/([0-9]+)\)/', $userMention, $matches);

        // instantiate the user to remove
        $userToRemove = $this->dataController->getUserById((int)$matches[1]);
        if (empty($userToRemove)) {
            throw new SlashCommandException(404, "Unknown User");
        }

        // should blacklist this user?
        $blacklist = (bool)$this->option('ban');

        if (!$this->channelOps->isChannelOperationAllowed($this->user, $this->channel, GlobalAuthContext::AUTH_MANAGE)) {
            throw new SlashCommandException(401, 'You do not have authority to remove members from this channel');
        }

        try {
            $this->channelOps->removeUserFromChannel($userToRemove, $this->user, $this->channel, $blacklist);
        } catch (ChannelAuthorizationException $e) {
            throw new SlashCommandException(401, $e->getMessage());
        } catch (ASException $e) {
            throw new SlashCommandException(400, $e->getMessage());
        }

        return null;
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('kick', true, ChannelUser::CHANNEL_USER_ROLE_ADMIN);
    }
}