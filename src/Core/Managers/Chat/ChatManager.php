<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Chat;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChatAuthorizationException;
use CodeLathe\Core\Exception\ChatInvalidAttachmentException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\SlashCommandException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Service\Command\CommandService;
use CodeLathe\Service\Logger\LoggerService;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class ChatManager extends ManagerBase
{
    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @var ChatOperations
     */
    protected $chatOperations;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var ChannelOperations
     */
    protected $channelOps;

    /**
     * @var CommandService
     */
    protected $commandService;

    /**
     * ChatManager constructor.
     * @param ChatOperations $chatOperations
     * @param DataController $dataController
     * @param ChannelOperations $channelOps
     * @param LoggerService $logger
     * @param CommandService $commandService
     */
    public function __construct(ChatOperations $chatOperations,
                                DataController $dataController,
                                ChannelOperations $channelOps,
                                LoggerService $logger,
                                CommandService $commandService)
    {
        $this->logger = $logger;
        $this->chatOperations = $chatOperations;
        $this->dataController = $dataController;
        $this->channelOps = $channelOps;
        $this->commandService = $commandService;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    /**
     * Post a message to a channel
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function postMessage (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        // Before we validate the text, lets transform some stuff
        $params = $this->chatOperations->transformChatText($params);
        // Validate request
        if (!RequestValidator::validateRequest(['channel_id', 'text', 'quote_message_id', 'send_email'], $params, $response)) {
            return $response;
        }

        $channelId = $params['channel_id'];

        $text = '';
        if (isset($params['text'])) {
            $text = $params['text'];
        }

        $attachments = '';
        if (!empty($params['attachments'])) {
            $attachments = $params['attachments'];
        }
        $quoteMessageId = 0;
        if (!empty($params['quote_message_id'])) {
            $quoteMessageId = (int)$params['quote_message_id'];
        }

        $send_email = false;
        if (!empty($params['send_email'])) {
            $send_email = (bool)$params['send_email'];
        }

        if ($text === "" && empty($attachments)) {
            // Both cannot be empty
            return JsonOutput::error("Attachments or text is required", 400)->write($response);
        }

        try {
            $messageId = 0;
            if (strlen($text) > Message::MESSAGE_MAX_LENGTH) {
                $textArr =  str_split($text, Message::MESSAGE_MAX_LENGTH);
                foreach ($textArr as $text) {
                    $mid = $this->chatOperations->postMessage($user,
                        (int)$channelId,
                        $text,
                        $attachments,
                        $quoteMessageId,
                        'chat',
                        $send_email
                    );
                    if ($messageId == 0) {
                        $messageId = $mid;
                    }
                }
            }
            else {
                $messageId = $this->chatOperations->postMessage($user,
                    (int)$channelId,
                    $text,
                    $attachments,
                    $quoteMessageId,
                    'chat',
                    $send_email
                );
            }
        }
        catch (ChatAuthorizationException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        }
        catch (ChatInvalidAttachmentException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 404)->write($response);
        }
        catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->withContent('message_id', $messageId)->write($response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function postBotMessage (Request $request, Response $response): Response
    {
        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');
        $oauthClientId = $auth->getOauthClientId();

        if ($oauthClientId === null) {
            return JsonOutput::error("Invalid Oauth Client", 401)->write($response);
        }

        $params = $request->getParsedBody();
        // Validate request
        if (!RequestValidator::validateRequest(['channel_id', 'text'], $params, $response)) {
            return $response;
        }

        $channelId = (int)$params['channel_id'];
        $text = $params['text'] ?? '';

        try {
            $this->chatOperations->postBotMessage($channelId, $text, $oauthClientId);
        } catch (ChatAuthorizationException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        } catch (ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success(201)->write($response);

    }

    /**
     *
     * Endpoint to update a message. Only the message creator can edit the message
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     * @throws DatabaseException
     */
    public function updateMessage (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        // Before we validate the text, lets transform some stuff
        $params = $this->chatOperations->transformChatText($params);

        // Validate request
        if (!RequestValidator::validateRequest(['message_id', 'text'], $params, $response)) {
            return $response;
        }

        //$this->logger->info(__FUNCTION__ . " Params=" . print_r($params,true));
        try {
            $this->chatOperations->updateMessageText($user, (int)$params['message_id'], $params['text']);
        }
        catch(ChatOpException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        }
        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }


    /**
     *
     * Delete a message
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws DatabaseException
     */
    public function deleteMessage (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['message_id'], $params, $response)) {
            return $response;
        }

        $this->logger->info(__FUNCTION__ . " Params=" . print_r($params,true));
        try {
            $this->chatOperations->deleteMessage($user, (int)$params['message_id']);
        }
        catch(ChatOpException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        }
        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    public function command (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['channel_id', 'text'], $params, $response)) {
            return $response;
        }

        if (!preg_match('/^\/?([a-zA-Z][a-zA-Z0-9_]*)\s*(.*)?$/', $params['text'], $matches)) {
            return JsonOutput::error(I18n::get('messages.chat_invalid_command_format'), 422)->write($response);
        }

        $channel = $this->dataController->getChannelById((int)$params['channel_id']);
        if ($channel === null) {
            return JsonOutput::error(I18n::get('messages.chat_channel_not_found'), 404)->write($response);
        }

        $commandString = strtolower(trim($matches[1]));
        $commandParams = $matches[2] ?? '';

        $command = $this->commandService->createCommand($channel, $user, $commandString, $commandParams);
        if ($command === null) {
            return JsonOutput::error(I18n::get('messages.chat_command_not_found'), 422)->write($response);
        }

        try {
            $payload = $command->handle();
        } catch (SlashCommandException $e) {
            return JsonOutput::error($e->getMessage(), $e->httpCode)->write($response);
        }


        return JsonOutput::success()->withContent('payload', $payload)->write($response);
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
    public function reactMessage (Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        // Validate request
        if (!RequestValidator::validateRequest(['message_id', 'emoji_value', 'remove'], $params, $response)) {
            return $response;
        }

        $remove = false;
        if (!empty($params['remove'])) {
            $remove = (bool)$params['remove'];
        }

        try {
            $this->chatOperations->handleEmoticon($user, (int)$params['message_id'], $params['emoji_value'], $remove);
        }
        catch(ChatOpException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 401)->write($response);
        }
        catch(ASException $ex) {
            $this->logger->error(__FUNCTION__ . " Exception : ". $ex->getMessage());
            return JsonOutput::error($ex->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->write($response);
    }


}
