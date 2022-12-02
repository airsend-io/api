<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Notification\MentionHandlers;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Objects\Mention;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Log\LoggerInterface;

trait MentionHandlerTrait
{

    abstract function resourceType(): string;

    abstract function validateResource(int $resourceId, ?string &$error = null): bool;

    protected function logger(): LoggerInterface
    {
        return ContainerFacade::get(LoggerInterface::class);
    }

    protected function dataController(): DataController
    {
        return ContainerFacade::get(DataController::class);
    }

    public function handle(Message $message, string $fullMention, string $mentionTitle, string $mentionId): void
    {

        if ($this->validateResource((int)$mentionId, $error)) {
            $this->logger()->debug("Registering mention '$fullMention'");
            $mention = Mention::create($message->getId(), $mentionTitle, $this->resourceType(), (int)$mentionId);
            $this->dataController()->createMention($mention);
        } else {
            $this->logger()->error("Error registering mention `$fullMention`: $error");
        }
    }
}