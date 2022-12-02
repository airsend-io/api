<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\Directories;

class UserDimensionParser
{

    const INTERNAL_DOMAINS = [
        'codelathe.com'
    ];

    /**
     * @var int|null
     */
    protected $userId;

    /**
     * @var DataController
     */
    protected $dataController;

    public function __construct(?int $userId)
    {
        $this->dataController = ContainerFacade::get(DataController::class);
        $this->userId = $userId;
    }

    /**
     * Parses and save the dimension data to the database.
     * Returns the id of the inserted/found record
     *
     * @return array
     * @throws \CodeLathe\Core\Exception\DatabaseException
     */
    public function parse(): array
    {

        if ($this->userId === null) {
            return [];
        }

        $user = $this->dataController->getUserById($this->userId);
        $email = $user->getEmail();
        [, $domain] = explode('@', $email);

        $data = [];
        if (isset($email)) {
            $data['email'] = $email;
            $data['email_domain'] = $domain;
            $data['company_domain'] = $this->isCompanyDomain($domain);
            $data['internal'] = $this->isInternal($domain);
            $data['channels_owned'] = $this->dataController->getChannelsOwnedCountForUser($this->userId);
            $data['channels_member'] = $this->dataController->getChannelsCountForUser($this->userId);
            $data['messages_sent'] = $this->dataController->getMessageSentCountForUser($this->userId);
            $data['created_on'] = $user->getCreatedOn();
        }
        return $data;
    }

    protected function isCompanyDomain(string $domain): bool
    {
        $freeEmailProviders = file_get_contents(Directories::resources('email/free_providers.txt'));
        $freeEmailProviders = array_map('trim', explode(PHP_EOL, $freeEmailProviders));
        return !in_array($domain, $freeEmailProviders);
    }

    protected function isInternal(string $domain): bool
    {
        return in_array($domain, static::INTERNAL_DOMAINS);
    }

}