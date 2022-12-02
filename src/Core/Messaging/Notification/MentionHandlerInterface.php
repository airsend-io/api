<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Notification;

use CodeLathe\Core\Objects\Message;

interface MentionHandlerInterface
{
    public function handle(Message $message, string $fullMention, string $mentionTitle, string $mentionId): void;
}