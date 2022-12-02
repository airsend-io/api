<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Password;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\PasswordOpException;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\PasswordRecoveredEvent;
use CodeLathe\Core\Messaging\Events\PasswordResetEvent;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;
use Psr\Http\Message\ResponseInterface as Response;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Utility\RequestValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordManager extends ManagerBase implements EventSubscriberInterface
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PasswordOperations
     */
    protected $passwordOps;

    /**
     * @var EventManager
     */
    protected $eventManager;


    /**
     * PasswordManager constructor.
     *
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param PasswordOperations $passwordOps
     */
    public function __construct(DataController $dataController,
        LoggerInterface $logger,
        PasswordOperations $passwordOps,
        EventManager $eventManager)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->passwordOps = $passwordOps;
    }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    public static function getSubscribedEvents()
    {
        return [
            PasswordRecoveredEvent::backgroundEventName() => 'onPasswordRecovered',
            PasswordResetEvent::backgroundEventName() => 'onPasswordReset'
        ];
    }

    /**
     * Password Recover function
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     * @throws \CodeLathe\Core\Exception\ValidationErrorException
     */
    public function recover(Request $request, Response $response): Response
    {
        // The parameters should be in the body of this request
        $params = $request->getParsedBody();

        if (empty($params)) {
            return JsonOutput::error("Invalid Parameters", 400)->write($response);
        }

        if (!RequestValidator::validateRequest(['opt_email', 'opt_phone'], $params, $response)) {
            return $response;
        }

        if (empty($params['opt_email']) && empty($params['opt_phone'])) {
            return JsonOutput::error("Email or Phone number is required", 400)->write($response);
        }

        $user = null;
        if (!empty($params['opt_email'])) {
            $email = $params['opt_email'];
            $user = $this->dataController->getUserByEmail($email);
        } else {
            if (!empty($params['opt_phone'])) {
                $phone = $params['opt_phone'];
                $user = $this->dataController->getUserByPhone($phone);
            }
        }

        if (empty($user)) {
            $this->logger->info("Invalid User for Recover Password");
            $this->logger->info(print_r($params,true));
            return JsonOutput::success()->withCode(200)->write($response); // ... Don't leak information
        }

        try
        {
            $userCode = $this->passwordOps->recover($user);
            if (empty($userCode)) {
                $this->logger->info("Bad Ops code for Recover Password");
                return JsonOutput::success()->withCode(200)->write($response); // ... Don't leak information
            }
            return JsonOutput::success()->withCode(200)->write($response);
        }
        catch(ASException $e){
            $this->logger->error(__FUNCTION__ . " : " . $e->getMessage());
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }
    }

    /**
     * Password Reset Function
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     * @throws \CodeLathe\Core\Exception\ValidationErrorException
     */
    public function reset(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();

        if (empty($params)) {
            return JsonOutput::error("Invalid Parameters", 400)->write($response);
        }

        // Validate request.
        if (!RequestValidator::validateRequest(['user_id','password','reset_code'], $params, $response)) { return $response;}

        $userId = $params['user_id'];
        $resetCode = $params['reset_code'];
        $password = $params['password'];

        try {
            if ($this->passwordOps->resetPassword($userId, $password, $resetCode)) {
                return JsonOutput::success()->withCode(200)->write($response);
            }
        }
        catch(PasswordOpException $pe){
            $this->logger->info(__FUNCTION__ . " : " . $pe->getMessage());

            return JsonOutput::error($pe->getMessage(), 400)->write($response);
        }
        catch(ASException $e){
            $this->logger->error(__FUNCTION__ . " : " . $e->getMessage());

            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \CodeLathe\Core\Exception\DatabaseException
     * @throws \CodeLathe\Core\Exception\InvalidFailedHttpCodeException
     * @throws \CodeLathe\Core\Exception\ValidationErrorException
     */
    public function update(Request $request, Response $response): Response
    {
        if (empty($user = $this->requireValidUser($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        if (empty($params)) {
            return JsonOutput::error("Invalid Parameters", 400)->write($response);
        }

        // Validate request.
        if (!RequestValidator::validateRequest(['user_id','current_password','new_password'], $params, $response)) { return $response;}

        $userId = $params['user_id'];
        $currentPassword = $params['current_password'];
        $newPassword = $params['new_password'];

        if ($currentPassword == $newPassword) {
            return JsonOutput::error("New password cannot be same as the old password", 400)->write($response);
        }

        try {
            if ($this->passwordOps->updatePassword($userId, $currentPassword, $newPassword)) {
                return JsonOutput::success()->withCode(200)->write($response);
            }
        }
        catch(PasswordOpException $pe){
            $this->logger->info(__FUNCTION__ . " : " . $pe->getMessage());

            return JsonOutput::error($pe->getMessage(), 400)->write($response);
        }
        catch(ASException $e){
            $this->logger->error(__FUNCTION__ . " : " . $e->getMessage());

            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }
    }

    /**
     * Password Recovered Event
     *
     * @param PasswordRecoveredEvent $event
     * @throws \CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException
     */
    public function onPasswordRecovered(PasswordRecoveredEvent $event)
    {
        $this->passwordOps->sendRecoveryMessage($event->getUserCode());
    }

    /**
     * Password Reset Event
     *
     * @param PasswordResetEvent $event
     * @throws \CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException
     */
    public function onPasswordReset(PasswordResetEvent $event)
    {
        $this->passwordOps->sendResetMessage($event->getUser());
    }

}