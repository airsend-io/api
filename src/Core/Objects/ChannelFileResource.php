<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\StringUtility;
use Psr\Log\LoggerInterface;

/**
 * Class ChannelFileResource
 * @package CodeLathe\Core\Objects
 * @deprecated - replaced with the TranslatedPath object
 */
class ChannelFileResource extends Resource
{
    public function __construct(string $resourceIdentifier)
    {
        $this->resourceIdentifier = $resourceIdentifier;
        $this->permissions = new ResourcePermission([ResourcePermission::READ => false,
            ResourcePermission::WRITE => false, ResourcePermission::DELETE => false]);
    }

    public function getResourcePrefix(): string
    {
        return "/cf";
    }

    public function translatePath(User $user, DataController $dc): bool
    {

        $logger = ContainerFacade::get(LoggerInterface::class);

        /** @var GlobalAuthContext $gac */
        $gac = ContainerFacade::get(GlobalAuthContext::class);

        if ($this->resourceIdentifier == "/cf")
        {
            $this->resourceDisplayName = "Shared Channels";
            $this->resourceDisplayPath = "/cf";
            $this->resourceBasePath = "/cf";

            $this->permissions = new ResourcePermission([
                ResourcePermission::LIST => true,
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => false,
                ResourcePermission::DELETE => false
            ]);

            return true;
        }

        // ... Figure out if the given user can access this path
        // ... Look up FO ID ==> Channel ID
        $paths = preg_split('@/@', $this->resourceIdentifier, -1, PREG_SPLIT_NO_EMPTY);
        if (count($paths) < 2) {
            $logger->info("Translate Path failed: Less then 2 parts ".$this->resourceIdentifier);
            return false;
        }

        if ($paths[0] != "cf") {
            $logger->info("Translate Path failed: Invalid Prefix ".$this->resourceIdentifier);
            return false;
        }

        $foid = $paths[1];
        $channelPath = $dc->getChannelPathById((int)$foid);
        if ($channelPath == null) {
            $logger->info("Translate Path failed: Invalid Channel Path for ".$this->resourceIdentifier);
            return false; // ... Not a valid file object mapping id
        }

        if ($channelPath->getPathType() != ChannelPath::CHANNEL_PATH_TYPE_FILE) {
            $logger->info("Translate Path failed: Invalid Channel Path File Object for ".$this->resourceIdentifier);
            return false; // ... Not a valid file object mapping id
        }

        $chanID = (int)$channelPath->getChannelId();
        $this->channelId = $chanID;

        // TODO: WARNING FIX THIS PROPERLY later
        if (!User::isServiceAdmin($user->getId())) {

            // check if the user is the public user
            $publicUserAllowed = ($user->getUserRole() === User::USER_ROLE_PUBLIC);

            $channelUser = $dc->getUserChannel($chanID, (int)$user->getId());
            if (!$publicUserAllowed && $channelUser === null) {
                $logger->info("Translate Path failed: Invalid Channel User for " . $this->resourceIdentifier);
                return false; // ... Not a user in this channel, bail
            }

            $dc->getCacheDataController()->forgetGlobalAuthContextCache($user->getId());
            $authContext = $gac->getEffectivePermission($user, GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $chanID);

            //$logger->debug("Channel Translate Path: Auth Context: ".print_r($authContext, true));
            if ($authContext < GlobalAuthContext::AUTH_READ) {
                $logger->info("Translate Path failed: No Channel Read Perms " . $this->resourceIdentifier);
                return false; // ... No Read permissions
            }

            $permissions = [
                ResourcePermission::LIST => $authContext >= GlobalAuthContext::AUTH_READ,
                ResourcePermission::READ => $authContext >= GlobalAuthContext::AUTH_READ,
                ResourcePermission::WRITE => $authContext >= GlobalAuthContext::AUTH_WRITE,
                ResourcePermission::DELETE => $authContext >= GlobalAuthContext::AUTH_WRITE,
            ];

            // if the user is less than a full_colaborator on the channel, deny writes (uploads) and deletes
            if ($channelUser !== null && $channelUser->getUserRole() < ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR) {
                $permissions[ResourcePermission::WRITE] = false;
                $permissions[ResourcePermission::DELETE] = false;
            }

            $this->permissions = new ResourcePermission($permissions);

        } else {
            $this->permissions = new ResourcePermission([
                ResourcePermission::READ => true,
                ResourcePermission::WRITE => true,
                ResourcePermission::DELETE => true
            ]);

        }

        $channel = $dc->getChannelById($chanID);
        if ($channel == null) {
            $logger->info("Translate Path failed: Invalid Channel ".$this->resourceIdentifier);
            return false; // ... Channel doesn't exist
        }

        // ... Made it this far, accessible for us
        $this->translated = $channelPath->getPath();
        if (count($paths) > 1) {
            for ($idx = 2; $idx < count($paths); $idx++)
            {
                $this->translated .= "/";
                $this->translated .= $paths[$idx];
            }
        }

        $this->resourceDisplayPath = "/Shared Channels/".$channel->getName();
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
        $this->resourceBasePath = "/cf/" .$foid;

        return true;
    }

    public function getResourceType(): string
    {
        return "files";
    }

    public function getAssociatedChannelInfo(DataController $dc,  array &$channelInfoArray): bool
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

    public function isAttachmentsPath(): bool
    {
        $apath = $this->resourceBasePath. "/attachments";
        if (StringUtility::startsWith($this->getResourceIdentifier(), $apath))
            return true;

        return false;
    }
}