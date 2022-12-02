<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Call;


use Cocur\Slugify\SlugifyInterface;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\CallOpException;
use CodeLathe\Core\Exception\ChannelAuthorizationException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Managers\Action\ActionOperations;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ChannelCreatedEvent;
use CodeLathe\Core\Messaging\Events\ChannelJoinUpdateEvent;
use CodeLathe\Core\Messaging\Events\ChannelRemovedEvent;
use CodeLathe\Core\Messaging\Events\ChannelUpdateEvent;
use CodeLathe\Core\Messaging\Events\MeetingInviteAcceptEvent;
use CodeLathe\Core\Messaging\Events\MeetingInviteEvent;
use CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent;
use CodeLathe\Core\Messaging\Events\UserCreatedEvent;
use CodeLathe\Core\Messaging\Events\UserRemovedFromChannelEvent;
use CodeLathe\Core\Messaging\Events\UserUpdatedInChannelEvent;
use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\Asset;
use CodeLathe\Core\Objects\Call;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\FolderProps;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Objects\OAuth\Client;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use ZipStream\Exception\OverflowException;
use ZipStream\ZipStream;

class CallOperations
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
     * @var EventManager
     */
    protected $eventManager;


    /**
     * @var
     */
    protected $objectFactory;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    /**
     * @var
     */
    protected $globalAuthContext;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var ActionOperations
     */
    protected $actionOps;

    /**
     * @var FileController
     */
    protected $fileController;
    /**
     * @var RtmOperations
     */
    private $rtmOps;
    /**
     * @var ChannelOperations
     */
    private $channelOps;

    /**
     * ChannelOperations constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param EventManager $eventManager
     * @param NormalizedObjectFactory $objectFactory
     * @param GlobalAuthContext $globalAuthContext
     * @param ConfigRegistry $config
     * @param ChatOperations $chatOps
     * @param RtmOperations $rtmOps
     * @param ChannelOperations $channelOps
     */
    public function __construct(
        DataController $dataController,
        LoggerInterface $logger,
        EventManager $eventManager,
        NormalizedObjectFactory $objectFactory,
        GlobalAuthContext $globalAuthContext,
        ConfigRegistry $config,
        ChatOperations $chatOps,
        RtmOperations $rtmOps,
        ChannelOperations $channelOps
    ) {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->objectFactory = $objectFactory;
        $this->globalAuthContext = $globalAuthContext;
        $this->config = $config;
        $this->chatOps = $chatOps;
        $this->rtmOps = $rtmOps;
        $this->channelOps = $channelOps;
    }


    /**
     * @param User $user
     * @param Channel|null $channel
     * @param bool $isPublic
     * @param string $allowedUsers
     * @return Call
     * @throws CallOpException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws \Exception
     */
    public function createCall(User $user, ?Channel $channel = null, bool $isPublic = false, string $allowedUsers = ""): Call
    {
        $channelId = $channel ? $channel->getId() : 0;
        $call = Call::create($user->getId(), $channelId, $isPublic, $allowedUsers);

        if ($this->dataController->createCall($call)) {
            if (!$isPublic) {
                $allowedUsers = explode(',', $call->allowedUsers());

                if (empty($allowedUsers)) {
                    $this->notifyUser($call, $user, 0);
                } else {
                    foreach ($allowedUsers as $userId) {
                        $this->notifyUser($call, $user, (int)$userId);
                    }
                }

                // TODO - Remove it once the mobile apps implement the native calls
                // raise bot message
                $botMessage = "[{$user->getDisplayName()}](user://{$user->getId()}) started a new meeting, click [here to join](meeting://{$call->callHash()}).";
                $botEvent = MessageBot::create(2, $botMessage, 'bot.start_call', null, [
                    'user_name' => $user->getDisplayName(),
                    'user_id' => $user->getId(),
                    'server_address' => $call->serverAddress(),
                    'call_hash' => $call->callHash(),
                ]);
                $this->chatOps->raiseBotNotification($botEvent, $channel->getId());

            }
        }
        else {
            throw new CallOpException('Failed creating call');
        }

        return $call;
    }

    /**
     * @param Call $call
     * @param bool $isPublic
     * @param string $allowedUsers
     * @param string $rtmToken
     * @return bool
     * @throws DatabaseException
     */
    public function updateCall(Call $call,  bool $isPublic = false, string $allowedUsers = "", string $rtmToken = ""): bool
    {

        if (empty($rtmToken = $this->rtmOps->validateRtmToken($rtmToken))) {
            $this->logger->error(__FUNCTION__ . " Cannot update call without rtm token");
            return false;
        }

        if (empty($user = $this->dataController->getUserById($rtmToken->userId()))) {
            $this->logger->error(__FUNCTION__ . " User ". $rtmToken->userId() . " does not exist");
            return false;
        }

        /** @var GlobalAuthContext $globalAuthContext */
        $globalAuthContext = ContainerFacade::get(GlobalAuthContext::class);
        $auth = $this->globalAuthContext->getEffectivePermission(
            $user,
            GlobalAuthContext::CONTEXT_TYPE_CHANNEL,
            (int)$call->getChannelId()
        );

        // Now, make sure user is authorized
        if ($rtmToken->userId() != $call->getCreatorId() && $auth < GlobalAuthContext::AUTH_MANAGE) {
            $this->logger->error(__FUNCTION__ . " User ". $rtmToken->userId() . " does not have the authority to modify this call ");
            return false;
        }
        $call->setIsPublic($isPublic);
        $channelId = $call->getChannelId();
        if ($isPublic) {
            $call->setChannelId(0);
        }

        if (!empty($allowedUsers)) {
            $call->setAllowedUsers($allowedUsers);
        }

        if ($retVal = $this->dataController->updateCall($call)) {
            if ($channelId > 0) {
                $this->pushChannelUpdate($channelId);
            }
        }

        return $retVal;
    }


    /**
     * @param Call $call
     * @param string $token
     * @return User|null
     * @throws DatabaseException
     */
    public function checkUserAccess(Call $call, string $token): ?User
    {
        if (!empty($rtmToken = $this->rtmOps->validateRtmToken($token))) {
            if (!empty($call->allowedUsers())) {
                $allowedUsers = explode(',', $call->allowedUsers());
                if (in_array($rtmToken->userId(), $allowedUsers)) {
                    // User is in the allowed list
                    $this->logger->debug('User ' .  $rtmToken->userId() . ' is part of channel '  . $call->allowedUsers());
                    return $this->dataController->getUserById($rtmToken->userId());
                } else {
                    $this->logger->info($rtmToken->userId() . " is not a part of allowed user list" . $call->allowedUsers());
                }
            } else {
                // If allowed users is empty and channel is present, then we will use the channel users to validate
                if ($call->getChannelId() > 0) {
                    if (!empty($this->dataController->getUserChannel($call->getChannelId(), $rtmToken->userId()))) {
                        $this->logger->debug('User ' .  $rtmToken->userId() . ' is part of channel '  . $call->getChannelId());
                        return $this->dataController->getUserById($rtmToken->userId());
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if the rtm token is for the call owner
     * @param Call $call
     * @param string $token
     * @return bool
     */
    public function isCallOwner(Call $call, string $token): bool
    {
        if (!empty($rtmToken = $this->rtmOps->validateRtmToken($token))) {
            return $rtmToken->userId() === $call->getCreatorId();
        }

        $this->logger->error($token . " is invalid");
        return false;
    }

    /**
     * @param int $channelId
     * @throws DatabaseException
     */
    public function pushChannelUpdate(int $channelId): void
    {
        $channel = $this->dataController->getChannelById($channelId);
        if (!empty($channel)) {
            $event = new ChannelUpdateEvent($channel);
            $this->eventManager->publishEvent($event);
        }
        else {
            $this->logger->info(__FUNCTION__ . ": Channel $channelId is not a valid channel . ignoring");
        }
    }

    /**
     * @param Call $call
     * @param User $from
     * @param int $toUserId
     * @throws DatabaseException
     */
    public function notifyUser(Call $call, User $from, int $toUserId): void
    {
        $usersToNotify = [];
        if ($toUserId <= 0) {
            $users = $this->channelOps->getUsersForChannel($call->getChannelId());
            foreach ($users as $user) {
                if ($from->getId() != $user->getId()) {
                    $usersToNotify[] = $user->getId();
                }
            }
        }
        else {
            $usersToNotify[] = $toUserId;
        }

        foreach ($usersToNotify as $toId) {
            $event = new MeetingInviteEvent($call->callHash(), $from->getId(), $toId, $call->getChannelId());
            $this->eventManager->publishEvent($event);
        }
    }

    /**
     * @param string $callHash
     * @param int $userId
     * @param bool $accept
     */
    public function notifyCallAcceptUser(string $callHash, int $userId, bool $accept=true): void
    {
        $event = new MeetingInviteAcceptEvent($callHash, $userId, $accept);
        $this->eventManager->publishEvent($event);
    }
}
