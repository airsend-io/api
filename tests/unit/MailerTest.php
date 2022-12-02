<?php

use Codeception\Test\Unit;
use CodeLathe\Service\Mailer\EmailSenderInterface;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailSenderDriverException;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailSenderDriverParamsException;
use CodeLathe\Service\Mailer\Exceptions\InvalidSMSSenderDriverException;
use CodeLathe\Service\Mailer\MailerService;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\Mailer\RecipientInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Container\ContainerInterface;
use Tests\Unit\Dummy\DummyRecipient;

class MailerTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @throws Exception
     */
    protected function _before()
    {
        // mailer service loaded with dummy dependencies, because we're not testing the actual sending of the emails.
        // this is really hard to test (check if the email was delivered), so this test is skipped on automated tests.
        $this->mailer = new MailerService('mailgun', ['key' => 'dummykey'], 'dummydomain.com');
    }

    protected function _after()
    {
    }

    public function _createMessageProvider()
    {
        return [
            'one string raw recipient' => [
                'to' => 'jonsnow@winterfel.north',
                'count' => 1,
                'expected' => [
                    ['name' => null, 'email' => 'jonsnow@winterfel.north', 'address' => 'jonsnow@winterfel.north'],
                ],
            ],
            'multiple string raw recipients' => [
                'to' => ['jonsnow@winterfel.north', 'branstark@winterfel.north'],
                'count' => 2,
                'expected' => [
                    ['name' => null, 'email' => 'jonsnow@winterfel.north', 'address' => 'jonsnow@winterfel.north'],
                    ['name' => null, 'email' => 'branstark@winterfel.north', 'address' => 'branstark@winterfel.north'],
                ],
            ],
            'one string complete recipient' => [
                'to' => 'Jon Snow <jonsnow@winterfel.north>',
                'count' => 1,
                'expected' => [
                    ['name' => 'Jon Snow', 'email' => 'jonsnow@winterfel.north', 'address' => 'Jon Snow <jonsnow@winterfel.north>'],
                ],
            ],
            'multiple string mixed recipients' => [
                'to' => ['Jon Snow <jonsnow@winterfel.north>', 'branstark@winterfel.north'],
                'count' => 2,
                'expected' => [
                    ['name' => 'Jon Snow', 'email' => 'jonsnow@winterfel.north', 'address' => 'Jon Snow <jonsnow@winterfel.north>'],
                    ['name' => null, 'email' => 'branstark@winterfel.north', 'address' => 'branstark@winterfel.north'],
                ],
            ],
            'multiple string mixed recipients with messed name' => [
                'to' => ['Jon   SnOw    <jonsnow@winterfel.north>', 'branstark@winterfel.north'],
                'count' => 2,
                'expected' => [
                    ['name' => 'Jon Snow', 'email' => 'jonsnow@winterfel.north', 'address' => 'Jon Snow <jonsnow@winterfel.north>'],
                    ['name' => null, 'email' => 'branstark@winterfel.north', 'address' => 'branstark@winterfel.north'],
                ],
            ],
            'one object recipient' => [
                'to' => new DummyRecipient('Jon Snow', 'jonsnow@winterfel.north', 'Jon Snow <jonsnow@winterfel.north>'),
                'count' => 1,
                'expected' => [
                    ['name' => 'Jon Snow', 'email' => 'jonsnow@winterfel.north', 'address' => 'Jon Snow <jonsnow@winterfel.north>'],
                ],
            ],
            'multiple object recipient' => [
                'to' => [
                    new DummyRecipient('Jon Snow', 'jonsnow@winterfel.north', 'Jon Snow <jonsnow@winterfel.north>'),
                    new DummyRecipient('Bran Stark', 'branstark@winterfel.north', 'Bran Stark <branstark@winterfel.north>'),
                ],
                'count' => 2,
                'expected' => [
                    ['name' => 'Jon Snow', 'email' => 'jonsnow@winterfel.north', 'address' => 'Jon Snow <jonsnow@winterfel.north>'],
                    ['name' => 'Bran Stark', 'email' => 'branstark@winterfel.north', 'address' => 'Bran Stark <branstark@winterfel.north>'],
                ],
            ],
            'multiple mixed object/string recipient' => [
                'to' => [
                    new DummyRecipient('Jon Snow', 'jonsnow@winterfel.north', 'Jon Snow <jonsnow@winterfel.north>'),
                    'Bran Stark <branstark@winterfel.north>',
                ],
                'count' => 2,
                'expected' => [
                    ['name' => 'Jon Snow', 'email' => 'jonsnow@winterfel.north', 'address' => 'Jon Snow <jonsnow@winterfel.north>'],
                    ['name' => 'Bran Stark', 'email' => 'branstark@winterfel.north', 'address' => 'Bran Stark <branstark@winterfel.north>'],
                ],
            ],
            'invalid string email 1' => [
                'to' => ['Jon Snow <notAnEmail>', 'branstark@winterfel.north'],
                'count' => 2,
                'expected' => null,
                'expectedException' => InvalidEmailAddressException::class
            ],
            'invalid string email 2' => [
                'to' => ['Jon Snow <jonsnow@winterfel.north>', 'branstark@winterfel.north.'],
                'count' => 2,
                'expected' => null,
                'expectedException' => InvalidEmailAddressException::class
            ],
        ];
    }

    /**
     * @param RecipientInterface[] $actual
     * @param int $count
     * @param array $expected
     */
    protected function assertRecipient($actual, $count, $expected)
    {
        $this->assertIsArray($actual);
        $this->assertCount($count, $actual);
        foreach ($actual as $key => $item) {
            $this->assertInstanceOf(RecipientInterface::class, $item);
            $this->assertEquals($expected[$key]['name'], $item->getName());
            $this->assertEquals($expected[$key]['email'], $item->getEmail());
            $this->assertEquals($expected[$key]['address'], $item->getAddress());
        }
    }

    /**
     * Test multiple ways to add recipients to an email message
     *
     * @dataProvider _createMessageProvider
     * @param $to
     * @param $count
     * @param $expected
     * @param null $expectedException
     * @throws InvalidEmailAddressException
     */
    public function testCreateMessage($to, $count, $expected, $expectedException = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $message = $this->mailer->createMessage($to);

        $actualTo = $message->getTo();
        $this->assertRecipient($actualTo, $count, $expected);
    }

    /**
     * Test multiple ways to add Carbon Copy recipients to an email message
     *
     * @dataProvider _createMessageProvider
     * @param $cc
     * @param $count
     * @param $expected
     * @param null $expectedException
     * @throws InvalidEmailAddressException
     */
    public function testAddCc($cc, $count, $expected, $expectedException = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $message = $this->mailer->createMessage('nedstark@winterfel.north')->cc($cc);

        $actualCc = $message->getCc();
        $this->assertRecipient($actualCc, $count, $expected);
    }

    /**
     * Test multiple ways to add Blind Carbon Copy recipients to an email message
     *
     * @dataProvider _createMessageProvider
     * @param $bcc
     * @param $count
     * @param $expected
     * @param null $expectedException
     * @throws InvalidEmailAddressException
     */
    public function testAddBcc($bcc, $count, $expected, $expectedException = null)
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $message = $this->mailer->createMessage('nedstark@winterfel.north')->bcc($bcc);

        $actualBcc = $message->getBcc();
        $this->assertRecipient($actualBcc, $count, $expected);
    }

    /**
     * Test the creation of an email message, including cc and bcc recipients, using mixed formats
     *
     * @throws InvalidEmailAddressException
     */
    public function testMixedToCcBcc()
    {
        $message = $this->mailer
            ->createMessage(['jonsnow@winterfel.north', 'Arya Stark <aryathekiller@winterfel.north>'])
            ->cc(new DummyRecipient(
                'Catlyn Tully',
                'catstark@riverun.rivers',
                'Catlyn Tully <catstark@riverrun.rivers>'
            ))
            ->bcc(['Tyrion Lannister <theimp@casterly.rock>', 'varys@secret.capital']);

        $this->assertRecipient($message->getTo(), 2, [
            ['name' => null, 'email' => 'jonsnow@winterfel.north', 'address' => 'jonsnow@winterfel.north'],
            ['name' => 'Arya Stark', 'email' => 'aryathekiller@winterfel.north', 'address' => 'Arya Stark <aryathekiller@winterfel.north>'],
        ]);

        $this->assertRecipient($message->getCc(), 1, [
            ['name' => 'Catlyn Tully', 'email' => 'catstark@riverun.rivers', 'address' => 'Catlyn Tully <catstark@riverrun.rivers>'],
        ]);

        $this->assertRecipient($message->getBcc(), 2, [
            ['name' => 'Tyrion Lannister', 'email' => 'theimp@casterly.rock', 'address' => 'Tyrion Lannister <theimp@casterly.rock>'],
            ['name' => null, 'email' => 'varys@secret.capital', 'address' => 'varys@secret.capital'],
        ]);
    }

    /**
     * @throws InvalidEmailSenderDriverParamsException
     * @throws InvalidEmailSenderDriverException
     */
    public function testInvalidMailerDriver()
    {
        $this->expectException(InvalidEmailSenderDriverException::class);
        new MailerService('invaliddriver', ['doesnt' => 'matter'], 'dummydomain.com');
    }

    /**
     * @throws InvalidEmailSenderDriverParamsException
     * @throws InvalidEmailSenderDriverException
     */
    public function testInvalidMailerDriverParams()
    {
        $this->expectException(InvalidEmailSenderDriverParamsException::class);
        new MailerService('mailgun', ['invalid' => 'param'], 'dummydomain.com');
    }
}