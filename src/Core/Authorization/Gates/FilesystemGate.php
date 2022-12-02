<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Authorization\Gates;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Core\Objects\TeamUser;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Memoizer;

class FilesystemGate extends AbstractGate
{

    use AuthorizeSystemAdminTrait;

    protected $dataController;

    public function __construct(DataController $dataController)
    {
        $this->dataController = $dataController;
    }

    public function read(User $user, TranslatedPath $translatedPath): bool
    {

        // if there is a channel attached to the path, check user role on the channel
        $channel = $translatedPath->getChannel();
        if ($channel !== null) {

            // all channel members have read access to channel files
            if (Memoizer::memoized([$this->dataController, 'isChannelMember'])($channel->getId(), $user->getId())) {
                return true;
            }

            // if the public user made so far, we allow it to read the file
            if ($user->getUserRole() === User::USER_ROLE_PUBLIC) {
                return true;
            }

            return false;
        }

        $team = $translatedPath->getTeam();

        /** @var TeamUser $teamUser */
        $teamUser = Memoizer::memoized([$this->dataController, 'getTeamUser'])($user->getId(), $team->getId());

        // user have to be member of the team
        if ($teamUser === null) {
            return false;
        }

        // only team managers and the owner can access team storage directly
        if ($teamUser->getUserRole() < TeamUser::TEAM_USER_ROLE_MANAGER) {
            return false;
        }

        return true;

    }

    public function upload(User $user, TranslatedPath $translatedPath): bool
    {
        // if there is a channel attached to the path, check user role on the channel
        $channel = $translatedPath->getChannel();
        if ($channel !== null) {

            // user must be a member of the channel and at least a full collaborator role to write to storage
            $channelUser = Memoizer::memoized([$this->dataController, 'getUserChannel'])($channel->getId(), $user->getId());
            if ($channelUser instanceof ChannelUser && $channelUser->getUserRole() >= ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR) {
                return true;
            }

            // TODO - Include special permission for attachments folder (collaborador) and allow upload only for full collaborators in the rest of the storage

            return false;
        }

        $team = $translatedPath->getTeam();

        $teamUser = Memoizer::memoized([$this->dataController, 'getTeamUser'])($user->getId(), $team->getId());

        // user have to be member of the team
        if ($teamUser === null) {
            return false;
        }

        // only team managers and the owner can access team storage directly
        if ($teamUser->getUserRole() < TeamUser::TEAM_USER_ROLE_MANAGER) {
            return false;
        }

        // block write access to top level, Channels and deleted items roots
        $channelsName = FileOperations::getChannelsName();
        $deleteItemsName = FileOperations::getDeletedItemsName();
        switch ($translatedPath->getPhysicalPath()) {
            case "/{$team->getId()}":
            case "/{$team->getId()}/{$channelsName}":
            case "/{$team->getId()}/{$deleteItemsName}":
                return false;
            default:
                return true;
        }

    }

    public function delete(User $user, TranslatedPath $translatedPath): bool
    {
        // for now, who can upload a file, can also delete it...
        return $this->upload($user, $translatedPath);
    }

    public function createFolder(User $user, TranslatedPath $translatedPath): bool
    {

        // for now, we just use the upload permission
        return $this->upload($user, $translatedPath);

    }
}