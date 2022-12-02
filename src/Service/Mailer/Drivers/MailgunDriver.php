<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer\Drivers;

use CodeLathe\Service\Mailer\EmailHandlerInterface;
use CodeLathe\Service\Mailer\EmailMessageInterface;
use CodeLathe\Service\Mailer\Exceptions\MessageNotSentException;
use Mailgun\Mailgun;


class MailgunDriver implements EmailHandlerInterface
{

    use ReduceRecipientsTrait;

    protected $key;

    protected $domain;

    public function __construct(string $key, string $domain)
    {
        $this->key = $key;
        $this->domain = $domain;
    }

    /**
     * @param EmailMessageInterface $message
     * @throws MessageNotSentException
     */
    public function send(EmailMessageInterface $message): void
    {

        $options = [
            'from' => $message->getFrom(),
            'to' => $this->reduceRecipients($message->getTo()),
            'subject' => $message->getSubject(),
            'html' => $message->getHtmlBody(),
            'text' => $message->getPlainBody(),
            'h:Reply-To' => $message->getReplyTo()
        ];

        $cc = $this->reduceRecipients($message->getCc());
        $bcc = $this->reduceRecipients($message->getBcc());

        if (!empty($cc)) {
            $options['cc'] = $cc;
        }

        if (!empty($bcc)) {
            $options['bcc'] = $bcc;
        }

        $mailgunClient = Mailgun::create($this->key);

        try {
            $result = $mailgunClient->sendMessage($this->domain, $options);
        } catch (\Throwable $e) {
            throw new MessageNotSentException($e->getMessage());
        }

        if ($result->http_response_code != 200) {
            throw new MessageNotSentException($result->http_response_body->message);
        }
    }

    /**
     * @inheritDoc
     */
    public function receive(string $requestBody): array
    {
        // TODO: Implement receive() method.
    }
}