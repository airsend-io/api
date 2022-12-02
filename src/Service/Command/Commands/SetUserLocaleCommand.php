<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command\Commands;

use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Service\Command\CommandInterface;
use CodeLathe\Service\Command\CommandTrait;

class SetUserLocaleCommand implements CommandInterface
{

    use CommandTrait, SetLocaleCommandTrait;

    /**
     * @var UserOperations
     */
    protected $userOps;

    public function __construct(UserOperations $userOps)
    {
        $this->userOps = $userOps;
    }

    protected function signature(): string
    {
        return 'global_locale {lang:[a-zA-Z]{2}[-_][a-zA-Z]{2}}';
    }

    /**
     * @return array|null
     * @throws SlashCommandException
     * @throws DatabaseException
     */
    public function handle(): ?array
    {

        $locale = $this->sanitizeLocale($this->argument('lang'));

        try {
            $this->userOps->setProfile($this->user, null, null, null, $locale);
        } catch (UserOpException $e) {
            throw new SlashCommandException(400, $e->getMessage());
        }

        return null;
    }

    public function getUiSignature()
    {
        return $this->defaultUiSignature('global_locale', true, ChannelUser::CHANNEL_USER_ROLE_VIEWER);
    }
}