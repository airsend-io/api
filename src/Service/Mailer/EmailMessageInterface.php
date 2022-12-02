<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;


use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;

interface EmailMessageInterface
{
    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return EmailMessage
     * @throws InvalidEmailAddressException
     */
    public function to($recipients): EmailMessageInterface;

    /**
     * @param string $address from address, without the domain
     * @param string|null $name
     * @return mixed
     */
    public function from(string $address, ?string $name = null): EmailMessageInterface;

    /**
     * @return string
     */
    public function getFrom(): string;

    /**
     * @return RecipientInterface[]
     */
    public function getTo(): array;

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return EmailMessage
     * @throws InvalidEmailAddressException
     */
    public function cc($recipients): EmailMessageInterface;

    /**
     * @return RecipientInterface[]
     */
    public function getCc(): array;

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $recipients
     * @return EmailMessageInterface
     * @throws InvalidEmailAddressException
     */
    public function bcc($recipients): EmailMessageInterface;

    /**
     * @return RecipientInterface[]
     */
    public function getBcc(): array;

    /**
     * @param string $subject
     * @return EmailMessageInterface
     */
    public function subject(string $subject): EmailMessageInterface;

    /**
     * @return string
     */
    public function getSubject(): string;

    /**
     * Defines the plain text body for the message
     *
     * @param string $plainText
     * @return EmailMessageInterface
     */
    public function plain(string $plainText): EmailMessageInterface;

    /**
     * @return string
     */
    public function getPlainBody(): string;

    /**
     * Defines the html body for the message
     *
     * @param string $htmlText
     * @return EmailMessageInterface
     */
    public function html(string $htmlText): EmailMessageInterface;

    /**
     * @return string
     */
    public function getHtmlBody(): string;

    /**
     * Uses the templating system to build both html and plain bodies for the email (when both templates are defined)
     *
     * @param string $template
     * @param array $variables
     * @return EmailMessageInterface
     */
    public function body(string $template, array $variables): EmailMessageInterface;

    /**
     * Includes the reply-to header for the message
     *
     * @param string $address
     * @param string|null $name
     * @param string|null $domain Force a different domain for the reply-to header
     * @return EmailMessageInterface
     */
    public function replyTo(string $address, ?string $name = null, ?string $domain = null): EmailMessageInterface;

    /**
     * Return the reply-to header from the message
     *
     * @return string
     */
    public function getReplyTo(): ?string;

    /**
     * @param string $name
     * @param string $mime
     * @param string $content
     * @return EmailMessageInterface
     */
    public function attachment(string $name, string $mime, string $content): EmailMessageInterface;

    /**
     * @return AttachmentInterface[]
     */
    public function getAttachments(): array;

}