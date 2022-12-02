<?php

use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;

/** @var MailerServiceInterface $mailer */
$mailer = ContainerFacade::get(MailerServiceInterface::class);

/** @var ServiceRegistryInterface $config */
$config = ContainerFacade::get(ServiceRegistryInterface::class);

// get the command params
$params = [];
foreach ($argv as $arg) {
    if (preg_match('/^--([a-z0-9]+)=([^=]+)$/', $arg, $matches)) {
        $params[$matches[1]] = $matches[2];
    }
}

echo PHP_EOL;

echo 'This script allow you to send test emails using airsend.' . PHP_EOL;

echo 'The current email driver is `' . $config->get('/mailer/driver') . '`' . PHP_EOL;

$senderDomain = $config->get('/mailer/domain');
echo "The current email sender domain is `$senderDomain`" . PHP_EOL;

echo 'Enter your email details bellow:' . PHP_EOL;

$to = isset($params['to']) ? explode(',', $params['to']) : [];
if (empty($to)) {
    echo 'Recipients (empty to stop):' . PHP_EOL;

    while (true) {
        $current = readline('    To: ');
        if (empty($current)) {
            break;
        }
        $to[] = $current;
    }
}

$from = $params['from'] ?? readline('From (don\'t include the domain name): ');
$subject = $params['subject'] ?? readline('Subject: ');

$htmlBody = $params['html'] ?? null;

if ($htmlBody === null) {
    echo "HTML body (empty line to finish):" . PHP_EOL;
    while (true) {
        $line = readline();
        if (empty($line)) {
            break;
        }
        $htmlBody .= $line . PHP_EOL;
    }
}

$plainBody = $params['plain'] ?? null;
if ($plainBody === null) {

    echo "Plain text body (empty line to finish):" . PHP_EOL;
    while (true) {
        $line = readline();
        if (empty($line)) {
            break;
        }
        $plainBody .= $line . PHP_EOL;
    }
}


echo PHP_EOL . 'Preview:' . PHP_EOL;
echo '=================================' . PHP_EOL;
echo "  To: " . implode(',', $to) . PHP_EOL;
echo "  From: $from@$senderDomain" . PHP_EOL;
echo "  Subject: $subject" . PHP_EOL;
if (!empty($htmlBody)) {
    echo PHP_EOL . "  HTML Body:" . PHP_EOL;
    echo '-------------------------------' . PHP_EOL;
    echo $htmlBody;
    echo PHP_EOL . '-------------------------------' . PHP_EOL;
} else {
    echo PHP_EOL . "  No HTML body defined." . PHP_EOL;
}
if (!empty($plainBody)) {
    echo PHP_EOL . "  Plain Body:" . PHP_EOL;
    echo '-------------------------------' . PHP_EOL;
    echo $plainBody;
    echo PHP_EOL . '-------------------------------' . PHP_EOL;
} else {
    echo PHP_EOL . "  No plain body defined." . PHP_EOL;
}
echo '=================================' . PHP_EOL;

echo PHP_EOL;
$confirm = strtolower(readline("Can I send it? (y/n): "));
if (!in_array($confirm, ['y', 'yes'])) {
    echo 'Aborting...' . PHP_EOL;
    exit(1);
}

echo 'Sending...' . PHP_EOL;


$emailMessage = $mailer
    ->createMessage($to)
    ->subject($subject)
    ->from($from);

if (!empty($htmlBody)) {
    $emailMessage->html($htmlBody);
}

if (!empty($plainBody)) {
    $emailMessage->plain($plainBody);
}

$mailer->send($emailMessage);

echo 'Email sent...' . PHP_EOL;


echo PHP_EOL;
