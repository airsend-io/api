<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

use CodeLathe\Service\Mailer\Drivers\AwsSesDriver;
use CodeLathe\Service\Mailer\Exceptions\MessageNotSentException;
use CodeLathe\Service\Mailer\Drivers\DummyDriver;
use CodeLathe\Service\Mailer\Drivers\MailgunDriver;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailHandlerDriverException;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailHandlerDriverParamsException;
use CodeLathe\Service\ServiceInterface;

/**
 * Describes a Service instance.
 */
class MailerService implements ServiceInterface, MailerServiceInterface
{

    public function getDrivers(): array
    {
        return [
            'mailgun' => function ($senderParams, $fromDomain): EmailHandlerInterface {
                return new MailgunDriver($senderParams['key'], $fromDomain);
            },
            'aws_ses' => function ($senderParams): EmailHandlerInterface {
                return new AwsSesDriver($senderParams['key'], $senderParams['secret']);
            },
            'dummy' => function (): EmailHandlerInterface {
                return new DummyDriver();
            },
        ];
    }

    /**
     * @var EmailHandlerInterface
     */
    protected $emailHandler;

    /**
     * @var string
     */
    protected $fromDomain;

    /**
     * MailerService constructor.
     * @param string $senderDriver
     * @param array $senderParams
     * @param string $fromDomain
     * @throws InvalidemailHandlerDriverException
     * @throws InvalidemailHandlerDriverParamsException
     */
    public function __construct(string $senderDriver, array $senderParams, string $fromDomain)
    {
        $drivers = $this->getDrivers();
        if (!isset($drivers[$senderDriver])) {
            throw new InvalidemailHandlerDriverException($senderDriver);
        }

        try {
            $driver = $drivers[$senderDriver]($senderParams, $fromDomain);
        } catch (\Throwable $e) {
            throw new InvalidemailHandlerDriverParamsException($e);
        }

        $this->emailHandler = $driver;
        $this->fromDomain = $fromDomain;
    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return static::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "Email Service provides a thin wrapper around email server";
    }

    /**
     * @param RecipientInterface[]|RecipientInterface|string|string[] $to
     * @return EmailMessageInterface
     * @throws InvalidEmailAddressException
     */
    public function createMessage($to): EmailMessageInterface
    {
        return new EmailMessage($to, $this->fromDomain);
    }

    /**
     * @param EmailMessageInterface $message
     * @throws MessageNotSentException
     */
    public function send(EmailMessageInterface $message): void
    {
        // TODO - Validade message: from, subject, html and text body

        $this->emailHandler->send($message);
    }

    /**
     * @inheritDoc
     */
    public function parseReceivedMessage(string $requestBody): array
    {
        return $this->emailHandler->receive($requestBody);
    }
}