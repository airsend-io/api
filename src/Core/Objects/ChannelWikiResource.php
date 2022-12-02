<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Utility\ContainerFacade;

/**
 * Class ChannelWikiResource
 * @package CodeLathe\Core\Objects
 * @deprecated - Replaced by the TranslatedPath object
 */
class ChannelWikiResource extends Resource
{
    public function __construct(string $resourceIdentifier)
    {
        $this->resourceIdentifier = $resourceIdentifier;
        $this->permissions = new ResourcePermission([ResourcePermission::READ => false,
            ResourcePermission::WRITE => false, ResourcePermission::DELETE => false]);
    }

    public function getResourcePrefix(): string
    {
        return "/wf";
    }

    public function translatePath(User $user, DataController $dc): bool
    {
        // ... Figure out if the given user can access this path
        // ... Look up FO ID ==> Channel ID
        $paths = preg_split('@/@', $this->resourceIdentifier, -1, PREG_SPLIT_NO_EMPTY);
        if (count($paths) < 2)
            return false;

        if ($paths[0] != "wf")
            return false;

        $foid = $paths[1];
        $channelPath = $dc->getChannelPathById((int)$foid);
        if ($channelPath == null)
            return false; // ... Not a valid file object mapping id

        if ($channelPath->getPathType() != ChannelPath::CHANNEL_PATH_TYPE_WIKI)
            return false; // ... Not a valid file object mapping id


        $chanID = (int)$channelPath->getChannelId();
        $this->channelId = $chanID;

        // check if the user is the public user, and if it's authorized to this channel
        $publicUserAllowed = ($user->getUserRole() === User::USER_ROLE_PUBLIC);

        $channelUser = $dc->getUserChannel($chanID, (int)$user->getId());
        if (!$publicUserAllowed && $channelUser == null)
            return false; // ... Not a user in this channel, bail

        $channel = $dc->getChannelById($chanID);
        if ($channel == null)
            return false; // ... Channel doesn't exist

        $gac = ContainerFacade::get(GlobalAuthContext::class);
        $authContext = $gac->getEffectivePermission($user, GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $chanID);
        if ($authContext < GlobalAuthContext::AUTH_READ) {
            return false; // ... No Read permissions
        }

        $allowWrite = $authContext >= GlobalAuthContext::AUTH_WRITE;
        $allowDelete = $authContext >= GlobalAuthContext::AUTH_MANAGE;



        // ... Made it this far, accessible for us
        $this->resourceDisplayPath = $this->resourceIdentifier;

        $this->translated = $channelPath->getPath();
        if (count($paths) > 1) {
            for ($idx = 2; $idx < count($paths); $idx++)
            {
                $this->resourceDisplayPath .= "/";
                $this->resourceDisplayPath .= $paths[$idx];

                $this->translated .= "/";
                $this->translated .= $paths[$idx];

                $this->relativePath .= "/";
                $this->relativePath .= $paths[$idx];
            }
        }

        $displayPath = Path::createFromPath($this->resourceDisplayPath);
        $this->resourceDisplayName = $displayPath->getName();

        $this->resourceBasePath = "/wf/" .$foid;

        if ($publicUserAllowed || $channelUser->getUserRole() == ChannelUser::CHANNEL_USER_ROLE_VIEWER) {
            $this->permissions = new ResourcePermission([
                ResourcePermission::LIST => true,
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => false,
                ResourcePermission::DELETE => false
            ]);
        } else if ($channelUser->getUserRole() == ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR_WIKI) {
            $this->permissions = new ResourcePermission([
                ResourcePermission::LIST => true,
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => $allowWrite,
                ResourcePermission::DELETE => false
            ]);
        } else if ($channelUser->getUserRole() == ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR) {
            $this->permissions = new ResourcePermission([
                ResourcePermission::LIST => true,
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => false,
                ResourcePermission::DELETE => false
            ]);
        } else if ($channelUser->getUserRole() == ChannelUser::CHANNEL_USER_ROLE_MANAGER) {
            $this->permissions = new ResourcePermission([
                ResourcePermission::LIST => true,
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => $allowWrite,
                ResourcePermission::DELETE => $allowWrite
            ]);
        } else if ($channelUser->getUserRole() == ChannelUser::CHANNEL_USER_ROLE_ADMIN) {
            $this->permissions = new ResourcePermission([
                ResourcePermission::LIST => true,
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => $allowWrite,
                ResourcePermission::DELETE => $allowDelete
            ]);
        }

        return true;
    }

    public function getResourceType(): string
    {
        return "wiki";
    }

    public function getAssociatedChannelInfo(DataController $dc, array &$channelInfoArray): bool
    {
        $channelPathID =  $this->getResourceObjectID();

        $channelPath = $dc->getChannelPathById($channelPathID);
        if (!isset($channelPath)) {
            throw new BadResourceTranslationException($channelPath);
        }
        $channelId = $channelPath->getChannelId();
        $channelFilePath = $this->getResourceIdentifier();
        $channelInfoArray[] = array('id' => $channelId, 'channelpath' => $channelFilePath);
        return true;
    }
}