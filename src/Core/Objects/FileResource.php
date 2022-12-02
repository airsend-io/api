<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\TeamOpException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use Psr\Log\LoggerInterface;

class FileResource extends Resource
{

    public function __construct(string $resourceIdentifier)
    {
        $this->resourceIdentifier = $resourceIdentifier;
    }

    public function getResourcePrefix(): string
    {
        return "/f";
    }

    public function resolve(User $user): FileResource
    {
        return $this;
    }

    public function translatePath(User $user, DataController $dc): bool
    {
        $logger = ContainerFacade::get(LoggerInterface::class);

        // ... Figure out if the given user can access this path
        // ... Look up TEAM ID
        $paths = preg_split('@/@', $this->resourceIdentifier, -1, PREG_SPLIT_NO_EMPTY);
        if (count($paths) < 2) {
            $logger->info("Translate Path failed: Less then 2 parts ".$this->resourceIdentifier);
            return false;
        }

        if ($paths[0] != "f") {
            $logger->info("Translate Path failed: Bad Prefix ".$this->resourceIdentifier);
            return false;
        }

        $teamid = (int)$paths[1];
        $team = $dc->getTeamByTeamId((int)$teamid);
        if ($team == null) {
            $logger->info("Translate Path failed: Bad Team ".$this->resourceIdentifier);
            return false;
        } // ... Not a valid team

        if ($user->getUserRole() != User::USER_ROLE_SERVICE_ADMIN) {
            // ... User can only touch direct file paths that belong to his team for now
            $defaultTeamForUser = $dc->getDefaultTeamForUser($user->getId());
            if (empty($defaultTeamForUser)) {
                $logger->info("Translate Path failed: Default Team is invalid for user ".$this->resourceIdentifier);
                return false;
            }

            if ($defaultTeamForUser->getId() != $teamid) {
                $logger->info("Translate Path failed: Default Team ".$defaultTeamForUser->getId()." is not path's teamid ".$this->resourceIdentifier);
                return false;
            }
        }

        $gac = ContainerFacade::get(GlobalAuthContext::class);
        $authContext = $gac->getEffectivePermission($user->getId(), GlobalAuthContext::CONTEXT_TYPE_TEAM, $teamid);
        if ($authContext < GlobalAuthContext::AUTH_READ) {
            $logger->info("Translate Path failed: No AuthContext Team Read Perms".$this->resourceIdentifier);
            return false; // ... No Read permissions
        }


        // ... Made it this far, accessible for us
        $this->translated = $this->getResourceIdentifier();

        $this->resourceDisplayPath = "/My Files";
        $this->relativePath = "";
        if (count($paths) > 1) {
            for ($idx = 2; $idx < count($paths); $idx++)
            {
                $this->resourceDisplayPath .= "/";
                $this->resourceDisplayPath .= $paths[$idx];

                $this->relativePath .= "/";
                $this->relativePath .= $paths[$idx];
            }
        }

        $displayPath = Path::createFromPath($this->resourceDisplayPath);
        $this->resourceDisplayName = $displayPath->getName();

        $this->resourceBasePath = "/f/" .$teamid;

        $this->permissions = new ResourcePermission([
            ResourcePermission::LIST => $authContext >= GlobalAuthContext::AUTH_READ,
            ResourcePermission::READ => $authContext >= GlobalAuthContext::AUTH_READ,
            ResourcePermission::WRITE => $authContext >= GlobalAuthContext::AUTH_WRITE,
            ResourcePermission::DELETE => $authContext >= GlobalAuthContext::AUTH_WRITE,
            ResourcePermission::OWNER => $authContext >= GlobalAuthContext::AUTH_OWNER
        ]);



        return true;
    }

    public function getResourceType(): string
    {
        return "files";
    }

    public function isDeletedItemsPath()
    {
        if (StringUtility::startsWith($this->resourceIdentifier, "/f/".$this->getResourceObjectID()."/".FileOperations::getDeletedItemsName()))
            return true;

        // ... This is a hack for now, searching for any "deleted items" entry anywhere in the path, but is strictly not right
        if (strpos($this->resourceIdentifier, FileOperations::getDeletedItemsName())!== FALSE)
            return true;

        return false;
    }

    public function getAssociatedChannelInfo(DataController $dc,  array &$channelInfoArray): bool
    {
        $paths = preg_split('@/@', $this->resourceIdentifier, -1, PREG_SPLIT_NO_EMPTY);
        if (count($paths) < 2) {
            return false;
        }

        if ($paths[0] != "f") {
            return false; // No channel associated with this file
        }

        // ... Let's see which channels this file is associated with
        // ... channel paths are /f/<ID>/Channels/<Channel Name>/files/filepath
        if (count($paths) >= 5)
        {
            if ($paths[2] == FileOperations::getChannelsName() && $paths[4] == FileOperations::getChannelFilesName()) {
                $channelBasePath = "";
                for ($idx = 0; $idx < 5; $idx++) {
                    $channelBasePath .= "/";
                    $channelBasePath .= $paths[$idx];
                }

                $channelPaths = $dc->getChannelPathsByValue($channelBasePath);
                foreach ($channelPaths as $channelPathData) {
                    $channelPath = ChannelPath::withDBData($channelPathData);
                    $associatedId = $channelPath->getChannelId();

                    $channelFilePath = '/cf/' . $channelPath->getId();
                    for ($idx = 5; $idx < count($paths); $idx++) {
                        $channelFilePath .= '/';
                        $channelFilePath .= $paths[$idx];
                    }

                    $channelInfoArray[] = array('id' => $associatedId, 'channelpath' => $channelFilePath);
                }
            }
        }

        if (count($channelInfoArray) == 0)
            return false;

        return true;
    }
}