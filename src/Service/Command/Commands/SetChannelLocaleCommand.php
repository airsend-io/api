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
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

class SetChannelLocaleCommand implements CommandInterface
{

    use CommandTrait, SetLocaleCommandTrait;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    public function __construct(ChannelOperations $channelOps, ChatOperations $chatOps)
    {
        $this->channelOps = $channelOps;
        $this->chatOps = $chatOps;
    }

    protected function signature(): string
    {
        return 'channel_locale {lang?:[a-zA-Z]{2}[-_][a-zA-Z]{2}}';
    }

    /**
     * @return array|null
     * @throws SlashCommandException
     * @throws \CodeLathe\Core\Exception\ChatOpException
     * @throws \CodeLathe\Core\Exception\DatabaseException
     */
    public function handle(): ?array
    {

        $locale = $this->argument('lang');

        if ($locale === null) {
            $currentLocale = $this->channel->getLocale();
            if (empty($currentLocale)) {
                $currentLocale = 'en-US';
            }
            $botMessage = "Current channel locale is: \"{$currentLocale}\"";
            $botEvent = MessageBot::create(2, $botMessage, 'bot.current_channel_locale', null, ['locale' => $currentLocale]);
            $this->chatOps->raiseBotNotification($botEvent, $this->channel->getId());
            return null;
        }

        $locale = $this->sanitizeLocale($this->argument('lang'));

        try {
            $this->channelOps->update($this->user, $this->channel, null, null, null, null, null, $locale);
        } catch (\Throwable $e) {
            throw new SlashCommandException(400, I18n::get('messages.channel_locale_update_failed'));
        }

        return null;
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('channel_locale', true, ChannelUser::CHANNEL_USER_ROLE_ADMIN);
    }
}