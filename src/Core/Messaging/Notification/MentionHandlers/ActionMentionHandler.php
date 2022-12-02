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
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\ActionHistory;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\AlertIssuer;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ActionMentionHandler implements MentionHandlerInterface
{

    use MentionHandlerTrait {
        handle as standardHandle;
    }

    /**
     * @var NormalizedObjectFactory
     */
    private $objectFactory;

    public function __construct(NormalizedObjectFactory $objectFactory)
    {
        $this->objectFactory = $objectFactory;
    }

    function resourceType(): string
    {
        return 'action';
    }

    function validateResource(int $resourceId, ?string &$error = null): bool
    {
        if ($this->dataController()->getActionById($resourceId) instanceof Action) {
            return true;
        }
        $error = "Action $resourceId doesn't exists";
        return false;
    }

    public function handle(Message $message, string $fullMention, string $mentionTitle, string $mentionId): void
    {

        // do standard stuff
        $this->standardHandle($message, $fullMention, $mentionTitle, $mentionId);

        // first verify if the action history already have this message (for message edits), and skip
        if ($this->dataController()->isActionMentioned((int)$mentionId, $message->getId())) {
            return;
        }

        // record the action history
        $history = ActionHistory::create((int)$mentionId, $message->getUserId(), 'mentioned', [
            'message_id' => $message->getId(),
        ]);
        $this->dataController()->createActionHistory($history);

    }
}