<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Data\DataController;
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
class InspireCommand implements CommandInterface
{

    use CommandTrait;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    public function __construct(ChatOperations $chatOps)
    {
        $this->chatOps = $chatOps;
    }

    protected function signature(): string
    {
        return 'inspire';
    }

    protected function inspire(): string
    {
        $quotes = require Directories::resources('lang/en_US/inspire.php');

        return I18n::get('inspire.quote' . rand(1, count($quotes)),  [], $this->channel->getLocale());

    }

    public function handle(): ?array
    {
        $quote = $this->inspire();
        $botMessage = "[{$this->user->getDisplayName()}](user://{$this->user->getId()}) is inspired: \"{$quote}\"";

        $botEvent = MessageBot::create(2, $botMessage, 'bot.inspire', null, [
            'user_name' => $this->user->getDisplayName(),
            'user_id' => $this->user->getId(),
            'quote' => $quote,
        ]);
        $this->chatOps->raiseBotNotification($botEvent, $this->channel->getId());

        return null;
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('inspire', false, ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR);
    }
}