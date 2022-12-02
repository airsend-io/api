<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Messaging\MessageQueue\MQProducer;
use CodeLathe\Core\Messaging\MessageQueue\NodeUtilMessage;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelFileResource;
use CodeLathe\Core\Objects\FolderProps;
use CodeLathe\Core\Objects\Resource;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use DateTime;
use Exception;
use PhpMimeMailParser\Parser as MailParser;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class EmailManager
{

    /**
     * @var ChatOperations
     */
    protected $chatOperations;

    /**
     * @var FileOperations
     */
    protected $fileOperations;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * @var MailParser
     */
    protected $mailParser;

    /**
     * @var MQProducer
     */
    protected $mqProducer;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * EmailManager constructor.
     * @param ChatOperations $chatOperations
     * @param FileOperations $fileOperations
     * @param LoggerInterface $logger
     * @param DataController $dataController
     * @param ServiceRegistryInterface $config
     * @param MailParser $mailParser
     * @param MQProducer $mqProducer
     * @param MailerServiceInterface $mailer
     */
    public function __construct(ChatOperations $chatOperations,
                                FileOperations $fileOperations,
                                LoggerInterface $logger,
                                DataController $dataController,
                                ServiceRegistryInterface $config,
                                MailParser $mailParser,
                                MQProducer $mqProducer,
                                MailerServiceInterface $mailer)
    {
        $this->chatOperations = $chatOperations;
        $this->fileOperations = $fileOperations;
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->config = $config;
        $this->mailParser = $mailParser;
        $this->mqProducer = $mqProducer;
        $this->mailer = $mailer;
    }

    /**
     * Receive the request and send the payload the to mailer service
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws DatabaseException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws Exception
     */
    public function receive(RequestInterface $request, ResponseInterface $response)
    {

        // always return an empty 204, doesn't matter if the message is valid or not (nobody will check this)
        $response = JsonOutput::success()->withhttpCode(204)->write($response);

        $requestBody = trim($request->getBody()->getContents());

        //$this->logger->debug('Received on `email.receive`: ' . $requestBody);

        // extract sender, recipients and the raw email text
        [$sender, $recipients, $rawEmail] = $this->mailer->parseReceivedMessage($requestBody);

        $this->logger->debug("Received email message (from: $sender / to: [" . implode(',', $recipients) . "])...");

        // parse channel email and token
        $suffix = $this->config->get('/notifications/sender_suffix');
        $suffix = empty($suffix) ? '' : preg_quote(".$suffix", '#');
        $domain = preg_quote($this->config->get('/mailer/response_domain'), '#');
        $pattern = "#^([^+]+)$suffix(?:\+([^@_]+))?@$domain$#";

        foreach ($recipients as $recipient) {
            if (preg_match($pattern, $recipient, $matches)) {
                $channelEmail = $matches[1];
                $channel = $this->dataController->getChannelByEmail($channelEmail);
                if ($channel !== null) {
                    $channelId = $channel->getId();
                    $token = $matches[2] ?? ''; // token may not exists at this point
                    break;
                }
            }
        }

        // impossible to find a channel, log and return
        if (!isset($channelId)) {
            $this->logger->debug("The recipient email is not a valid receiving address...");
            return $response;
        }

        $this->logger->debug("Message sent by email to channel `{$channel->getName()}`");

        // if the token wasn't on the recipient address, try to find it on the message body (the reply-to header is not working)
        if (empty($token)) {

            // search for the link that allow read-only access to the channel
            if (!preg_match("#/channel/$channelId\?token=([a-zA-Z0-9]+)#", $rawEmail, $matches)) {
                $this->logger->debug("No token found on the message. Ignoring...");
                return $response;
            }
            $token = $matches[1];
        }

        $this->logger->debug("Found a token on the message: $token");

        // try to get the user sender the message as an email tag, then as a read-only token
        $user = $this->findUserFromEmailTag($channel, $token) ??$this->findUserFromNotificationToken($sender, $token, $channel);
        if ($user === null) {

            // if the token is not possible to find the user, consider the token invalid, and stop
            $this->logger->debug('Token is not valid. Ignoring...');
            return $response;
        }

        $this->logger->debug("The token $token is valid for user `{$user->getDisplayName()}` on channel `{$channel->getName()}`");

        // all good, let's try to parse the raw email message, to get only the part that the user typed, and post to the channel
        $this->logger->debug("Backgrounding the message to nodeutil...");
        $this->parseAndPostMessage($user, $channel, $rawEmail);

        return $response;

    }

    /**
     * @param string $sender
     * @param string $token
     * @param Channel $channel
     * @return User|null
     * @throws DatabaseException
     */
    protected function findUserFromNotificationToken(string $sender, string $token, Channel $channel): ?User
    {
        // check if the token exists
        if (($notification = $this->dataController->getNotificationByToken($token)) === null) {
            $this->logger->debug("Token not found as a notification token");
            return null;
        }

        // check if the token belongs to the channel
        if ($notification->getChannelId() != $channel->getId()) {
            $this->logger->debug("The token was not generated from this channel.");
            return null;
        }

        // check the token expiration
        if ($notification->getExpirationTime() < (new DateTime())) {
            $this->logger->debug("Token expired.");
            return null;
        }

        // Check if the from email is a valid user
        $user = $this->dataController->getUserByEmail($sender);
        if ($user === null) {
            $this->logger->debug("Invalid user email `$sender`.");
            return null;
        }

        // check if the user is member of the channel
        if ($this->dataController->getUserChannel($channel->getId(), $user->getId()) === null) {
            $this->logger->debug("User `{$user->getDisplayName()}` is not part of the channel ``{$channel->getName()}``");
            return null;
        }

        $this->logger->debug("Found the sender user `{$user->getDisplayName()}` using the read-only token");

        return $user;

    }

    /**
     * @param Channel $channel
     * @param string $token
     * @return User|null
     * @throws DatabaseException
     */
    protected function findUserFromEmailTag(Channel $channel, string $token): ?User
    {
        if (!preg_match('/^usr-([^-]+)/', $token, $matches)) {
            $this->logger->debug('The token is not an channel_users email tag');
            return null;
        }

        $tag = $matches[1];

        $this->logger->debug("Found an email tag: `$tag`. Trying to find the user for this tag...");


        $user = $this->dataController->getUserByEmailTag($channel, $tag);
        if ($user === null) {
            $this->logger->debug("The tag is not valid for channel {$channel->getName()}");
            return null;
        }

        $this->logger->debug("Found the sender user `{$user->getDisplayName()}` using the email tag.");

        return $user;

    }

    /**
     * @param User $user
     * @param Channel $channel
     * @param string $rawEmail
     * @throws Exception
     */
    protected function parseAndPostMessage(User $user, Channel $channel, string $rawEmail): void
    {
        // first get the plain text part of the email
        $this->mailParser->setText($rawEmail);
        $plainText = $this->mailParser->getMessageBody('text');

        // if there is no plain text on the email, we ignore it (we cannot parse html only emails)
        if (empty($plainText)) {
            $this->logger->debug('No plain text part found on the email. Ignoring...');
            return;
        }

        // parse the attachments
        $attachments = $this->mailParser->getAttachments(true);
        $messageAttachments = [];
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {

                $content = $attachment->getContent();

                $this->logger->debug('----> ' . $attachment->getContentType());

                // ignore inline images smaller than 5k
                if ($attachment->getContentDisposition() === 'inline' && preg_match('/^image/i', $attachment->getContentType()) && (strlen($content) < 5 * 1024)) {
                    continue;
                }

                // generate a temporary name
                $tmpName = Directories::tmp('tmp_mail_upload_' . bin2hex(random_bytes(8)));

                // save the attachment content to the temporary file
                file_put_contents($tmpName, $content);

                // upload the file as an attachment to the channel
                if ($uploadPath = $this->fileOperations->uploadAttachmentToChannel($channel, $user, $attachment->getFilename(), $tmpName)) {
                    $messageAttachments[] = $uploadPath;
                }

            }
        }

        // backgrounding the message parsing to nodeutil (the good parsing libraries are written in node)
        $nodeUtilPayload = [
            'user_id' => $user->getId(),
            'channel_id' => $channel->getId(),
            'plainText' => $plainText,
            'attachments' => Json::encode($messageAttachments)
        ];
        $msg = new NodeUtilMessage('strip_message_from_email', $nodeUtilPayload);
        try {
            $this->mqProducer->utilNodeProduce($msg);
        } catch (Exception $e) {
            $this->logger->error(__FUNCTION__ . " : Exception: " . $e->getMessage());
        }
    }


}