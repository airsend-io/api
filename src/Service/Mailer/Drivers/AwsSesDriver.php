<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer\Drivers;

use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use CodeLathe\Service\Mailer\EmailHandlerInterface;
use CodeLathe\Service\Mailer\EmailMessageInterface;
use CodeLathe\Service\Mailer\Exceptions\ImpossibleToParseReceivedEmail;
use CodeLathe\Service\Mailer\Exceptions\MessageNotSentException;
use CodeLathe\Service\Mailer\RecipientInterface;
use GuzzleHttp\Psr7\Stream;


class AwsSesDriver implements EmailHandlerInterface
{

    use ReduceRecipientsTrait;

    /**
     * @var SesClient
     */
    protected $awsSesClient;

    protected $awsS3Client;

    /**
     * AwsSesDriver constructor.
     * @param string $key
     * @param string $secret
     */
    public function __construct(string $key, string $secret)
    {
        $this->awsSesClient = new SesClient([
            'version' => '2010-12-01',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        $this->awsS3Client = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'credentials' => [
                'key' => $key,
                'secret' => $secret
            ]
        ]);
    }

    /**
     * @param EmailMessageInterface $message
     * @throws MessageNotSentException
     */
    public function send(EmailMessageInterface $message): void
    {

        if (empty($message->getAttachments())) {
            $this->simpleSend($message);
        } else {
            $this->rawSend($message);
        }



    }

    protected function simpleSend(EmailMessageInterface $message): void
    {
        $to = array_map(function (RecipientInterface $item) {
            return $item->getAddress();
        }, $message->getTo());

        $from = utf8_encode($message->getFrom());
        $replyTo = [utf8_encode($message->getReplyTo() ?? $message->getFrom())];

        try {
            $this->awsSesClient->sendEmail([
                'Destination' => [
                    'ToAddresses' => $to,
                ],
                'ReplyToAddresses' => $replyTo,
                'Source' => $from,
                'Message' => [
                    'Body' => [
                        'Html' => [
                            'Charset' => 'UTF-8',
                            'Data' => $message->getHtmlBody(),
                        ],
                        'Text' => [
                            'Charset' => 'UTF-8',
                            'Data' => $message->getPlainBody(),
                        ],
                    ],
                    'Subject' => [
                        'Charset' => 'UTF-8',
                        'Data' => $message->getSubject(),
                    ],
                ],
            ]);
        } catch (AwsException $e) {
            throw new MessageNotSentException($e->getMessage() . ' --> ' . $e->getAwsErrorMessage());
        }
    }

    protected function rawSend(EmailMessageInterface $message): void
    {

        // for messages with attachments, we need to create a raw message and send through AWS

        $to = array_map(function (RecipientInterface $item) {
            return $item->getAddress();
        }, $message->getTo());
        $toString = implode(",", $to);

        $from = utf8_encode($message->getFrom());

//        $myFileName = basename($myFilePath);
//
//        $myDataAttachedFile = file_get_contents($myFilePath);
//        $myDataAttachedFile = chunk_split(base64_encode($myDataAttachedFile));

//        $myFileMimeInfo = finfo_open(FILEINFO_MIME_TYPE);
//        $myFileMimeType = finfo_file($myFileMimeInfo, $myFilePath);

        // generate random boundaries
        $boundary = md5('base' . time());
        $multipartBoundary = md5('multipart' . time());

        $rawMessage = "";

        $rawMessage .= "MIME-Version: 1.0\n";

        // set to, from, subject
        $rawMessage .= "To:$toString\n";
        $rawMessage .= "From:$from\n";
        $rawMessage .= "Subject:{$message->getSubject()}\n";

        // set the boundaries
        $rawMessage .= "Content-Type: multipart/mixed; boundary=\"$multipartBoundary\"\n";
        $rawMessage .= "\n--$multipartBoundary\n";
        $rawMessage .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\n";

        // plain text content
        $rawMessage .= "\n--$boundary\n";
        $rawMessage .= "Content-Type: text/plain; charset=\"UTF-8\"\n";
        $rawMessage .= "\n{$message->getPlainBody()}\n";

        // html content
        $rawMessage .= "\n--$boundary\n";
        $rawMessage .= "Content-Type: text/html; charset=\"UTF-8\"\n";
        $rawMessage .= "\n{$message->getHtmlBody()}\n";

        // finishes the multipart/alternative
        $rawMessage .= "\n--$boundary--\n";

        // include the attachments
        foreach ($message->getAttachments() as $attachment) {
            $rawMessage .= "--$multipartBoundary\n";
            $rawMessage .= "Content-Type: {$attachment->getMime()}; name=\"{$attachment->getFileName()}\"\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"{$attachment->getFileName()}\"\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\n\n";
            $rawMessage .= $attachment->getContent() . "\n";
        }

        // finishes the attachments section
        $rawMessage .= "--$multipartBoundary--";

        try {
            $this->awsSesClient->sendRawEmail([
                'Source'       => $from,
                'Destinations' => $to,
                'RawMessage'   => [
                    'Data' => $rawMessage
                ]
            ]);
        } catch (AwsException $e) {
            throw new MessageNotSentException($e->getMessage() . ' --> ' . $e->getAwsErrorMessage());
        }

    }

    /**
     * For AWS SES, the message received on the endpoint just contains info about the email. The email itself is stored
     * on AWS S3. It's up to this driver to get this info, and download the message from S3 before processing it.
     *
     * @inheritDoc
     */
    public function receive(string $requestBody): array
    {
        $payload = json_decode($requestBody, true);

        // decode the message attribute (the email object)
        $message = json_decode($payload['Message'], true);

        // parse sender address
        $sender = $message['mail']['source'];

        // parse recipients addresses
        $recipients = $message['mail']['destination'];

        // parse the raw email
        $action = $message['receipt']['action']; // split the action object

        $rawEmail = $this->downloadMessageFromS3($action['bucketName'], $action['objectKey']);

        return [$sender, $recipients, $rawEmail];
    }

    /**
     * @param string $bucketName
     * @param string $objectKey
     * @return string
     * @throws ImpossibleToParseReceivedEmail
     */
    protected function downloadMessageFromS3(string $bucketName, string $objectKey): string
    {

        try {

            $objectDesc = [
                'Bucket' => $bucketName,
                'Key'    => $objectKey
            ];
            $result = $this->awsS3Client->getObject($objectDesc);

            /** @var Stream $resultBody */
            $resultBody = $result['Body'];
            $rawEmail = $resultBody->getContents();

            $this->awsS3Client->deleteObject($objectDesc);

        } catch (S3Exception $e) {
            throw new ImpossibleToParseReceivedEmail($e->getMessage());
        }

        return $rawEmail;

    }
}