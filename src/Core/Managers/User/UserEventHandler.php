<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\User;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Indexers\UserIndexer;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Messaging\Events\UserAddedToChannelEvent;
use CodeLathe\Core\Messaging\Events\UserApprovedEvent;
use CodeLathe\Core\Messaging\Events\UserCreatedEvent;
use CodeLathe\Core\Messaging\Events\UserDeletedEvent;
use CodeLathe\Core\Messaging\Events\UserFinalizedEvent;
use CodeLathe\Core\Messaging\Events\UserInvitedEvent;
use CodeLathe\Core\Messaging\Events\UserLoginEvent;
use CodeLathe\Core\Messaging\Events\UserUpdatedEvent;
use CodeLathe\Core\Messaging\Events\UserVerifiedEvent;
use CodeLathe\Core\Messaging\Events\VerificationRefreshEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Objects\UserCode;
use CodeLathe\Core\Utility\Sendy;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserEventHandler implements EventSubscriberInterface
{
    private $logger;

    private $dataController;

    private $userOps;

    private $chatOps;

    private $configRegistry;

    private $mailer;

    private $fops;

    /**
     * @var UserIndexer
     */
    protected $userIndexer;


    public function __construct(LoggerInterface $logger,
                                DataController $controller,
                                UserOperations $userOps,
                                ChatOperations $chatOps,
                                ConfigRegistry $configRegistry,
                                MailerServiceInterface $mailer,
                                FileOperations $fileOperations,
                                UserIndexer $userIndexer)
    {
        $this->logger = $logger;
        $this->dataController = $controller;
        $this->userOps = $userOps;
        $this->chatOps = $chatOps;
        $this->configRegistry = $configRegistry;
        $this->mailer = $mailer;
        $this->fops = $fileOperations;
        $this->userIndexer = $userIndexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            UserDeletedEvent::backgroundEventName() => 'onUserDeleted',
            UserUpdatedEvent::backgroundEventName() => 'onUserUpdated',
            UserCreatedEvent::backgroundEventName() => 'onUserCreated',
            UserLoginEvent::backgroundEventName() => 'onUserLogin',
            UserAddedToChannelEvent::backgroundEventName() => 'onUserAddedToChannel',
            UserApprovedEvent::backgroundEventName() => 'onUserApproved',
            UserVerifiedEvent::backgroundEventName() => 'onUserVerified',
            UserFinalizedEvent::backgroundEventName() => 'onUserFinalized',
            VerificationRefreshEvent::backgroundEventName() => 'onVerificationRefresh',

        ];
    }

    public function onUserDeleted(UserDeletedEvent $event)
    {
        $this->logger->debug(__CLASS__ . " " .  __FUNCTION__ . " " . " userId " . $event->getUser()->getId()." ".$event->getUser()->getEmail());
        $userId = $event->getUser()->getId();
        $email = $event->getUser()->getEmail();
        try {
            $this->dataController->updateUserDisplayNameInMessages($userId, "Deleted User");
            $this->logger->debug("Updated Messages by $userId with display name Deleted User");
            $this->chatOps->deleteEmoticonByUserId($userId);
            $this->logger->debug("Deleted Message Emoticons by $userId");

            // Find all teams owned by the user and the corresponding channels and mark for deletion
            $teamsToDelete = [];
            $channelsToDelete = [];
            foreach($this->dataController->getTeamUsersForUser($userId) as $teamUser)
            {
                if ($teamUser->getUserRole() == TeamUser::TEAM_USER_ROLE_OWNER)
                {
                    foreach($this->dataController->getChannelsByTeamId($teamUser->getTeamId()) as $record)
                    {
                        $channel = Channel::withDBData($record);
                        $channelsToDelete[] = $channel;
                    }
                    $teamsToDelete[] = $this->dataController->getTeamByTeamId($teamUser->getTeamId());
                }
            }

            $this->logger->debug("Teams to be deleted " . print_r($teamsToDelete,true));
            $this->logger->debug("Channels to be deleted " . print_r($channelsToDelete,true));

            // Delete all the channels that belong to the teams owned by the user
            foreach($channelsToDelete as $channel)
            {
                $this->logger->debug("Deleting Channel " . $channel->getId() . " Channel Name " . $channel->getName());
                $this->dataController->deleteChannel($channel->getId());
            }

            // delete all teams owned by the user.
            foreach($teamsToDelete as $team)
            {
                $this->logger->debug("Deleting Team " . $team->getId() . " Team Name " . $team->getName());
                $this->fops->onDeleteTeam($team);
                $this->dataController->deleteTeam($team->getId());
            }
        }
        catch(ASException $e){
            $this->logger->critical("User delete Failed for $userId " . $e->getMessage());
        }

        $this->unregisterSendyMailingList($email);
    }

    public function onUserUpdated(UserUpdatedEvent $event)
    {
        $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Calling on User Updated Event");
        $userId = $event->getUserId();
        $newUser = $event->getUserAfterUpdate();
        $oldUser = $event->getUserBeforeUpdate();
        $this->logger->debug("New User " . print_r($newUser,true));
        $this->logger->debug("Old User " . print_r($oldUser,true));

        try {
            if ($newUser->getDisplayName() != $oldUser->getDisplayName()) {
                $this->dataController->updateUserDisplayNameInMessages($userId, $newUser->getDisplayName());
                $this->logger->debug("Updated Messages by $userId with new display name " . $newUser->getDisplayName());
                $this->chatOps->updateEmoticonDisplayName($userId, $newUser->getDisplayName());
                $this->logger->debug("Updated Message Emoticons by $userId with new display name " . $newUser->getDisplayName());
            }
        }
        catch(ASException $e){
            $this->logger->critical("User updated event Failed for $userId " . $e->getMessage());
        }

        $user = $event->getUserAfterUpdate();
        $this->userIndexer->indexUser($user->getId(), $user->getDisplayName(), $user->getEmail());
    }

    public function onUserApproved(UserApprovedEvent $event)
    {
        $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Send Verification email with code to " . $event->getUser()->getEmailOrPhone());
        $userCode = $this->userOps->createVerificationCode($event->getUser());
        $this->sendVerificationCode($event->getUser(), $userCode);
    }

    public function onVerificationRefresh(VerificationRefreshEvent $event)
    {
        $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Send Verification email with code to " . $event->getUser()->getEmailOrPhone());
        $userCode = $this->userOps->createVerificationCode($event->getUser());
        $this->sendVerificationCode($event->getUser(), $userCode);
    }

    public function onUserVerified(UserVerifiedEvent $event)
    {
        $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Send Welcome email to " . $event->getUser()->getEmailOrPhone());
        $this->sendWelcomeEmail($event->getUser());
    }

    public function onUserFinalized(UserFinalizedEvent $event)
    {
        $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Send Welcome email to " . $event->getUser()->getEmailOrPhone());
        $this->sendWelcomeEmail($event->getUser());
    }

    public function onUserCreated(UserCreatedEvent $event)
    {
        $user = $event->getUser();
        $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Calling on User Created Event ".$user->getEmailOrPhone());

        if ($user->getApprovalStatus() == User::APPROVAL_STATUS_PENDING){
            $this->sendWaitingForApprovalEmail($user);
        } else if ($user->getApprovalStatus() == User::APPROVAL_STATUS_APPROVED){
            if ($user->getAccountStatus() == User::ACCOUNT_STATUS_ACTIVE){

                // already active, send welcome email
                $this->sendWelcomeEmail($user);

            } elseif ($user->getAccountStatus() == User::ACCOUNT_STATUS_PENDING_VERIFICATION) {

                // pending verification, send the verification email
                $userCode = $this->userOps->createVerificationCode($user);
                $this->sendVerificationCode($user, $userCode, $event->getFromPublicChannelId(), $event->getFromPublicChannelHash());

            }
        }

        // ... No Notification to devops on account creation at this time
        //$this->sendNewUserCreatedEmail($user);

        $this->registerSendyMailingList($user);

        $this->userIndexer->indexUser($user->getId(), $user->getDisplayName(), $user->getEmail());


    }

    public function registerSendyMailingList(User $user)
    {
        $appmode = $this->configRegistry->get('/app/mode');
        if ($appmode == "dev")
        {
            $this->logger->debug("SKIPPING Sendy Registration in Dev Mode");
            return;
        }

        $config = array(
            'api_key' => 'e4ddc7472c419cdb0f5b', //your API key is available in Settings
            'installation_url' => 'http://sendy.codelathe.com',  //Your Sendy installation
            'list_id' => '1h402vcD81d9yMkP7djECQ'
        );

        try {
            $sendy = new \CodeLathe\Core\Utility\Sendy($config);
            $results = $sendy->subscribe(array(
                'email' => $user->getEmail(),
                'name' => $user->getDisplayName()
            ));

            if (isset($results) && isset($results['status'])) {
                $this->logger->debug(print_r($results, true));
                if ($results['status'] == true) {
                    $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " Register user in Sendy OK");
                }
                else {
                    $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " Register user in Sendy FAILED" . print_r($results, true));
                }
            }
            else {
                $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " Failed to register user in Sendy");
            }
        }
        catch (\Exception $e) {
            $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " Failed to register user in Sendy ".$e->getMessage());
        }
    }

    public function unregisterSendyMailingList($email)
    {
        $appmode = $this->configRegistry->get('/app/mode');
        if ($appmode == "dev")
        {
            $this->logger->debug("SKIPPING Sendy UnRegistration in Dev Mode");
            return;
        }

        $config = array(
            'api_key' => 'e4ddc7472c419cdb0f5b', //your API key is available in Settings
            'installation_url' => 'http://sendy.codelathe.com',  //Your Sendy installation
            'list_id' => '1h402vcD81d9yMkP7djECQ'
        );

        try {
            $sendy = new \CodeLathe\Core\Utility\Sendy($config);
            $results = $sendy->unsubscribe($email);

            if (isset($results) && isset($results['status'])) {
                $this->logger->debug(print_r($results, true));
                if ($results['status'] == true) {
                    $this->logger->debug(__CLASS__ . " " . __FUNCTION__ . " UnRegister user in Sendy OK");
                }
                else {
                    $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " UnRegister user in Sendy FAILED");
                }
            }
            else {
                $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " Failed to Unregister user in Sendy");
            }
        }
        catch (\Exception $e) {
            $this->logger->error(__CLASS__ . " " . __FUNCTION__ . " Failed to Unregister user in Sendy ".$e->getMessage());
        }
    }

    public function onUserAddedToChannel(UserAddedToChannelEvent $event)
    {
        $channel = $this->dataController->getChannelById($event->channelId());
        $user = $this->dataController->getUserById($event->channelUser()->getUserId());

        // TODO: send Welcome email to customer for getting invited to channel
    }

    public function onUserLogin(UserLoginEvent $event)
    {
        $userId = $event->getUserId();
        $user = $this->dataController->getUserById($userId);
        if ($user->getAccountStatus() == User::ACCOUNT_STATUS_PENDING_VERIFICATION)
        {
            $userCode = $this->userOps->createVerificationCode($user);
            $this->sendVerificationCode($user, $userCode);
        }
    }

       public function sendUserApprovedEmail(User $user)
    {
        if (empty($user)){
            $this->logger->error("Invalid User to Send Approval Email");
        }

        try {
            // ... send user approval email
            $baseUrl = rtrim($this->configRegistry->get('/app/ui/baseurl'), '/');
            $body = "<p>Your AirSend account is active. You can login to AirSend at $baseUrl with your email and password.</p>";
            $body_after_button = "<p>AirSend allows clear and easy collaboration with anyone you want by keeping everything in one place.";
            $body_after_button .= "You can now share files, send messages, complete tasks and keep everyone in the loop.</p>";
            $body_after_button .= "<p>If you did not request to create an account, please contact support to delete your account.</p>";
            $this->logger->info("Sending user approval email to " . $user->getEmail());


            $message = $this->mailer
                ->createMessage($user->getDisplayName() . " <" . $user->getEmail() . ">")
                ->subject('AirSend: Account Active')
                ->from("noreply", "AirSend")
                ->body('general_template', [
                    'display_name' => $user->getDisplayName(),
                    'subject' => 'AirSend: Account Active',
                    'byline_text' => 'Welcome to AirSend!',
                    'html_body_text' => $body,
                    'html_body_after_button_text' => $body_after_button,
                    'button_url' => $baseUrl,
                    'button_text' => "Login to AirSend"
                ]);

            $this->mailer->send($message);
            $this->logger->info("User Account Approval Email Sent to " . $user->getEmail());
        }
        catch(ASException $ex){
            $this->logger->error($ex->getMessage());
        }
        catch(\Exception $e){
            $this->logger->error($e->getMessage());
        }

    }

    private function sendVerificationCode(User $user, UserCode $code, ?int $fromPublicChannelId = null, ?string $fromPublicChannelHash = null)
    {
        $this->logger->debug("Sending Verification Code " . $code->getCode() . " to user " . $user->getEmail());
        $baseUrl = rtrim($this->configRegistry->get('/app/ui/baseurl'), '/');
        $plainCode = strtoupper($code->getCode());
        $verifyUrl = $baseUrl. "/user.verify?" . "user=" . urlencode($user->getEmailOrPhone()) ."&verify_code=" . $plainCode;
        if ($fromPublicChannelId !== null) {
            $verifyUrl .= "&public_channel_id=$fromPublicChannelId&public_channel_hash=$fromPublicChannelHash";
        }
        $this->logger->info("Sending verification email to " . $user->getEmail());


        $message = $this->mailer
            ->createMessage($user->getDisplayName() . " <" . $user->getEmail() . ">")
            ->subject('AirSend: Confirm your email')
            ->from("noreply", "AirSend")
            ->body('email_verify_template', [
                'subject' => 'AirSend: Confirm your email',
                'display_name' => $user->getDisplayName(),
                'byline_text' => '',
                'verifyUrl' => $verifyUrl,
                'code' => $plainCode,
            ]);

        $this->mailer->send($message);
        $this->logger->info("Account verification Email Sent to " . $user->getEmail() . " with code " . $code->getCode());
    }


    private function sendWaitingForApprovalEmail(User $user)
    {
        $adminEmail = $this->configRegistry->get('/app/admin/email');
        if ($adminEmail == '')
        {
            $this->logger->error("No Admin Email configured, cannot send Waiting for Approval Email");
            return;
        }
        $baseUrl = rtrim($this->configRegistry->get('/app/ui/baseurl'), '/').':2091';
        $displayName = $user->getDisplayName();
        $emailOrPhone = $user->getEmailOrPhone();
        $body = "<p>A new account has been created in Airsend and is waiting for approval</p>";
        $body .= "<p>Name : $displayName <br/> Email : $emailOrPhone</p>";
        $body .= "<p>Make haste and approve the user quickly..</p>";
        $this->logger->info("Account Approval Email Sent to " . $adminEmail);


        $message = $this->mailer
            ->createMessage($adminEmail)
            ->subject('AirSend: Waiting for approval '.$emailOrPhone)
            ->from("noreply", "AirSend")
            ->body('general_template', [
                'subject' => 'AirSend: Waiting for approval',
                'display_name' => 'Admin',
                'byline_text' => '',
                'html_body_text' => $body,
                'html_body_after_button_text' => '<p></p>',
                'button_url' => $baseUrl,
                'button_text' => "Airsend Admin"
            ]);

        $this->mailer->send($message);
        $this->logger->info("User Waiting for Approval Email Sent to " . $adminEmail);
    }

    private function sendNewUserCreatedEmail(User $user)
    {
        $adminEmail = $this->configRegistry->get('/app/admin/email');
        if ($adminEmail == '')
        {
            $this->logger->error("No Admin Email configured, cannot send Waiting for Approval Email");
            return;
        }
        $baseUrl = rtrim($this->configRegistry->get('/app/ui/baseurl'), '/').':2091';
        $displayName = $user->getDisplayName();
        $emailOrPhone = $user->getEmailOrPhone();
        $body = "<p>A new account has been created in Airsend!</p>";
        $body .= "<p>Name : $displayName <br/> Email : $emailOrPhone</p>";
        $this->logger->info("Account Approval Email Sent to " . $adminEmail);


        $message = $this->mailer
            ->createMessage($adminEmail)
            ->subject('AirSend: New User Signup '.$emailOrPhone)
            ->from("noreply", "AirSend")
            ->body('general_template', [
                'subject' => 'AirSend: New User Signup',
                'display_name' => 'Admin',
                'byline_text' => '',
                'html_body_text' => $body,
                'html_body_after_button_text' => '<p></p>',
                'button_url' => $baseUrl,
                'button_text' => "Airsend Admin"
            ]);

        $this->mailer->send($message);
        $this->logger->info("New User Signup for Approval Email Sent to " . $adminEmail);
    }


    private function sendWelcomeEmail(User $user)
    {
        $baseUrl = rtrim($this->configRegistry->get('/app/ui/baseurl'), '/');
        $body = "<p>AirSend lets you collaborate and stay organized with the ability to keep everything in one
place.";
        $body .= "You can now have conversations, share files, complete tasks, and get work done
easily.</p>";
        $body_after_button = "<p>We wish you a great and productive week!<br/><br/>";
        $body_after_button .= "If you did not make an account, <a href=\"https://www.airsend.io/support/\" style=\"color: #0097C0; text-decoration: none;\">let us know</a> to delete your account. </p>";
        $this->logger->info("Sending user welcome email to " . $user->getEmail());


        $message = $this->mailer
            ->createMessage($user->getDisplayName() . " <" . $user->getEmail() . ">")
            ->subject('Welcome to AirSend')
            ->from("noreply", "AirSend")
            ->body('general_template', [
                'display_name' => $user->getDisplayName(),
                'subject' => 'AirSend: Welcome Email',
                'byline_text' => 'Welcome to AirSend',
                'html_body_text' => $body,
                'html_body_after_button_text' => $body_after_button,
                'button_url' => $baseUrl,
                'button_text' => "Go to My Channels"
            ]);

        $this->mailer->send($message);
        $this->logger->info("User Account welcome Sent to " . $user->getEmail());
    }
}