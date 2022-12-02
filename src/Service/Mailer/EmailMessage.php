<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\Exceptions\InvalidFromAddressException;
use CodeLathe\Service\ServiceRegistryInterface;

/**
 * Class PendingEmail
 *
 * This class represents a pending email. A pending email basically holds email header information.
 *
 * @package CodeLathe\Service\Email\EmailSender
 */
class EmailMessage implements EmailMessageInterface
{

    /**
     * @var RecipientInterface[]
     */
    protected $to;

    /**
     * @var RecipientInterface[]
     */
    protected $cc = [];

    /**
     * @var RecipientInterface[]
     */
    protected $bcc = [];

    /**
     * @var string
     */
    protected $from = '';

    /**
     * @var string
     */
    protected $subject = '';

    /**
     * @var string
     */
    protected $plainBody = '';

    /**
     * @var string
     */
    protected $htmlBody = '';

    /**
     * @var string
     */
    protected $fromDomain;

    /**
     * @var string
     */
    protected $replyTo;

    /**
     * @var string[]
     */
    protected $attachments = [];

    /**
     * Mail constructor.
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @param string $fromDomain
     * @throws InvalidEmailAddressException
     */
    public function __construct($recipients, string $fromDomain)
    {
        $this->fromDomain = $fromDomain;
        $this->to = $this->parseRecipients($recipients);
    }

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return RecipientInterface[]
     * @throws InvalidEmailAddressException
     */
    protected function parseRecipients($recipients): array
    {
        // force the recipients to be an array
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        // map all recipients to become RecipientInterfaces (if a recipient is a string, build an AddressRecipient
        foreach ($recipients as &$recipient) {
            $recipient = $recipient instanceof RecipientInterface ? $recipient : new AddressRecipient($recipient);
        }

        return $recipients;
    }

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return EmailMessage
     * @throws InvalidEmailAddressException
     */
    public function to($recipients): EmailMessageInterface
    {
        $this->to = $this->parseRecipients($recipients);
        return $this;
    }

    /**
     * @return RecipientInterface[]
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return EmailMessage
     * @throws InvalidEmailAddressException
     */
    public function cc($recipients): EmailMessageInterface
    {
        $this->cc = $this->parseRecipients($recipients);
        return $this;
    }

    /**
     * @return RecipientInterface[]
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return EmailMessageInterface
     * @throws InvalidEmailAddressException
     */
    public function bcc($recipients): EmailMessageInterface
    {
        $this->bcc = $this->parseRecipients($recipients);
        return $this;
    }

    /**
     * @return RecipientInterface[]
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * @param string $plainText
     * @return EmailMessageInterface
     */
    public function plain(string $plainText): EmailMessageInterface
    {
        $this->plainBody = $plainText;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlainBody(): string
    {
        return $this->plainBody;
    }

    /**
     * @param string $htmlText
     * @return EmailMessageInterface
     */
    public function html(string $htmlText): EmailMessageInterface
    {
        $this->htmlBody = $htmlText;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlBody(): string
    {
        return $this->htmlBody;
    }

    /**
     * Uses the templating system to build both html and plain bodies for the email (when both templates are defined)
     *
     * @param string $template
     * @param array $variables
     * @return EmailMessageInterface
     */
    public function body(string $template, array $variables): EmailMessageInterface
    {

        // get the templates
        $basePath = dirname(__FILE__) . "/Templates/$template.";
        $htmlTemplateFile = $basePath . 'html.php';
        $plainTemplateFile = $basePath . 'plain.php';

        // extract the variables
        extract($variables);

        // execute the templates
        if (file_exists($htmlTemplateFile)) {
            ob_start();
            require $htmlTemplateFile;
            $this->htmlBody = ob_get_clean();
        }

        if (file_exists($plainTemplateFile)) {
            ob_start();
            require $plainTemplateFile;
            $this->plainBody = ob_get_clean();
        }

        return $this;
    }

    /**
     * @param string $address
     * @param string|null $domain
     * @param string|null $name
     * @return string
     * @throws InvalidFromAddressException
     */
    protected function normalizeSenderAddress(string $address, string $domain, ?string $name = null)
    {
        $address = trim($address);

        // validate the address (cannot contain a domain name)
        if (preg_match('/[\s@]/', $address)) {
            throw new InvalidFromAddressException();
        }

        $address = "$address@{$domain}";

        if ($name !== null) {
            $name = trim($name);
            $name = preg_replace('/\s+/', ' ', $name);
            $output = "\"$name\" <$address>";
        } else {
            $output = $address;
        }
        return $output;
    }

    /**
     * @param string $name
     * @param string $address from address, without the domain
     * @return mixed
     * @throws InvalidFromAddressException
     */
    public function from(string $address, ?string $name = null): EmailMessageInterface
    {
        $this->from = $this->normalizeSenderAddress($address, $this->fromDomain, $name);
        return $this;
    }

    /**
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * @param string $subject
     * @return EmailMessageInterface
     */
    public function subject(string $subject): EmailMessageInterface
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }


    /**
     * Includes the reply-to header for the message
     *
     * @param string $address
     * @param string|null $name
     * @param string|null $domain
     * @return EmailMessageInterface
     * @throws InvalidFromAddressException
     */
    public function replyTo(string $address, ?string $name = null, ?string $domain = null): EmailMessageInterface
    {
        $this->replyTo = $this->normalizeSenderAddress($address, $domain ?? $this->fromDomain, $name);
        return $this;
    }

    /**
     * Return the reply-to header from the message
     *
     * @return string
     */
    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    /**
     * @param string $name
     * @param string $mime
     * @param string $content
     * @return EmailMessageInterface
     */
    public function attachment(string $name, string $mime, string $content): EmailMessageInterface
    {
        $this->attachments[] = new Attachment($name, $mime, $content);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}