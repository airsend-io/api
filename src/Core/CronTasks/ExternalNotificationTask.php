<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\CronTasks;

use Carbon\CarbonImmutable;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Infrastructure\CriticalSection;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Notification;
use CodeLathe\Core\Utility\Markdown;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Cron\AbstractCronTask;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use CodeLathe\Service\SMS\SMSServiceInterface;
use Exception;
use Generator;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Describes a Service instance.
 */
class ExternalNotificationTask extends AbstractCronTask
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var SMSServiceInterface
     */
    protected $smsService;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ServiceRegistryInterface
     */
    protected $registry;

    /**
     * @var CriticalSection
     */
    protected $criticalSection;

    /**
     * ExternalNotificationTask constructor.
     * @param LoggerInterface $logger
     * @param MailerServiceInterface $mailerService
     * @param SMSServiceInterface $smsService
     * @param DataController $dataController
     * @param ServiceRegistryInterface $registry
     * @param CriticalSection $criticalSection
     */
    public function __construct(LoggerInterface $logger,
                                MailerServiceInterface $mailerService,
                                SMSServiceInterface $smsService,
                                DataController $dataController,
                                ServiceRegistryInterface $registry,
                                CriticalSection $criticalSection)
    {
        $this->logger = $logger;
        $this->mailer = $mailerService;
        $this->smsService = $smsService;
        $this->dataController = $dataController;
        $this->registry = $registry;
        $this->criticalSection = $criticalSection;
    }

    /**
     * Entrypoint for the execution logic of the cron job
     *
     * @return mixed
     * @throws InvalidEmailAddressException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function run(): void
    {

        // store the start time, so we can track the time that each execution is taking.
        $startTime = CarbonImmutable::now();

        // use critical section to ensure that one execution is not overlaping an previous execution
        // if it is, just skip the current executions, without waiting
        $sessionId = microtime(true) + rand(1,10000);

        $this->logger->info("EXTERNAL NOTIFICATION JOB ($sessionId) STARTED at " . $startTime->format('Y-m-d H:i:s'));

        if ($this->criticalSection->acquireSection("CRON_DIGEST", $sessionId, 0)) {

            // process the messages digest for unregistered users
            //$this->logger->debug('NOTIFICATIONS -> Searching for messages to unregistered users...');
            $this->processDigest($this->dataController->getMessageDigestDataForUnregisteredUser());

            // process the mentions digest for unregistered users
            //$this->logger->debug('NOTIFICATIONS -> Searching for mentions to unregistered users...');
            $this->processDigest($this->dataController->getMentionDigestDataForUnregisteredUser(), true);

            // process the messages digests for registered users
            //$this->logger->debug('NOTIFICATIONS -> Searching for messages to registered users...');
            $this->processDigest($this->dataController->getMessageDigestDataForRegisteredUsers());

            // process the mentions digest for registered users
            //$this->logger->debug('NOTIFICATIONS -> Searching for mentions to registered users...');
            $this->processDigest($this->dataController->getMentionDigestDataForRegisteredUsers(), true);

            $this->criticalSection->releaseSection("CRON_DIGEST", $sessionId);

            $endTime = CarbonImmutable::now();

            $this->logger->info("EXTERNAL NOTIFICATION JOB ($sessionId) FINISHED at {$endTime->format('Y-m-d H:i:s')}. Time elapsed: {$endTime->diffAsCarbonInterval($startTime)->format('%H:%I:%S')}");

        }
        else {
            $this->logger->debug("EXTERNAL NOTIFICATION JOB ($sessionId) SKIPPED");
        }
    }

    /**
     * @param iterable $digestData
     * @param bool|null $mentionDigest
     * @throws InvalidEmailAddressException
     * @throws Exception
     */
    protected function processDigest(Generator $digestData, ?bool $mentionDigest = false): void
    {

        // first normalize the digests using a hash
        $digestNormalizedData = [];
        foreach ($digestData as $item) {

            $hash = md5("{$item['channel_id']}_{$item['recipient_email']}");

            $digestNormalizedData[$hash]['channel_id'] = $item['channel_id'];
            $digestNormalizedData[$hash]['channel_name'] = $item['channel_name'];
            $digestNormalizedData[$hash]['channel_email'] = $item['channel_email'];
            $digestNormalizedData[$hash]['recipient_id'] = $item['recipient_id'];
            $digestNormalizedData[$hash]['recipient_email'] = $item['recipient_email'];

            // accumulate the messages
            $message = [
                'message_id' => $item['message_id'],
                'sender_id' => $item['sender_id'],
                'sender_name' => $item['sender_name'],
                'text' => $item['text'],
                'created_on' => $item['created_on']
            ];
            $digestNormalizedData[$hash]['messages'][] = $message;

        }

        // then send the notifications
        $count = count($digestNormalizedData);
        if ($count > 0) {
            $this->logger->debug("Sending $count notifications...");
        }
        foreach ($digestNormalizedData as $item) {

            // generates an unique token that grants access to the receiver (readonly on channel, send messages responding to the email, and other limited accesses)
            $token = Utility::uniqueToken();

            // generate the read-only link that gives access to the channel
            $uiBaseUrl = $this->registry->get('/app/ui/baseurl');
            $channelLink = "$uiBaseUrl/channel/{$item['channel_id']}?token={$token}";
            $settingsLink = "$uiBaseUrl/email-settings?token={$token}";
            $reportAbuseLink = "$uiBaseUrl/report?token={$token}&channel_name=" . urlencode($item['channel_name']) . "&reporter_email=" . urlencode($item['recipient_email']);

            $emailContent = $this->sendEmailDigest(
                $item['channel_name'],
                $item['channel_email'],
                $item['recipient_email'],
                $item['messages'],
                $token,
                $channelLink,
                $settingsLink,
                $reportAbuseLink,
                $mentionDigest);

            // TODO - Send the sms notification...

            // Save to the notifications table and to the notifications_timeline
            $messageIds = array_map(function($message) {
                return (int) $message['message_id'];
            }, $item['messages']);
            $notification = Notification::create(
                $token,
                (int)$item['recipient_id'],
                Notification::NOTIFICATION_CONTEXT_TYPE_CHANNEL,
                (int)$item['channel_id'],
                Notification::NOTIFICATION_MEDIA_TYPE_EMAIL,
                Notification::NOTIFICATION_TYPE_MESSAGE_DIGEST,
                '' // TODO - Include the email content: $emailContent
            );
            $this->dataController->createNotification($notification, $messageIds);
        }

    }

    /**
     * Returns a name that identifies the task
     *
     * @return string
     */
    public function getName(): string
    {
        return 'External notification/digest task';
    }

    /**
     * @param string $channelName
     * @param string $channelEmail
     * @param string $to
     * @param array $messages
     * @param string $token
     * @param string $channelLink
     * @param string $settingsLink
     * @param string $reportAbuseLink
     * @param bool $mentionDigest
     * @return string
     * @throws InvalidEmailAddressException
     */
    protected function sendEmailDigest(string $channelName,
                                       string $channelEmail,
                                       string $to,
                                       array $messages,
                                       string $token,
                                       string $channelLink,
                                       string $settingsLink,
                                       string $reportAbuseLink,
                                       bool $mentionDigest): string
    {

        // truncate the number of messages on the digest
        $maxMessagesOnDigest = $this->registry->get('/email_digest/max_message_count_on_digest');
        $additionalMessagesCount = 0;
        if (count($messages) > $maxMessagesOnDigest) {
            $additionalMessagesCount = count($messages) - $maxMessagesOnDigest;
            $messages = array_slice($messages, 0, $maxMessagesOnDigest);
        }

        // format messages data for the template
        $messagesData = array_map(function (array $message) use ($token, $mentionDigest) {

            $serverBaseUrl = $this->registry->get('/app/server/baseurl');
            $text = $message['text'];
            $truncated = false;

            // we don't truncate messages on mention notification email
            if (!$mentionDigest) {
                $maxMessageSize = $this->registry->get('/email_digest/max_message_size');
                if (strlen($text) > $maxMessageSize) {
                    $text = substr($text, 0, $maxMessageSize - 3) . ' ...';
                    $truncated = true;
                }
            }

            $uiBaseUrl = $this->registry->get('/app/ui/baseurl');
            $attachments = [];
            foreach ($message['attachments'] ?? [] as $item) {
                $attachment = json_decode($item['content'] ?? '', true);
                if (!empty($attachment['path'])) {
                    $attachments[$attachment['file']] = "$uiBaseUrl/channel/{$message['channel_id']}?preview={$attachment['path']}";
                }
            }

            $text = Markdown::parseMessageForEmail($text);
            return [
                'text' => $text,
                'user_name' => $message['sender_name'],
                'time' => \DateTime::createFromFormat('Y-m-d H:i:s', $message['created_on'])->format('l, g:iA'),
                'avatar' => $serverBaseUrl . "/user.image.get?user_id={$message['sender_id']}&image_class=medium&token=$token&round=1&return_fallback_image=1",
                'truncated' => $truncated,
                'attachments' => $attachments,
            ];
        }, $messages);

        // define the from header, and the reply-to header (used for automatic responding inside the channel)
        $from = $this->suffixChannelEmail($channelEmail);
        $replyTo = $from . '+' . $token;

        // generate the email message
        $subject = $mentionDigest ? "You have been mentioned in the channel {$channelName}" : "You have new messages in the channel {$channelName}";
        $this->logger->debug("Sending email to `$to`, with subject `$subject`");
        $fromName = "AirSend - $channelName";
        $emailMessage = $this->mailer
            ->createMessage($to)
            ->subject($subject)
            ->from($from, $fromName)
            ->replyTo($replyTo, $fromName, $this->registry->get('/mailer/response_domain'))
            ->body('digest_notification', [
                'subject' => $subject,
                'messages' => $messagesData,
                'channelName' => $channelName,
                'channelUrl' => $channelLink,
                'manageSettingsUrl' => $settingsLink,
                'reportAbuseUrl' => $reportAbuseLink,
                'additionalMessagesCount' => $additionalMessagesCount
            ]);

        // send it
        $this->mailer->send($emailMessage);

        return $emailMessage->getHtmlBody();
    }

    /**
     * @param string $channelEmail
     * @return string
     */
    protected function suffixChannelEmail(string $channelEmail): string
    {
        $suffix = $this->registry->get('/notifications/sender_suffix');
        if (empty($suffix)) {
            return $channelEmail;
        }
        return "{$channelEmail}.{$suffix}";
    }

    /**
     * @param Channel $channel
     * @param string $to
     * @param array $messages
     * @throws \CodeLathe\Core\Exception\DatabaseException
     */
    protected function sendSmsDigest(Channel $channel, string $to, array $messages)
    {

        // initiate the body
        $body = "You have new messages in the channel \"{$channel->getName()}\":\n";

        // creates the digest
        foreach ($messages as $message) {

            // get the sender user object
            $sender = $this->dataController->getUserById($message->getUserId());

            $body .= "{$sender->getDisplayName()}: {$message->getText()}\n";
        }

        // truncate the body
        // TODO - Include the link to the channel here, and handle the size
        if (strlen($body) > 160) {
            $body = substr($body, 0, 157) . '...';
        }

        // send the message
        $this->smsService->send($to, $body);

    }
}