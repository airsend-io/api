<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Admin;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\PhoneOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Utility\App;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Core\Utility\Utility;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidRequest;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Messaging\Events\ChannelCreatedEvent;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\JSONSerializableArray;
use CodeLathe\Core\Utility\RequestValidator;
use Exception;
use PHPUnit\Util\Log\JSON;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Request as Request;

class AdminTeamManager extends ManagerBase
{

    protected $dataController;

    protected $logger;

    protected $eventManager;

    protected $userOps;

    protected $channelOps;

    protected $objectFactory;

    protected $fileController;

    protected $phoneOps;

    protected $app;

    public function __construct(DataController $dataController,
        LoggerInterface $logger,
        UserOperations $userOps,
        ChannelOperations $channelOps,
        PhoneOperations $phoneOps,
        EventManager $eventManager,
        FileController $fileController,
        App $app)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->userOps = $userOps;
        $this->eventManager = $eventManager;
        $this->channelOps = $channelOps;
        $this->objectFactory = ContainerFacade::get(NormalizedObjectFactory::class);
        $this->fileController = $fileController;
        $this->phoneOps = $phoneOps;
        $this->app = $app;
    }

    /**
     * Derived class must give us the dataController
     * @return DataController
     */
    protected function dataController () : DataController
    {
        return $this->dataController;
    }

    public function create(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        $teamName = $params['team_name'];
        $teamType = $params['team_type'];
        $emailOrPhone = $params['team_owner'];

        try {
            $user = $this->dataController->getUserByEmailOrPhone($emailOrPhone);
            if (empty($user))
            {
                $passwordHash = password_hash(StringUtility::generateRandomString(8), PASSWORD_DEFAULT);

                $user = User::create(Utility::isValidEmail($emailOrPhone) ?? null,
                                    Utility::isValidPhoneFormat($emailOrPhone)?? null,
                                    $passwordHash, $emailOrPhone, User::ACCOUNT_STATUS_ACTIVE, User::USER_TRUST_LEVEL_FULL,
                                User::APPROVAL_STATUS_APPROVED, true);

                if (!$this->dataController->createUser($user)){
                    return JsonOutput::error("User $teamName creation failed", 500)->write($response);
                }
            }

            $team = Team::create($teamName, $teamType, $admin->getId());
            if (!$this->dataController->createTeam($team)) {
                return JsonOutput::error("Team $teamName creation failed", 500)->write($response);
            }

            return JsonOutput::success(200)->write($response);
        }
        catch(ASException $e){
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }

    }

    public function update(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        $teamId = $params['team_id'];
        $teamName = $params['team_name'];
        $teamType = $params['team_type'];

        try {
            $team = $this->dataController->getTeamByTeamId($teamId);
            if (empty($team)) {
                return JsonOutput::error("Invalid Team", 500)->write($response);
            }

            $team->setName($teamName);
            $team->setTeamType($teamType);
            if (!$this->dataController->updateTeam($team)) {
                return JsonOutput::error("Team update failed", 500)->write($response);
            }

            return JsonOutput::success(200)->write($response);
        }
        catch(ASException $e)
        {
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }

    }

    public function delete(Request $request, Response $response): Response
    {
       //TODO : delete
    }

    public function info(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        $teamId = $params['team_id'];
        try {
            $team = $this->dataController->getTeamByTeamId($teamId);
            if (empty($team)) {
                return JsonOutput::error("Invalid Team", 500)->write($response);
            }

            return JsonOutput::success(200)->withContent("team", $team)->write($response);
        }
        catch(ASException $e)
        {
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }
    }

    public function search(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        $id = empty($params['id']) ? null : (int)$params['id'];
        $keyword = empty($params['keyword']) ? null : strval($params['keyword']);
        $teamType = empty($params['team_type']) ? null : intval($params['team_type']);
        $offset =  empty($params['offset']) ? null : intval($params['offset']);
        $rowCount =  empty($params['row_count']) ? null : intval($params['row_count']);

        if ($id !== null) {
            $team = $this->dataController->getTeamByTeamId($id);
            if ($team !== null) {
                $teams = [$team];
                return JsonOutput::success(200)->addMeta("total", 1)->withContent('team', $teams)->write($response);
            } else {
                return JsonOutput::success()->addMeta("total", 0)->withContent('users', [])->write($response);
            }
        }

        $total = $this->dataController->searchTeamCount($keyword, $teamType);
        $teams = [];
        foreach($this->dataController->searchTeams($keyword, $teamType, $offset, $rowCount) as $record) {
            $teams[] = Team::withDBData($record);
        }

        return JsonOutput::success()->addMeta("total", $total)->withContent('team', $teams)->write($response);
    }

    public function listUsers(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getQueryParams();

        $teamId = intval($params['team_id']);

        $users = [];
        foreach($this->dataController->getTeamUsers($teamId) as $teamUser) {
            $user = $this->dataController->getUserById($teamUser->getUserId());
            $users[]  = array_merge($teamUser->getArray(), [
                'display_name' => $user->getDisplayName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'has_avatar' => $user->getHasAvatar(),
                'user_updated_on' => $user->getUpdatedOn(),
            ]);
        }

        return JsonOutput::success()->withContent('users', $users)->write($response);
    }

    public function addUser(Request $request, Response $response): Response
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        $teamId = $params['team_id'];
        $userId = $params['user_id'];
        $userRole = $params['user_role'];

        try {
            $team = $this->dataController->getTeamByTeamId($teamId);
            if (empty($team)) {
                return JsonOutput::error("Invalid Team", 500)->write($response);
            }

            if (!empty($this->dataController->getTeamUser($userId, $teamId))) {
                return JsonOutput::error("User already in Team", 500)->write($response);
            }

            if (!in_array($userRole, array(TeamUser::TEAM_USER_ROLE_MEMBER,
                                            TeamUser::TEAM_USER_ROLE_MANAGER,
                                            TeamUser::TEAM_USER_ROLE_COLLABORATOR,
                                            TeamUser::TEAM_USER_ROLE_OWNER))) {
                return JsonOutput::error("Invalid Role", 500)->write($response);
            }

            $teamUser = TeamUser::create($teamId, $userId, $userRole, $admin->getId());
            if (!$this->dataController->addTeamUser($teamUser)) {
                return JsonOutput::error("Add Team User Failed", 500)->write($response);
            }

            return JsonOutput::success(200)->write($response);
        }
        catch(ASException $e)
        {
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }
    }

    public function deleteUser(Request $request, Response $response): Response
    {

        if (empty($admin = $this->requireValidAdmin($request, $response))) { return $response; };

        $params = $request->getParsedBody();

        $teamId = $params['team_id'];
        $userId = $params['user_id'];

        try {
            $team = $this->dataController->getTeamByTeamId($teamId);
            if (empty($team)) {
                return JsonOutput::error("Invalid Team", 500)->write($response);
            }

            if (empty($this->dataController->getTeamUser($userId, $teamId))) {
                return JsonOutput::error("Invalid Team User", 500)->write($response);
            }

            if ($this->dataController->dropTeamUser($teamId, $userId) == 0) {
                return JsonOutput::error("Drop Team User Failed", 500)->write($response);
            }

            return JsonOutput::success(200)->write($response);
        }
        catch(ASException $e)
        {
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }
    }
}