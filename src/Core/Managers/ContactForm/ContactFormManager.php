<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\ContactForm;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatAuthorizationException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\HttpException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotFoundException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UserOpException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Objects\ContactForm;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class ContactFormManager extends ManagerBase
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ContactFormOperations
     */
    protected $contactFormOps;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * BackgroundDispatcher constructor.
     * @param ContactFormOperations $contactFormOps
     * @param LoggerInterface $logger
     * @param NormalizedObjectFactory $objectFactory
     * @param DataController $dataController
     */
    public function __construct(ContactFormOperations $contactFormOps, LoggerInterface $logger, NormalizedObjectFactory $objectFactory, DataController $dataController)
    {
        $this->logger = $logger;
        $this->objectFactory = $objectFactory;
        $this->contactFormOps = $contactFormOps;
        $this->dataController = $dataController;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     */
    public function list(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $list = $this->contactFormOps->findForUser($user->getId());

        $forms = [];
        /** @var ContactForm $item */
        foreach ($list as $item) {
            $forms[] = $this->objectFactory->normalizedObject($item);
        }

        return JsonOutput::success()->withContent('forms', $forms)->write($response);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws NotImplementedException
     * @throws InvalidArgumentException
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function create(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['form_title', 'confirmation_message'], $params, $response)) {
            return $response;
        }

        $formTitle = $params['form_title'];
        $confirmationMessage = $params['confirmation_message'];
        $copyFromChannelId = isset($params['copy_from_channel_id']) ? (int)$params['copy_from_channel_id'] : null;
        $color = $params['color'] ?? null;

        // check if color param is valid, and convert it to long
        if ($color !== null) {
            if (($color = Convert::hexrgb2int($color)) === null) {
                return JsonOutput::error('Invalid color provided', 422)->write($response);
            }
        }

        // check if the channel to copy exists
        if ($copyFromChannelId !== null && $this->dataController->getChannelById($copyFromChannelId) === null) {
            return JsonOutput::error('Invalid channel provided for copy', 404)->write($response);
        }

        $enableOverlay = (bool) ($params['enable_overlay'] ?? false);

        $contactForm = $this->contactFormOps->create($user->getId(), $formTitle, $confirmationMessage, $copyFromChannelId, $enableOverlay, $color);

        $normalizedContactForm = $this->objectFactory->normalizedObject($contactForm);

        return JsonOutput::success()->withContent('contact_form', $normalizedContactForm)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function update(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['form_id'], $params, $response)) {
            return $response;
        }

        $formId = (int) $params['form_id'];
        $formTitle = $params['form_title'] ?? null;
        $confirmationMessage = $params['confirmation_message'] ?? null;
        $copyFromChannelId = isset($params['copy_from_channel_id']) ? (int)$params['copy_from_channel_id'] : null;
        $enableOverlay = isset($params['enable_overlay']) ? ((bool) $params['enable_overlay']) : null;

        $color = isset($params['color']) ? $params['color'] : null;
        if ($color !== null) {
            if ($color === '0') {
                $color = -1;
            } else {
                if (($color = Convert::hexrgb2int($color)) === null) {
                    return JsonOutput::error('Invalid color provided', 422)->write($response);
                }
            }
        }

        // check if the channel to copy exists
        if ($copyFromChannelId !== null && $copyFromChannelId > 0 && $this->dataController->getChannelById($copyFromChannelId) === null) {
            return JsonOutput::error('Invalid channel provided for copy', 422)->write($response);
        }

        try {
            $contactForm = $this->contactFormOps->update($formId, $user->getId(), $formTitle, $confirmationMessage, $copyFromChannelId, $enableOverlay, $color);
        } catch (HttpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getCode())->write($response);
        }

        if ($contactForm === null) {
            return JsonOutput::success(204)->write($response); // no changes done
        }

        $normalizedContactForm = $this->objectFactory->normalizedObject($contactForm);

        return JsonOutput::success()->withContent('contact_form', $normalizedContactForm)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function enable(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['form_id'], $params, $response)) {
            return $response;
        }

        $formId = (int) $params['form_id'];

        try {
            $contactForm = $this->contactFormOps->enable($formId, $user->getId());
        } catch (HttpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getCode())->write($response);
        }

        $normalizedContactForm = $this->objectFactory->normalizedObject($contactForm);

        return JsonOutput::success()->withContent('contact_form', $normalizedContactForm)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     */
    public function disable(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['form_id'], $params, $response)) {
            return $response;
        }

        $formId = (int) $params['form_id'];

        try {
            $contactForm = $this->contactFormOps->disable($formId, $user->getId());
        } catch (HttpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getCode())->write($response);
        }

        $normalizedContactForm = $this->objectFactory->normalizedObject($contactForm);

        return JsonOutput::success()->withContent('contact_form', $normalizedContactForm)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function delete(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['form_id'], $params, $response)) {
            return $response;
        }

        $formId = (int) $params['form_id'];

        try {
            $this->contactFormOps->delete($formId, $user->getId());
        } catch (HttpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getHttpCode())->write($response);
        }

        return JsonOutput::success(204)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws ChannelOpException
     * @throws ChatAuthorizationException
     * @throws ChatOpException
     * @throws FSOpException
     * @throws TeamOpException
     * @throws UnknownPolicyEntityException
     * @throws UnknownResourceException
     * @throws UserOpException
     * @throws InvalidEmailAddressException
     */
    public function fill(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();

        // Validate request.
        if (!RequestValidator::validateRequest(['form_filler_name', 'form_filler_email', 'form_filler_message'], $params, $response)) {
            return $response;
        }

        $formHash = $params['form_hash'];
        $fillerName = $params['form_filler_name'];
        $fillerEmail = $params['form_filler_email'];
        $fillerMessage = $params['form_filler_message'];

        try {
            $confirmationMessage = $this->contactFormOps->handleFormFilling($formHash, $fillerName, $fillerEmail, $fillerMessage);
        } catch (HttpException $e) {
            return JsonOutput::error($e->getMessage(), $e->getHttpCode())->write($response);
        }

        return JsonOutput::success()->withContent('message', $confirmationMessage)->write($response);
    }

    /**
     * @inheritDoc
     */
    protected function dataController(): DataController
    {
        return $this->dataController;
    }
}

