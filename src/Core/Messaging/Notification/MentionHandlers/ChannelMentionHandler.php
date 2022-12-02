<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Notification\MentionHandlers;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Messaging\Notification\MentionHandlerInterface;
use CodeLathe\Core\Messaging\Notification\NotificationFilter;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\AlertIssuer;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Message;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ChannelMentionHandler implements MentionHandlerInterface
{

    use MentionHandlerTrait;

    function resourceType(): string
    {
        return 'channel';
    }

    function validateResource(int $resourceId, ?string &$error = null): bool
    {
        if ($this->dataController()->getChannelById($resourceId) instanceof Channel) {
            return true;
        }
        $error = "Channel $resourceId doesn't exists";
        return false;
    }
}