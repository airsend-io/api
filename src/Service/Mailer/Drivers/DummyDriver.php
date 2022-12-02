<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer\Drivers;

use CodeLathe\Service\Mailer\EmailHandlerInterface;
use CodeLathe\Service\Mailer\EmailMessageInterface;

class DummyDriver implements EmailHandlerInterface
{
    use ReduceRecipientsTrait;

    /**
     * @param EmailMessageInterface $message
     */
    public function send(EmailMessageInterface $message): void
    {
        $fileName = 'mail_' . date('YmdHis') . '_' . uniqid() . '.txt';
        $dir = dirname(__DIR__, 4) . '/scratch/sentemail';

        $content = '';
        $content .= "FROM: {$message->getFrom()}" . PHP_EOL;
        $content .= "TO: {$this->reduceRecipients($message->getTo())}" . PHP_EOL;

        $cc = $this->reduceRecipients($message->getCc());
        $bcc = $this->reduceRecipients($message->getBcc());
        if (!empty($cc)) {
            $content .= "CC: {$cc}" . PHP_EOL;
        }
        if (!empty($bcc)) {
            $content .= "BCC: {$bcc}" . PHP_EOL;
        }

        $content .= "SUBJECT: {$message->getSubject()}" . PHP_EOL;

        $content .= '== PLAIN TEXT CONTENT START ===========================' . PHP_EOL;
        $content .= $message->getPlainBody();
        $content .= PHP_EOL . '== PLAIN TEXT CONTENT END =============================' . PHP_EOL . PHP_EOL;

        $content .= '== HTML CONTENT START =================================' . PHP_EOL;
        $content .= $message->getHtmlBody();
        $content .= PHP_EOL . '== HTML CONTENT END ===================================' . PHP_EOL . PHP_EOL;

        file_put_contents("$dir/$fileName", $content);
    }

    /**
     * @inheritDoc
     */
    public function receive(string $requestBody): array
    {
        // TODO: Implement receive() method.
    }
}