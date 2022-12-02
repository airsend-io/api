<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

use CodeLathe\Service\Mailer\Exceptions\ImpossibleToParseReceivedEmail;
use CodeLathe\Service\Mailer\Exceptions\MessageNotSentException;

interface EmailHandlerInterface
{
    /**
     * @param EmailMessageInterface $message
     * @throws MessageNotSentException
     */
    public function send(EmailMessageInterface $message): void;

    /**
     * Parses the received request from the email service.
     *
     * Email services usually have the ability to receive emails and send the email content inside a api payload.
     *
     * This method parses the requestBody sent by the email service, returning an array in the format bellow:
     * ```
     * [$sender:string, $recipients:string[], $rawEmail:string]
     * ```
     * `sender` is the email address (without display name) from the sender
     * `recipients` is an array containing the email addresses (without display name) of the recipients of the message
     * `rawEmail` is the raw content of the sent email (entire email text, including headers and body)
     *
     * @param string $requestBody
     * @return array
     * @throws ImpossibleToParseReceivedEmail
     */
    public function receive(string $requestBody): array;
}