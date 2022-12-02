<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Action;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ActionOpException;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\HttpException;
use CodeLathe\Core\Exception\NotFoundException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\UnprocessableEntityException;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ActionCreatedEvent;
use CodeLathe\Core\Messaging\Events\ActionDeletedEvent;
use CodeLathe\Core\Messaging\Events\ActionMovedEvent;
use CodeLathe\Core\Messaging\Events\ActionUpdatedEvent;
use CodeLathe\Core\Messaging\Events\ASEvent;
use CodeLathe\Core\Messaging\Events\NotifyAlert;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\Alert;
use CodeLathe\Core\Objects\AlertIssuer;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Objects\UserAction;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ActionOperations
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FileOperations
     */
    protected $fOps;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var GlobalAuthContext
     */
    protected $globalAuthContext;

    protected $chatOps;

    protected $normalizedObjectFactory;
    /**
     * @var ConfigRegistry
     */
    private $config;
    /**
     * @var MailerServiceInterface
     */
    private $mailer;

    /**
     * ActionOperations constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param GlobalAuthContext $globalAuthContext
     * @param ChatOperations $chatOperations
     * @param NormalizedObjectFactory $normalizedObjectFactory
     * @param ConfigRegistry $config
     * @param MailerServiceInterface $mailer
     */
    public function __construct(DataController $dataController,
        LoggerInterface $logger,
        FileOperations $fOps,
        EventManager $eventManager,
        GlobalAuthContext $globalAuthContext,
        ChatOperations $chatOperations,
        NormalizedObjectFactory $normalizedObjectFactory,
        ConfigRegistry $config,
        MailerServiceInterface $mailer)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->globalAuthContext = $globalAuthContext;
        $this->chatOps = $chatOperations;
        $this->normalizedObjectFactory = $normalizedObjectFactory;
        $this->config = $config;
        $this->mailer = $mailer;
    }

    /**
     * Create action
     *
     * @param int $channelId
     * @param string $actionName
     * @param string|null $desc
     * @param int $actionType
     * @param int $actionStatus
     * @param string|null $dueOn
     * @param int $createdBy
     * @param array $userIds
     * @param int|null $parentId
     * @return Action
     * @throws ActionOpException
     */
    public function createAction(int $channelId,
                                 string $actionName,
                                 ?string $desc,
                                 int $actionType,
                                 int $actionStatus,
                                 ?string $dueOn,
                                 int $createdBy,
                                 array $userIds,
                                 ?int $parentId): Action
    {
        try {

            // sanitize the action name/desc (remove html tags)
            $actionName = htmlentities($actionName);
            if ($desc !== null) {
                $desc = htmlentities($desc);
            }

            // TODO - Replace with the new authorization system
            $authContext = $this->globalAuthContext->getEffectivePermission($createdBy,
                GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $channelId);

            if ($authContext < GlobalAuthContext::AUTH_WRITE) {
                throw new ActionOpException(403, "Unauthorized");
            }

            // user have write access to the channel, but he have action write access?
            $channelUser = $this->dataController->getUserChannel($channelId, $createdBy);
            if ($channelUser->getUserRole() < ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI) {
                throw new ActionOpException(403, "Unauthorized");
            }

            // 1.0 has no team support. Everything is with default team
            $channel = $this->dataController->getChannelById($channelId);
            if (empty($channel)) {
                throw new ChannelOpException("Channel not found for $channelId");
            }

            if (count($userIds)>0) {
                $users = $this->dataController->getUsersForChannel($channelId);
                $channelUserIds = [];
                foreach ($users as $user) {
                    $channelUserIds[] = $user['id'];
                }

                $this->logger->info(print_r($userIds,true));
                // check if assignees are subset of users already in channel
                if (array_intersect($userIds, $channelUserIds) != $userIds) {
                    throw new ChannelOpException("Invalid assignees for channel " . $channel->getName());
                }
            }

            if (isset($dueOn) && strtotime($dueOn) === false){
                throw new ActionOpException(422, "Invalid Due Date");
            }

            // calculate action position
            $positionIndex = $this->dataController->actionLastPositionIndex($channelId, $parentId);

            if ($parentId !== null) {
                /** @var Action|null $parentAction */
                $parentAction = $this->dataController->getActionById($parentId);
                if ($parentAction === null) {
                    throw (new ActionOpException(400, 'Invalid parent action'));
                }
                if ($parentAction->getParentId() !== null) {
                    throw new ActionOpException(400, 'Only 1 level of nesting is allowed for actions');
                }
            }

            $this->logger->info("CREATING ACTION " . $actionName);
            $action = Action::create(
                $channelId,
                $actionName,
                $desc,
                $actionType,
                $actionStatus,
                $dueOn,
                $createdBy,
                $positionIndex,
                $parentId
            );
            if (!$this->dataController->createAction($action)) {
                throw new ActionOpException(500,"Failed creating action");
            }

            foreach ($userIds as $userId) {
                $userAction = UserAction::create($action->getId(), $userId, $createdBy);
                if (!$this->dataController->addUserAction($userAction)) {
                    throw new ActionOpException(500,"Failed adding user " . $userId . "to action " . $action->getId());
                }
            }

            // Publish this event for foreground and background users
            $event = new ActionCreatedEvent($action);
            $this->eventManager->publishEvent($event);

            $this->raiseActionAddedBotMessage($createdBy, $action, $channelId);

            return $action;

        } catch(HttpException $e){
            $this->logger->error(__CLASS__ . " " . __FUNCTION__ . $e->getTraceAsString());
            throw new ActionOpException($e->getHttpCode(), $e->getMessage());
        } catch(ASException $e) {
            $this->logger->error(__CLASS__ . " " . __FUNCTION__ . $e->getTraceAsString());
            throw new ActionOpException(400, $e->getMessage());
        }
    }


    /**
     * Get action detail for an action
     *
     * @param int $actionId
     * @param int $requestedBy
     * @return Action|null
     * @throws ActionOpException
     * @throws DatabaseException
     */
    public function getAction(int $actionId, int $requestedBy) : ?Action
    {
        $action = $this->dataController->getActionWithUsers($actionId);
        if (empty($action)) {
            throw new ActionOpException(404,"Action not found");
        }
        $authContext = $this->globalAuthContext->getEffectivePermission($requestedBy, GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $action->getChannelId());
        if ($authContext < GlobalAuthContext::AUTH_READ ) {
            throw new ActionOpException(403,"Unauthorized");
        }

        $childrenRecords = $this->dataController->getActions($action->getChannelId(), null, $action->getId());
        foreach ($childrenRecords as $childRecord) {
            $action->addChild($this->dataController->getActionWithUsers((int)$childRecord['id']));
        }

        return $action;
    }

    /**
     * Update an action
     *
     * @param int $actionId
     * @param string $actionName
     * @param string|null $desc
     * @param int $actionType
     * @param int $actionStatus
     * @param string|null $dueOn
     * @param int $updatedBy
     * @param array $assigneeIds
     * @return Action
     * @throws ActionOpException
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function updateAction(int $actionId,
                                 string $actionName,
                                 ?string $desc,
                                 int $actionType,
                                 int $actionStatus,
                                 ?string $dueOn,
                                 int $updatedBy,
                                 array $assigneeIds,
                                 ?int $channelId = null): Action
    {

        // sanitize the action name/desc (remove html tags)
        $actionName = htmlentities($actionName);
        if ($desc !== null) {
            $desc = htmlentities($desc);
        }

        $initialAction = $this->dataController->getActionById($actionId);
        if (empty($initialAction)) {
            throw new ActionOpException(404,"Action not found");
        }
        $initialActionWithUsers =$this->dataController->getActionWithUsers($actionId);
        $authContext = $this->globalAuthContext->getEffectivePermission($updatedBy, GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $initialAction->getChannelId());
        if ($authContext < GlobalAuthContext::AUTH_WRITE ) {
            throw new ActionOpException(403,"Unauthorized");
        }
        $channelId = $channelId ?? $initialAction->getChannelId();
        $users = $this->dataController->getUsersForChannel($channelId);
        $channelUserIds = [];
        foreach($users as $user){
            $channelUserIds[] = $user['id'];
        } // check if assignees are subset of users already in channel
        if (array_intersect($assigneeIds, $channelUserIds) != $assigneeIds) {
            throw new ChannelOpException("Assignees do not belong to channel ");
        }
        if (isset($dueOn) && strtotime($dueOn) === false){
            throw new ActionOpException(400,"Invalid Due Date");
        }

        // prevent moving subtasks to another channel
        if ($initialAction->getChannelId() !== $channelId && $initialAction->getParentId() !== null) {
            throw new ActionOpException(400, 'Subtasks can\'t be moved to another channel');
        }

        $updatedAction = clone $initialAction;
        $updatedAction->setChannelId($channelId);
        $updatedAction->setName($actionName);
        $updatedAction->setDesc($desc);
        $updatedAction->setActionType($actionType);
        $updatedAction->setActionStatus($actionStatus);
        $updatedAction->setDueOn($dueOn);
        $updatedAction->setUpdatedBy($updatedBy);
        $updatedAction->setUpdatedOn(date('Y-m-d H:i:s'));

        if (!$this->dataController->updateAction($updatedAction)) {
            throw new ChannelOpException("Update failed for action " . $actionName);
        }
        $kids = $this->dataController->getActionKids($updatedAction->getId());
        if ($initialAction->getChannelId() !== $updatedAction->getChannelId()) {
            foreach ($kids as $row) {
                $child = Action::withDBData($row);
                $child->setChannelId($channelId);
                $this->dataController->updateAction($child);
                $this->dataController->deleteUserActions($child->getId());
            }
        }

        $this->dataController->deleteUserActions($updatedAction->getId());
        foreach($assigneeIds as $userId){
            $userAction = UserAction::create($updatedAction->getId(),$userId, $updatedBy);
            if(!$this->dataController->addUserAction($userAction)) {
                throw new ActionOpException( 500,"Failed adding user ".$userId . "to action ".$updatedAction->getId());
            }
        }
        $updatedActionWithUsers =$this->dataController->getActionWithUsers($actionId); // Publish this event for foreground and background users
        $event = new ActionUpdatedEvent($initialActionWithUsers, $updatedActionWithUsers);
        $this->eventManager->publishEvent($event); //$this->raiseActionUpdatedBotMessage($updatedBy, $updatedActionWithUsers, (int)$event->getAssociatedChannelId());
        return $updatedAction;
    }


    /**
     * @param int $actionCreatorId
     * @param Action $action
     * @param string $opText
     * @param string $i18nKey
     * @param array $i18nParams
     * @return string
     * @throws DatabaseException
     */
    public function createMessageText(int $actionCreatorId, Action $action, string $opText="created", &$i18nKey = '', &$i18nParams = []): string
    {
        $this->logger->info(__FUNCTION__ . " : " . $action->getName() . " Type = " . $action->getActionType() . " : " . $action->getActionTypeDesc());
        $channel = $this->dataController->getChannelById($action->getChannelId());

        $type = "action";
        $i18nKey = "bot.action_$opText";
        $trailer = "";
        if (Action::ACTION_TYPE_REMINDER == $action->getActionType()) {
            $type = "reminder";
            $i18nKey = "bot.action_reminder_$opText";

            if (!empty($action->getDueOn())) {
                $i18nKey = "bot.action_reminder_{$opText}_due";
                $ts = strtotime($action->getDueOn());
                $trailer .= " due on (timestamp://$ts)";
                $i18nParams = [
                    'due' => $ts
                ];
            }
        }
        else if (Action::ACTION_TYPE_REVIEW == $action->getActionType()) {
            $type = "review";
            $i18nKey = "bot.action_review_$opText";
        }
        else if (Action::ACTION_TYPE_UPDATE == $action->getActionType()) {
            $type = "update-action";
            $i18nKey = "bot.action_update_$opText";
        }
        else if (Action::ACTION_TYPE_SIGN == $action->getActionType()) {
            $i18nKey = "bot.action_sign_$opText";
            $type = "sign-action";
        }

        $creator = $this->dataController->getUserById($actionCreatorId);

        if (!empty($creator)) {
            $botMessage = "[{$creator->getDisplayName()}](user://{$creator->getId()}) has {$opText} a {$type} [{$action->getName()}](action://{$action->getId()}) {$trailer} in [{$channel->getName()}](channel://{$channel->getId()})";
            $i18nParams = array_merge($i18nParams, [
                'user_name' => $creator->getDisplayName(),
                'user_id' => $creator->getId(),
                'action_name' => $action->getName(),
                'action_id' => $action->getId(),
                'channel_name' => $channel->getName(),
                'channel_id' => $channel->getId(),
            ]);
        } else {
            $botMessage = "(Deleted user) created new $type [{$action->getName()}](action://{$action->getId()}) {$trailer} in [{$channel->getName()}](channel://{$channel->getId()})";
            $i18nParams = array_merge($i18nParams, [
                'action_name' => $action->getName(),
                'action_id' => $action->getId(),
                'channel_name' => $channel->getName(),
                'channel_id' => $channel->getId(),
            ]);
        }

        return $botMessage;

    }
    /**
     * @param int $actionCreatorId
     * @param Action $action
     * @param int $channelId
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function raiseActionAddedBotMessage(int $actionCreatorId, Action $action, int $channelId): void
    {
        $i18nParams = [];
        $botMessage = $this->createMessageText($actionCreatorId, $action, "created", $i18nKey, $i18nParams);
        $botEvent = MessageBot::create(2, $botMessage, $i18nKey, null, $i18nParams);
        $this->chatOps->raiseBotNotification($botEvent, $channelId);
    }

    /**
     * @param int $actionUpdaterId
     * @param Action $action
     * @param int $channelId
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function raiseActionUpdatedBotMessage(int $actionUpdaterId, Action $action, int $channelId): void
    {
        $i18nParams = [];
        $botMessage = $this->createMessageText($actionUpdaterId, $action, "updated", $i18nKey, $i18nParams);
        $botEvent = MessageBot::create(2, $botMessage, $i18nKey, null, $i18nParams);
        $this->chatOps->raiseBotNotification($botEvent, $channelId);
    }

    /**
     * @param int $actionUpdaterId
     * @param Action $action
     * @param int $channelId
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function raiseActionDeletedBotMessage(int $actionUpdaterId, Action $action, int $channelId): void
    {
        $i18nParams = [];
        $botMessage = $this->createMessageText($actionUpdaterId, $action, "deleted", $i18nKey, $i18nParams);
        $botEvent = MessageBot::create(2, $botMessage, $i18nKey, null, $i18nParams);
        $this->chatOps->raiseBotNotification($botEvent, $channelId);
    }


    /**
     * Get action detail for an action
     *
     * @param int $actionId
     * @param int $requestedBy
     * @return void
     * @throws ActionOpException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function deleteAction(int $actionId, int $requestedBy) : void
    {
        $action =   $this->dataController->getActionById($actionId);
        if (empty($action)) {
            throw new ActionOpException(404,"Action not found"  );
        }

        $authContext = $this->globalAuthContext->getEffectivePermission($requestedBy, GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $action->getChannelId());
        if ($authContext < GlobalAuthContext::AUTH_WRITE ) {
            throw new ActionOpException(401,"Unauthorized" );
        }


        try {

            if ($this->dataController->deleteUserActions($actionId) < 0) {
                $this->logger->error("Failed deleting action!");
                throw new ActionOpException(500,"Failed deleting user action" );
            }

            if (!$this->dataController->deleteAction($actionId)) {
                $this->logger->error("Failed deleting action!");
                throw new ActionOpException(500,"Failed deleting action" );
            }
        }
        catch (\PDOException $ex) {
            $this->logger->error("DB failure while deleting action!" . $ex->getMessage());
            throw new ActionOpException(500,"Failed deleting action" );
        }

        $event = new ActionDeletedEvent($action);
        $this->eventManager->publishEvent($event);

        $this->raiseActionDeletedBotMessage($requestedBy, $action, (int)$action->getChannelId());
    }

    /**
     * Move action
     * @param User $movedBy
     * @param int $actionId
     * @param int|null $parentActionId
     * @param int|null $afterActionId
     * @throws DatabaseException
     * @throws NotFoundException
     * @throws UnprocessableEntityException
     */
    public function moveAction(User $movedBy, int $actionId, ?int $parentActionId, ?int $afterActionId): void
    {

        // check if the action exists
        $action = $this->dataController->getActionById($actionId);
        if ($action === null) {
            throw new NotFoundException('Action not found');
        }

        $parentAction = null;
        $afterAction = null;

        // if after action is set...
        if ($afterActionId !== null) {

            // ... check if it exists
            $afterAction = $this->dataController->getActionById($afterActionId);
            if ($afterAction === null) {
                throw new NotFoundException('After action not found');
            }

            // ... check if it is on the same channel
            if ($action->getChannelId() !== $afterAction->getChannelId()) {
                throw new UnprocessableEntityException('After-action and the moved action must belong to the same channel');
            }

            // ... and there is no parent id, set the parent id to the after-action parent id (in the end action and after-action must be siblings)
            if ($parentActionId === null) {
                $parentActionId = $afterAction->getParentId();
            }
        }

        // if parent action is set...
        if ($parentActionId !== null) {

            // ... check if it exists
            $parentAction = $this->dataController->getActionById($parentActionId);
            if ($parentAction === null) {
                throw new NotFoundException('Parent action not found');
            }

            // ... check if it is on the same channel
            if ($action->getChannelId() !== $parentAction->getChannelId()) {
                throw new UnprocessableEntityException('Parent action and the moved action must belong to the same channel');
            }

            // ... parent action can't have a parent (one level control)
            if ($parentAction->getParentId() !== null) {
                throw new UnprocessableEntityException('Just one nested level is allowed');
            }

            // ... moved action can't have kids (one level control)
            if (!empty($this->dataController->getActionKids($action->getId()))) {
                throw new UnprocessableEntityException('Just one nested level is allowed');
            }

        }

        if ($afterAction !== null && $parentAction !== null && $afterAction->getParentId() !== $parentAction->getId()) {
            throw new UnprocessableEntityException('Inconsistent parent and after actions');
        }

        $positionIndex = $this->dataController->actionFindPositionIndex($action->getChannelId(), $afterActionId, $parentActionId);

        $this->dataController->actionMove($actionId, $parentActionId, $positionIndex);

        $event = new ActionMovedEvent($action, $movedBy->getId(), $afterActionId, $parentActionId, $action->getParentId());
        $this->eventManager->publishEvent($event);

    }

    /**
     * Called to send reminder notifications
     * @throws DatabaseException
     * @throws ChatOpException
     * @throws \Exception
     */
    public function onCron() : void
    {
        // Look up all events with the following criteria
        $from = date("Y-m-d H:i:s");
        $to = date("Y-m-d H:i:s",strtotime(date("Y-m-d H:i:s")." +5 minutes"));

        $actionsRec = $this->dataController->getRemindersDueBetween($from, $to);

        if (!empty($actionsRec)) {
            foreach ($actionsRec as $rec)
            {
                $action = $this->dataController->getActionWithUsers($rec['id']);

                //TODO: This needs to be updated with more control on notifications
                // Generate alert and email for this

                $fromDate = new \DateTime(($from));
                $toDate = new \DateTime($action->getDueOn());

                $difference = $toDate->diff($fromDate);


                $message = '['.$action->getName().'](action://'.$action->getId().') is due in ' . $difference->format("%i minutes ");
                $this->logger->info(__FUNCTION__ . " : " . $message);
                $this->sendAlert($action, $message, true);

                $minutes = (int) $difference->format('%i');
                $botEvent = MessageBot::create(2, $message, 'bot.due_in', $minutes, [
                    'action_name' => $action->getName(),
                    'action_id' => $action->getId(),
                    'minutes' => $minutes,
                    'channel_id' => $action->getChannelId(),
                ]);
                $this->chatOps->raiseBotNotification($botEvent, $action->getChannelId());

            }
        }
    }

    /**
     * Send email and alert notifications
     * @param ASEvent $event
     */
    public function notify(ASEvent $event) : void
    {

        try {
            if ($event instanceof ActionCreatedEvent) {
                $action = $this->dataController->getActionWithUsers($event->getActionId());

                $this->sendNewActionEmail($event);
                $text = $this->createMessageText((int)$action->getCreatedBy(), $action);
                $this->sendAlert($action, $text);
            }

            if ($event instanceof ActionUpdatedEvent) {
                $action = $this->dataController->getActionWithUsers($event->getActionId());

                $this->sendUpdatedActionEmail($event);
                $text = $this->createMessageText((int)$action->getCreatedBy(), $action, 'updated');
                $this->sendAlert($action, $text);
            }

            if ($event instanceof ActionDeletedEvent) {
                $action = $this->dataController->getActionWithUsers($event->getActionId());
                // Do we need to send any notification?
            }
        }
        catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__ . "Exception caught: " . $ex->getMessage());
        }
    }


    /**
     * Create and send action email to action assignees
     *
     * @param ActionCreatedEvent $event
     * @throws DatabaseException
     */
    private function sendNewActionEmail(ActionCreatedEvent $event): void
    {
        $action = $this->dataController->getActionWithUsers($event->getActionId());
        $channel = $this->dataController->getChannelById($action->getChannelId());
        if (empty($channel)) {
            // Creator is not found. Malformed . ignore
            $this->logger->error("Channel id ". $action->getChannelId() ." not found. Skipping action email");
            return;
        }

        $creatorId = (int)$action->getCreatedBy();
        $creator = $this->dataController->getUserById($creatorId);
        if (empty($creator)) {
            // Creator is not found. Malformed . ignore
            $this->logger->error("Creator id $creatorId not found. Skipping action email");
            return;
        }

        $users = $action->getUserObjects();

        if (!empty($users)) {
            $channelUrl = rtrim($this->config->get('/app/ui/baseurl'), '/') . "/channel/" . $action->getChannelId();
            $subject = $creator->getDisplayName() . " has created a new action for you in AirSend!";

            foreach ($users as $user) {
                $body = "<p>" . $creator->getDisplayName() . " has created a new Action. </p>";
                $body .= "<p><b>" . $action->getName() . "</b></p>";
                if (!empty($desc = $action->getDesc())) {
                    $body .= "<p> Here is a brief description of the action </p>";
                    $body .= "<p><i>$desc</i></p>";
                }

                $this->sendActionEmailNotification($user, $channelUrl, $subject, $body, $channel->getName());
            }
        }
    }

    /**
     * Create and send updated action email to action assignees
     * @param ActionUpdatedEvent $event
     * @throws DatabaseException
     */
    private function sendUpdatedActionEmail(ActionUpdatedEvent $event): void
    {
            $action = $this->dataController->getActionWithUsers($event->getActionId());
            $channel = $this->dataController->getChannelById($action->getChannelId());
            if (empty($channel)) {
                // Creator is not found. Malformed . ignore
                $this->logger->error("Channel id " . $action->getChannelId() . " not found. Skipping action email");
                return;
            }

            $creatorId = (int)$action->getUpdatedBy();
            $creator = $this->dataController->getUserById($creatorId);
            if (empty($creator)) {
                // Creator is not found. Malformed . ignore
                $this->logger->error("Creator id $creatorId not found. Skipping action email");
                return;
            }

            $users = $action->getUserObjects();
            if (!empty($users)) {
                $channelUrl = rtrim($this->config->get('/app/ui/baseurl'), '/') . "/channel/" . $action->getChannelId();
                $subject = $creator->getDisplayName() . " has updated your action in AirSend!";
                $body = "<p>We thought you might like to know that " . $creator->getDisplayName(
                    ) . " has updated an action. </p>";
                $body .= "<p><b>" . $action->getName() . "</b></p>";
                foreach ($users as $user) {
                    $this->sendActionEmailNotification($user, $channelUrl, $subject, $body, $channel->getName());
                }
            }

            // Notify creator if the channel is updated by someone else
            if ($action->getUpdatedBy() != $action->getCreatedBy()) {
                $user = $this->dataController->getUserById((int)$action->getCreatedBy());
                if (!empty($user)) {
                    $this->sendActionEmailNotification($user, $channelUrl, $subject, $body, $channel->getName());
                }
            }
    }

    /**
     * Send an email
     *
     * @param User $user
     * @param string $url
     * @param string $subject
     * @param string $body
     * @param string $channelName
     */
    private function sendActionEmailNotification(User $user, string $url, string $subject, string $body, string $channelName): void
    {
        // ... send password recovery email

        $body_after_button = "<p>You can access the channel <b>$channelName</b>  to view and work on this action.</p>";

        try {

            $this->logger->info("Sending action email to " . $user->getEmail());

            $message = $this->mailer
                ->createMessage($user->getDisplayName() . " <" . $user->getEmail() . ">")
                ->subject($subject)
                ->from("noreply", "AirSend")
                ->body('general_template', [
                    'subject' => $subject,
                    'display_name' => $user->getDisplayName(),
                    'byline_text' => '',
                    'html_body_text' => $body,
                    'html_body_after_button_text' => $body_after_button,
                    'button_url' => $url,
                    'button_text' => "View Action"
                ]);
            $this->mailer->send($message);
        }
        catch(ASException $e){
            $this->logger->error(__CLASS__  .  " " . __FUNCTION__ . " " . $e->getMessage());
        }

    }

    /**
     * @param Action $action
     * @param string $text
     * @param bool $alertCreator
     * @throws DatabaseException
     */
    private function sendAlert(Action $action, string $text, bool $alertCreator = false): void
    {
        if (empty($text)) {
            $text = "Action (" . $action->getName() . ") needs your attention";
        }

        $createdByUser = $this->dataController->getUserById((int)$action->getCreatedBy());
        // Deleted user. Ignore
        if (empty($createdByUser)) {
            return;
        }

        // One alert per user assigned
        $users = $action->getUserObjects();
        if ($alertCreator) {
            $users[] = $createdByUser;
        }
        if (!empty($users)) {
            foreach ($users as $user) {

                $alert = Alert::create((int)$user->getId(), $action->getId(), Alert::CONTEXT_TYPE_ACTION, $text,
                                       AlertIssuer::create((int)$action->getCreatedBy(), $createdByUser->getDisplayName()), Alert::ALERT_TYPE_ACTION);

                $this->dataController->upsertAlert($alert);
                $event = new NotifyAlert($alert);
                $this->eventManager->publishEvent($event);
            }
        }
    }


}