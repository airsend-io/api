<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Serializers\JSONSerializer;
use CodeLathe\Core\Utility\StringUtility;

/**
 * Class Resource
 *
 * Resources are objects that the AirSend Channel depends upon
 * They will represent things like files, wiki pages etc
 *
 * Resources are referred to using Resource Identifiers.
 *
 * Resource Identifiers are usually formatted like below
 * /<ResourcePrefix>/<Resource Path>
 *
 * @package CodeLathe\Core\Objects
 * @deprecated - Replaced with the TranslatedPath object
 */
abstract class Resource implements \JsonSerializable
{
    protected $resourceIdentifier;
    protected $translated = ""; // Handle to the resolved FileResource
    protected $permissions = null; // Handle to Resource Permissions

    protected $resourceDisplayName = ""; // Special name associated with this Resource, 'My Files', 'Channel Name', 'Team Name'
    protected $resourceDisplayPath = ""; // Full Path with any IDs converted to Display Names

    protected $resourceBasePath; // for channel paths, it will /cf/<CHANNELPATHID>

    protected $relativePath; // Remaining Path after object ID /f/<OBJECTID>/relativePath

    protected $channelId = null;

    /**
     * Returns the Prefix for Resources
     * Currently, these are the known resource prefixes
     * /f/ - for file resources
     * /cf/ - for channel file resources
     *
     * @return string
     */
    public abstract function getResourcePrefix(): string;
    public abstract function getResourceType(): string;
    public abstract function getAssociatedChannelInfo(DataController $dc, array &$channelInfoArray): bool;

    public abstract function translatePath(User $user, DataController $dc): bool;

    public function getResourceDisplayPath() : string
    {
        return $this->resourceDisplayPath;
    }

    public function getResourceDisplayName() : string
    {
        return $this->resourceDisplayName;
    }

    public function getRelativePath() : string
    {
        return $this->relativePath;
    }

    public function getPermissions(): ?ResourcePermission
    {
        return $this->permissions;
    }

    public function getTranslatedPath(): string
    {
        return $this->translated;
    }

    public function getResourceBasePath()
    {
        return $this->resourceBasePath;
    }

    /**
     * Returns the full Resource Identifier for a given resource
     *
     * @return string
     */
    public function getResourceIdentifier(): string
    {
        return $this->resourceIdentifier;
    }

    /**
     * Transforms a given resource into a raw resource representation
     *
     * @return string
     */
    public function transform()
    {
        $path =  trim(StringUtility::str_replace_first($this->getResourcePrefix(), "", $this->getResourceIdentifier()));
        if ($path == "")
        {
            $path = "/";
        }
        return $path;
    }

    public function getChannelId(): ?int
    {
        return $this->channelId;
    }

    /**
     * Returns the object ID associated with this resource
     * typically resources look like
     * /f|cf/wf/<OBJECTID/Path
     *
     * @return int
     * @throws UnknownResourceException
     */
    public function getResourceObjectID() : int
    {
        $paths = preg_split('@/@', $this->resourceIdentifier, -1, PREG_SPLIT_NO_EMPTY);
        if (count($paths) < 2)
            throw new UnknownResourceException();

        if (($paths[0] == "f") || ($paths[0] == "wf") || ($paths[0] == "cf"))
        {
            return (int)$paths[1];
        }

        // ... Note: Some paths like /f/ don't have object id
        throw new UnknownResourceException();
    }


    public function jsonSerialize()
    {
        $data = array();
        $data['type'] = $this->getResourceType();
        $data['location'] = $this->getResourceIdentifier();
        return $data;
    }
}