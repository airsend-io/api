<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\ContactForm;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatAuthorizationException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\ForbiddenException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\NotFoundException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ContactForm;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use Generator;
use Psr\Cache\InvalidArgumentException;

class ContactFormOperations
{

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    /**
     * @var ChatOperations
     */
    protected $chatOps;

    /**
     * @var UserOperations
     */
    protected $userOps;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * ContactFormOperations constructor.
     * @param DataController $dataController
     * @param ChannelOperations $channelOps
     * @param ChatOperations $chatOps
     * @param UserOperations $userOps
     * @param MailerServiceInterface $mailer
     * @param ConfigRegistry $config
     */
    public function __construct(DataController $dataController,
                                ChannelOperations $channelOps,
                                ChatOperations $chatOps,
                                UserOperations $userOps,
                                MailerServiceInterface $mailer,
                                ConfigRegistry $config)
    {
        $this->dataController = $dataController;
        $this->channelOps = $channelOps;
        $this->chatOps = $chatOps;
        $this->userOps = $userOps;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    /**
     * @param int $ownerId
     * @param string $formTitle
     * @param string $confirmationMessage
     * @param int|null $copyFromChannelId
     * @param bool $enableOverlay
     * @param int $color
     * @return ContactForm|null
     * @throws DatabaseException
     */
    public function create(int $ownerId, string $formTitle, string $confirmationMessage, ?int $copyFromChannelId, bool $enableOverlay, ?int $color): ?ContactForm
    {
        $contactForm = ContactForm::create($ownerId, $formTitle, $confirmationMessage, $copyFromChannelId, $enableOverlay, true, $color);
        return $this->dataController->createContactForm($contactForm);
    }

    /**
     * @param int $formId
     * @param int|null $userId
     * @return ContactForm|null
     * @throws DatabaseException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function findById(int $formId, ?int $userId = null): ContactForm
    {
        $contactForm = $this->dataController->getContactFormById($formId);
        if ($contactForm === null) {
            throw new NotFoundException('Form not found');
        }

        // check permissions (for now, just the form owner can make changes)
        if ($userId !== null && $contactForm->getOwnerId() !== $userId) {
            throw new ForbiddenException('Access denied to this form');
        }
        return $contactForm;
    }

    /**
     * @param int $formId
     * @param int $userId
     * @param string|null $formTitle
     * @param string|null $confirmationMessage
     * @param int|null $copyFromChannelId
     * @param bool|null $enableOverlay
     * @param int|null $color
     * @return ContactForm|null
     * @throws DatabaseException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function update(int $formId, int $userId, ?string $formTitle = null, ?string $confirmationMessage = null, ?int $copyFromChannelId = null, ?bool $enableOverlay = null, ?int $color = null): ?ContactForm
    {
        $contactForm = $this->findById($formId, $userId);

        if ($formTitle !== null) {
            $contactForm->setTitle($formTitle);
        }

        if ($confirmationMessage !== null) {
            $contactForm->setConfirmationMessage($confirmationMessage);
        }

        if ($copyFromChannelId !== null) { // -1 to don't copy anymore
            $contactForm->setCopyFromChannelId($copyFromChannelId < 0 ? null : $copyFromChannelId);
        }

        if ($enableOverlay !== null) {
            $contactForm->setEnableOverlay($enableOverlay);
        }

        if ($color !== null) {
            $color = $color < 0 ? null : $color;
            $contactForm->setColor($color);
        }

        return $this->dataController->updateContactForm($contactForm);

    }

    /**
     * @param int $formId
     * @param int $userId
     * @return ContactForm|null
     * @throws DatabaseException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function enable(int $formId, int $userId): ?ContactForm
    {
        $contactForm = $this->findById($formId, $userId);
        if ($contactForm === null) {
            return null;
        }

        $contactForm->enable();

        return $this->dataController->updateContactForm($contactForm);
    }

    /**
     * @param int $formId
     * @param int $userId
     * @return ContactForm|null
     * @throws DatabaseException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function disable(int $formId, int $userId): ?ContactForm
    {
        $contactForm = $this->findById($formId, $userId);
        if ($contactForm === null) {
            return null;
        }

        $contactForm->disable();

        return $this->dataController->updateContactForm($contactForm);
    }

    /**
     * @param $formId
     * @param int $userId
     * @throws DatabaseException
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete($formId, int $userId): void
    {
        $contactForm = $this->findById($formId, $userId);
        $this->dataController->deleteContactForm($contactForm->getId());
    }

    /**
     * @param int $ownerId
     * @return Generator
     * @throws DatabaseException
     */
    public function findForUser(int $ownerId): Generator
    {
        return $this->dataController->getContactFormsForUser($ownerId);
    }

    /**
     * @param string $formHash
     * @param string $fillerName
     * @param string $fillerEmail
     * @param string $fillerMessage
     * @return string
     * @throws ChannelMalformedException
     * @throws ChannelOpException
     * @throws ChatAuthorizationException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws FSOpException
     * @throws ForbiddenException
     * @throws InvalidArgumentException
     * @throws InvalidEmailAddressException
     * @throws NotFoundException
     * @throws NotImplementedException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UnknownResourceException
     * @throws UserOpException
     */
    public function handleFormFilling(string $formHash, string $fillerName, string $fillerEmail, string $fillerMessage): string
    {

        // find the contact form been filled
        $contactForm = $this->dataController->findContactFormByHash($formHash);

        // find the contact form owner
        $formOwner = $this->dataController->getUserById($contactForm->getOwnerId());

        // find the filler user (if it exists)
        $fillerUser = $this->dataController->getUserByEmail($fillerEmail);

        // create the filler user if it don't exists...
        // the invite to the channel will automatically create the user, but we need to check if the channel already
        // exists before creating it, and for that, we need the user, so we create it outside the channel invite process
        if (empty($fillerUser)) {
            $fillerUser = $this->userOps->createUser($fillerEmail,
                null,
                StringUtility::generateRandomString(12),
                $fillerName,
                User::ACCOUNT_STATUS_PENDING_FINALIZE,
                User::USER_ROLE_VIEWER,
                User::APPROVAL_STATUS_APPROVED,
                true,
                $formOwner->getId());
        }

        // Check if there is a channel related to this form/email
        $channel = $this->dataController->findChannelByContactForm($contactForm->getId(), $fillerUser->getId());

        if ($channel === null) {

            // TODO - Include this pattern on the contact_forms table. Maybe include a blurb pattern too
            $channelName = "Contact form - {$fillerEmail}";

            // Create the channel since it don't exists
            $copyChannelId = $contactForm->getCopyFromChannelId() ?? -1;
            $channel = $this->channelOps->createChannel($formOwner, $channelName, '', null, -1, $copyChannelId, false, null, null, $contactForm->getId(), $fillerUser->getId());

            // Add the user to the channel
            $this->channelOps->addUserToChannel($channel->getId(), $fillerEmail, $formOwner->getId());

        }

        // Post the message to the channel
        $this->chatOps->postMessage($fillerUser, $channel->getId(), $fillerMessage);

        $this->sendFormFillingEmail($channel, $contactForm, $formOwner, $fillerUser);

        // Generate the confirmation message and return
        $channelInviteUrl = $this->channelOps->getChannelInviteUrl($channel->getId(), $fillerUser->getId());
        return str_replace('%CHANNEL_LINK%', $channelInviteUrl, $contactForm->getConfirmationMessage());

    }

    /**
     * @param Channel $channel
     * @param ContactForm $contactForm
     * @param User $formOwner
     * @param User $formFiller
     * @throws InvalidEmailAddressException
     */
    public function sendFormFillingEmail(Channel $channel, ContactForm $contactForm, User $formOwner, User $formFiller)
    {

        $subject = "`{$formFiller->getDisplayName()} <{$formFiller->getEmail()}>` filled the form `{$contactForm->getTitle()}`";

        $body = "<p>We thought you might like to know that {$formFiller->getDisplayName()} has filled your form `{$contactForm->getTitle()}`.</p>";
        $body .= "<p>A channel was created so you can interact with this user and answer their request.</p>";

        $channelUrl = rtrim($this->config->get('/app/ui/baseurl'), '/') . "/channel/{$channel->getId()}";

        $message = $this->mailer
            ->createMessage($formOwner->getEmail())
            ->subject($subject)
            ->from($channel->getEmail(), "AirSend - {$channel->getName()}")
            ->body('general_template', [
                'subject' => $subject,
                'display_name' => $formOwner->getDisplayName(),
                'byline_text' => '',
                'html_body_text' => $body,
                'html_body_after_button_text' => '<p></p>',
                'button_url' => $channelUrl,
                'button_text' => "View Channel"
            ]);
        $this->mailer->send($message);
    }
}