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
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\User;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class UserMentionHandler implements MentionHandlerInterface
{

    use MentionHandlerTrait {
        handle as standardHandle;
    }

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var NotificationFilter
     */
    protected $notificationFilter;

    public function __construct(LoggerInterface $logger, DataController $dataController, NotificationFilter $notificationFilter)
    {
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->notificationFilter = $notificationFilter;
    }

    public function handle(Message $message, string $fullMention, string $mentionTitle, string $mentionId): void
    {

        $channel = $this->dataController->getChannelById((int)$message->getChannelId());

        // create an alert for the user @ mention
        $blurb = $this->snipBlurb($message, $mentionId);
        $blurb = "[" . $message->getDisplayName() . "](user://" . $message->getUserId() . ") has mentioned you in [" . $channel->getName() . "](channel://" . $channel->getId() . ") : " . $blurb;

        if (!empty($mentioned = $this->dataController->getUserById((int)$mentionId))) {

            // do the default mention handling
            $this->standardHandle($message, $fullMention, $mentionTitle, $mentionId);

            if ((int)$message->getUserId() === (int)$mentionId) {
                return;
            }

            $this->addAlertForUser((int)$mentionId, $message, $blurb);

        } elseif ($mentionId == 'all') {

            foreach ($this->dataController->getUsersForChannel($channel->getId()) as $userRec) {
                $matchId = (int)$userRec['id'];
                if ((int)$message->getUserId() === (int)$matchId) {
                    continue;
                }
                $this->addAlertForUser((int)$matchId, $message, $blurb);
            }

        }

    }

    /**
     * @param Message $message
     * @param string $matchId
     * @return string
     */
    protected function snipBlurb(Message $message, string $matchId): string
    {
        $msgText = $message->getText();

        // strip out all
        $msgText = preg_replace('/\[(.*?)\]\((.*?)\)/', '${1}', $msgText);


        $sniplen =  60;

        if (strlen($msgText) < $sniplen) {
            return $msgText;
        }

        $pos = strpos($msgText, $matchId);
        if ($pos > 5) {
            $end = $pos + strlen($matchId);
            if ($end + $sniplen < strlen($msgText)) {
                $end += $sniplen;
            }
            $blurb = substr($msgText, $pos - 5, $end);
            $blurb = "... $blurb ...";

        } else {
            $pos = 0;
            $end = $sniplen;
            if (strlen($msgText) < $sniplen) {
                $end = strlen($msgText);
            }
            $blurb = substr($msgText, $pos, $end);
            $blurb = $blurb . " ...";

        }


        return $blurb;
    }

    /**
     * @param int $matchId
     * @param Message $message
     * @param string $blurb
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    private function addAlertForUser(int $matchId, Message $message, string $blurb) {
        $alert = Alert::create((int)$matchId,
            $message->getId(),
            Alert::CONTEXT_TYPE_MESSAGE,
            $blurb,
            AlertIssuer::create((int)$message->getUserId(), $message->getDisplayName()),
            Alert::ALERT_TYPE_MENTION
        );

        // Check if we have this alert and ignore alert
        $existing = $this->dataController->getAlert($alert->getUserId(), $alert->getContextId(), $alert->getContextType(), $alert->getAlertText());
        if (empty($existing)) {
            $this->dataController->upsertAlert($alert);

            $this->notificationFilter->notifyAlert($alert);
        }
    }

    function resourceType(): string
    {
        return 'user';
    }

    function validateResource(int $resourceId, ?string &$error = null): bool
    {
       if ($this->dataController->getUserById($resourceId) instanceof User) {
           return true;
       }
       $error = "User $resourceId doesn't exists.";
       return false;
    }
}