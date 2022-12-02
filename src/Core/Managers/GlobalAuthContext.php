<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Objects\AuthPermission;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\Team;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\User;
use Psr\Log\LoggerInterface;

/**
 * Class GlobalAuthContext
 * @package CodeLathe\Core\Managers
 *
 * Global AuthContext accepts userId, context and an optional context Id and returns the permission
 * for the userId in that context.
 *
 * UserId is the user who permission must be determined in a given context.
 * The context can be Global, Channel or Team.
 * ContextId is the corresponding ChannelId or TeamId. For global context the contextId is null
 *
 * The permission returned is one of the following
 *
 * 1. READ      - Read is allowed for the user in that context
 * 2. WRITE     - Write is allowed for the user in that context. Meaning, user can create a new channel,
  *             - team, action, file, wiki, message
 * 3. MANAGE    - All Write Permissions + Edit Channel, Action etc. + Add user to channel
 * 4. OWNER     - All Manage Permissions + Delete Channel.
 * 5. ADMIN     - Service Admin
 *
 */
class GlobalAuthContext
{

    /**
     * @var DataController
     */
    private $dataController;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * GlobalAuthContext constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger)
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
    }


    /**
     * No Access
     */
    CONST AUTH_NONE             = 0;  // no authentication

    /**
     * Read Only Access
     */
    CONST AUTH_READ             = 10;

    /**
     * Write Access including create new channel, team, action, file, wiki, message
     */
    CONST AUTH_WRITE            = 20;

    /**
     * Manage can do everything that owner can do except delete channel or team
     */
    CONST AUTH_MANAGE           = 30;

    /**
     * Owner can pretty much do everything in the channel or team context to which he is the owner.
     */
    CONST AUTH_ADMIN              = 40;

    /**
     * Service admin
     */
    CONST AUTH_OWNER            = 60;


    CONST CONTEXT_TYPE_GLOBAL        = 0;
    CONST CONTEXT_TYPE_CHANNEL       = 10;
    CONST CONTEXT_TYPE_TEAM          = 20;


    /**
     * UserId indicates the user to which we are finding the global auth context
     * contextType indicates the context Global, Channel or Team
     *      Channel Indicates that effective permission returned is valid for that user in that channel
     *      Team indicates that effective permission returned is valid for that user in that team
     *      Global Indicates that effective permission returned is valid for that user globally across
     *                                              all areas of airsend (It is only applicable for service admins)
     * contextId can be either channelId or teamId
     *
     * @param int|User $user
     * @param int $contextType
     * @param int|null $contextId
     * @return int
     * @throws DatabaseException
     */
    public function getEffectivePermission($user, int $contextType, ?int $contextId = null)
    {

        if (!($user instanceof User)) {
            $user = $this->dataController->getUserById($user);
        }
        $userId = $user->getId();

        $channelId = null;
        $teamId = null;
        if ($contextType == self::CONTEXT_TYPE_CHANNEL) {
            //$this->logger->debug(__CLASS__ . " Get Effective Permission for UserId $userId in Channel Context with Channel $contextId");
            $channelId = $contextId;
        }
        else if ($contextType == self::CONTEXT_TYPE_TEAM) {
            //$this->logger->debug(__CLASS__ . " Get Effective Permission for UserId $userId in Team Context with TeamId $contextId");
            $teamId = $contextId;
        }
        else if ($contextType == self::CONTEXT_TYPE_GLOBAL) {
            //$this->logger->debug(__CLASS__. " Get Effective Permission for UserId $userId in Global Context");
            $channelId = null;
            $teamId = null;
        }
        else {
            $this->logger->debug(__CLASS__ . " Effective Permission NONE Invalid Context");
            return self::AUTH_NONE;
        }

        // .. Just based on user object alone we can determine only AUTH NONE, AUTH_READ (for public user) or AUTH ADMIN.
        // .. Auth Viewer, Editor, Manager, Limited Owner or Owner requires Channel or Team Context if not
        // .. that will provide authentication for all channels or teams which is incorrect. Only
        // .. admins can have that permission.

        // .. check user approval status is pending
        if ($user->getApprovalStatus() == User::APPROVAL_STATUS_PENDING) {
            //$this->logger->debug(__CLASS__ . " Effective Permission AUTH User Approval Status is Pending");
            return self::AUTH_NONE;
        }

        // .. check user account status
        switch($user->getAccountStatus())
        {
            case User::ACCOUNT_STATUS_DELETED:
                //$this->logger->debug(__CLASS__ . " Effective Permission VIEWER User Account Status Deleted");
                return self::AUTH_NONE;
                break;
            case User::ACCOUNT_STATUS_BLOCKED:
                //$this->logger->debug(__CLASS__ . " Effective Permission VIEWER User Account Status Blocked");
                return self::AUTH_NONE;
                break;
            case User::ACCOUNT_STATUS_DISABLED:
                //$this->logger->debug(__CLASS__ . " Effective Permission VIEWER User Account Status Disabled");
                return self::AUTH_NONE;
                break;
        }

        // .. Check user Role for admin/sub admin
        if ($user->getUserRole() == User::USER_ROLE_SERVICE_ADMIN || $user->getUserRole() == User::USER_ROLE_SUB_ADMIN) {
            //$this->logger->debug(__CLASS__ . " Effective Permission ADMIN User Role is Admin");
            return self::AUTH_ADMIN;
        } elseif ($user->getUserRole() == User::USER_ROLE_PUBLIC) {
            //$this->logger->debug(__CLASS__ . " Effective Permission READ User Role is Public");
            return self::AUTH_READ;
        } elseif ($user->getUserRole() == User::USER_ROLE_UNKNOWN) {
            //$this->logger->debug(__CLASS__ . " Effective Permission NONE User Role is Unknown");
            return self::AUTH_NONE;
        }

        // .. Check user trust level
        /* TODO Check
        if ($user->getTrustLevel() == User::USER_TRUST_LEVEL_UNKNOWN) {
            $this->logger->debug(__CLASS__ . " Effective Permission NONE User Trust Level is Unknown");
            return self::AUTH_NONE;
        }
        */


        if ($user->getApprovalStatus() == User::APPROVAL_STATUS_APPROVED &&
            ($user->getUserRole() == User::USER_ROLE_VIEWER
                || $user->getAccountStatus() == User::ACCOUNT_STATUS_PENDING_FINALIZE
                || $user->getAccountStatus() == User::ACCOUNT_STATUS_PENDING_VERIFICATION))
        {
            // .. without channelId and teamId the Auth::VIEWER indicates global viewer for all channels and teams
            // .. that is only possible for service admin or sub admin.
            if (empty($channelId) && empty($teamId)) {
                //$this->logger->debug(__CLASS__ . " Effective Permission READ Global Context Requested");
                return self::AUTH_READ;
            }

            if (!empty($teamId)) {
                $teamUser = $this->dataController->getTeamUser($userId, $teamId);
                if (empty($teamUser)) {
                    $this->logger->debug(__CLASS__ . " Effective Permission NONE User $userId does not belong to Team $teamId");
                    return self::AUTH_NONE;
                }
            }

            if (!empty($channelId)){
                $channelUser = $this->dataController->getUserChannel($channelId, $userId);
                if (empty($channelUser)) {
                    //$this->logger->debug(__CLASS__ . " Effective Permission NONE User $userId does not belong to Channel $channelId");
                    return self::AUTH_NONE;
                }
            }

            // User exists for the given team or channel. However since the user has only viewer access, we provide Viewer access
            //$this->logger->debug(__CLASS__ . " Effective Permission Viewer User Role is Viewer and User $userId and user belongs to
            //                                            channelId or teamId passed.");
            return self::AUTH_READ;

        }
        // check for valid user status one final time before checking channel and team
        if ($user->getApprovalStatus() == User::APPROVAL_STATUS_APPROVED &&
            $user->getAccountStatus() == User::ACCOUNT_STATUS_ACTIVE &&
            $user->getUserRole() == User::USER_ROLE_EDITOR)
        {
            // .. without channelId and teamId the Auth::EDITOR indicates global editor for all channels and teams
            // .. that is only possible for service admin or sub admin.
            if (empty($channelId) && empty($teamId)) {
                //$this->logger->debug(__CLASS__ . " Effective Permission WRITE  Global Context Requested");
                return self::AUTH_WRITE;
            }

            if (!empty($channelId)) {
                $channel = $this->dataController->getChannelById($channelId);
                if (empty($channel)) {
                    //$this->logger->debug(__CLASS__ . " Effective Permission NONE User Role is Editor ChannelId $channelId is invalid.");
                    return self::AUTH_NONE;
                }

                // return AUTH_OWNER if the user is the owner of the channel
                if ($channel->getOwnedBy() === $userId) {
                    return self::AUTH_OWNER;
                }

                $channelUser = $this->dataController->getUserChannel($channelId, $userId);
                if (empty($channelUser)) {
                    //$this->logger->debug(__CLASS__ . " Effective Permission NONE User $userId does not belong to ChannelId $channelId.");
                    return self::AUTH_NONE;
                }

                // ... As long as the user belongs to the team in some capacity, we only care about channel user role here.
                switch($channelUser->getUserRole())
                {
                    case ChannelUser::CHANNEL_USER_ROLE_VIEWER:
                        //$this->logger->debug(__CLASS__ . " Effective Permission READ " . $this->getUserRoleString($user, $channelUser));
                        return self::AUTH_READ;
                        break;
                    case ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR:
                        return self::AUTH_READ;
                    case ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI:
                        //$this->logger->debug(__CLASS__ . " Effective Permission WRITE " . $this->getUserRoleString($user, $channelUser));
                        return self::AUTH_WRITE;
                        break;
                    case ChannelUser::CHANNEL_USER_ROLE_MANAGER:
                        //$this->logger->debug(__CLASS__ . " Effective Permission MANAGE " . $this->getUserRoleString($user, $channelUser));
                        return self::AUTH_MANAGE;
                        break;
                        break;
                    case ChannelUser::CHANNEL_USER_ROLE_ADMIN:
                        $this->logger->debug(__CLASS__ . " Effective Permission ADMIN " . $this->getUserRoleString($user, $channelUser));
                        return self::AUTH_ADMIN;
                        break;
                    default:
                        $this->logger->debug(__CLASS__ . " Effective Permission NONE Channel User Role UNKNOWN");
                        return self::AUTH_NONE;
                        break;
                }
            }

            if (empty($channelId) && !empty($teamId))
            {
                $teamUser = $this->dataController->getTeamUser($userId, $teamId);
                if (empty($teamUser)) {
                    $this->logger->debug(__CLASS__ . " Effective Permission NONE User $userId does not belong to TeamId $teamId.");
                    return self::AUTH_NONE;
                }

                switch($teamUser->getUserRole())
                {
                    case TeamUser::TEAM_USER_ROLE_MEMBER:
                        //$this->logger->debug(__CLASS__ . " Effective Permission READ " . $this->getUserRoleString($user, null, $teamUser));
                        return self::AUTH_READ;
                        break;
                    case TeamUser::TEAM_USER_ROLE_COLLABORATOR:
                        //$this->logger->debug(__CLASS__ . " Effective Permission WRITE " . $this->getUserRoleString($user, null, $teamUser));
                        return self::AUTH_WRITE;
                        break;
                    case TeamUser::TEAM_USER_ROLE_MANAGER:
                        //$this->logger->debug(__CLASS__ . " Effective Permission MANAGE " . $this->getUserRoleString($user, null, $teamUser));
                        return self::AUTH_MANAGE;
                        break;
                    case TeamUser::TEAM_USER_ROLE_OWNER:
                        //$this->logger->debug(__CLASS__ . " Effective Permission ADMIN " . $this->getUserRoleString($user, null, $teamUser));
                        return self::AUTH_ADMIN;
                        break;
                    default:
                        //$this->logger->debug(__CLASS__ . " Effective Permission NONE Team User Role UNKNOWN");
                        return self::AUTH_NONE;
                        break;
                }

            }
        }
        else
        {
            $this->logger->debug(__CLASS__ . " Effective Permission NONE Invalid User Approval Status/Account Status/User Role");
            return self::AUTH_NONE;
        }

    }


    private function getUserRoleString(User $user, ?ChannelUser $channelUser = null, ?TeamUser $teamUser = null)
    {
        if (empty($channelUser) && empty($teamUser)) {
            return "User Role " . $user->getUserRoleAsString();
        }
        else if (!empty($channelUser) && !empty($teamUser)) {
            return "User Role " . $user->getUserRoleAsString() . " Team User Role " . $teamUser->getUserRoleAsString() . " Channel User Role " . $channelUser->getUserRoleAsString();
        }
        else if (!empty($channelUser) && empty($teamUser)) {
            return "User Role " . $user->getUserRoleAsString() . " Channel User Role " . $channelUser->getUserRoleAsString();
        }
        else if (empty($channelUser) && !empty($teamUser)) {
            return "User Role " . $user->getUserRoleAsString() . " Team User Role " . $teamUser->getUserRoleAsString();
        }
    }




}