<?php


namespace CodeLathe\Core\Managers;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\I18n;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

abstract class ManagerBase
{

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    abstract protected function dataController():DataController;

    /**
     * @param Request $request
     * @param Response|null $response
     * @return User|null
     * @throws InvalidFailedHttpCodeException
     * @throws DatabaseException
     */
    protected function requireValidUser(Request $request, ?Response $response = null): ?User
    {

        /** @var Auth $auth */
        $auth = $request->getAttribute('auth');
        if (empty($auth) || get_class($auth) != Auth::class) {
            $errorMessage = "Unauthenticated: Auth not set.";
            if ($response !== null) {
                JsonOutput::error($errorMessage, 401)->write($response);
            }
            return null;
        }

        $userId = $auth->getUserId();
        if (empty($userId)) {
            $errorMessage = "Unauthenticated: User not set in Auth";
            if ($response !== null) {
                JsonOutput::error($errorMessage, 401)->write($response);
            }
            return null;
        }

        $user = $this->dataController()->getUserById($userId);
        if (empty($user)) {
            $errorMessage = "Invalid User";
            if ($response !== null) {
                JsonOutput::error($errorMessage, 401)->write($response);
            }
            return null;
        }

        if (($publicHash = $auth->getPublicHash()) !== null) {
            $user->setPublicHash($publicHash);
        }

        // if the logged user has a language definition, it will override the one provided on the Accept-Language header
        $locale = $user->getLocale();
        if (!preg_match('/^[a-z]{2}_[a-z]{2}$/i', trim($locale))) {
            I18n::setLocale($locale);
        }

        return $user;
    }

    /**
     * This method must be used to check if the user has JWT token set with admin access
     *
     * @param Request $request
     * @param Response $response
     * @return User|null
     * @throws InvalidFailedHttpCodeException
     * @throws DatabaseException
     */
    protected function requireValidAdmin(Request $request, Response $response): ?User
    {
        $auth = $request->getAttribute('auth');
        if (empty($auth) || get_class($auth) != Auth::class) {
            JsonOutput::error("Internal Error: Auth not set.", 500)->write($response);
            return null;
        }

        $userId = $auth->getUserId();
        if (empty($userId)) {
            JsonOutput::error("Internal Error: User not set in Auth", 500)->write($response);
            return null;
        }

        if (!$auth->getIsAdmin()) {
            JsonOutput::error("Not Authorized", 401)->write($response);
            return null;
        }

        $user = $this->dataController()->getUserById($userId);
        if (empty($user) || !User::isServiceAdmin($user->getId()) || $user->getUserRole() < User::USER_ROLE_SUB_ADMIN) {
            JsonOutput::error("Invalid Account", 403)->write($response);
            return null;
        }
        return $user;
    }

}