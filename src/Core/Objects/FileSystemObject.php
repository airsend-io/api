<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FolderInterface;
use CodeLathe\Service\Storage\Contracts\Objects\FSEntryInterface;
use CodeLathe\Service\Storage\Shared\StorageObject;

abstract class FileSystemObject implements \JsonSerializable, ObjectInterface
{
    private $props;

    /**
     * @param StorageObject $storageObject
     * @param string $resourcePrefix
     * @param string $resourceDisplayPathPrefix
     * @param array $flags
     * @throws \Exception
     * @deprecated
     */
    protected function loadFromStorageObject(StorageObject $storageObject, string $resourcePrefix, string $resourceDisplayPathPrefix, array $flags = array())
    {
        $this->props['id'] = $storageObject->getObjectId();
        $this->props['name'] = $storageObject->getName();
        $this->props['displayname'] = $storageObject->getName();
        $this->props['fullpath'] = $resourcePrefix.'/'.$storageObject->getName();
        $this->props['displaypath'] = $resourceDisplayPathPrefix.'/'.$storageObject->getName();
        $this->props['parent'] = $resourcePrefix;
        $this->props['ext'] = $storageObject->getExtension() ?? "";
        $this->props['type'] = $storageObject->getType();
        $this->props['size'] = $storageObject->getFileSize();
        $this->props['creation'] = $storageObject->getCreationDate();
        $this->props['modification'] = $storageObject->getModificationDate();
        $this->props['access'] = $storageObject->getLastAccessDate() ?? "";
        $this->props['versionidentifier'] = $storageObject->getObjectVersionIdentifier() ?? "";
        $this->props['syncversion'] = $storageObject->getSyncVersionId();
        $this->props['by'] = $storageObject->getOwner();
        $this->props['flags'] = $flags;
    }

    public function loadFromData(string $name, string $path, string $ext = "", string $type = "folder", int $size = 0,
        string $creation = "", string $modification = "", string $access = "", string $displayName = "",string $displayPath = "", string $syncversion = "", array $flags = array())
    {
        $this->props['name'] = $name;
        $this->props['displayname'] = empty($displayName) ? $name : $displayName ;
        $this->props['displaypath'] = empty($displayPath) ? $path. '/'. $name : $displayPath ;
        $this->props['fullpath'] = $path. '/'. $name;
        $this->props['parent'] = $path;
        $this->props['ext'] = $ext;
        $this->props['type'] = $type;
        $this->props['size'] = $size;
        $this->props['creation'] = $creation;
        $this->props['modification'] = $modification;
        $this->props['access'] = $access;
        $this->props['versionidentifier'] = ""; // not available
        $this->props['syncversion'] = $syncversion; // not available
        $this->props['flags'] = $flags;
        $this->props['by'] = "";
    }

    protected static function getPathFlags(string $path, array $flags = []): array
    {

        $path = preg_replace('/^\/f/', '', $path);

        // system folders patterns
        $systemPatterns = [
            '/^\/[0-9]+$/', // root team folder
            '/^\/[0-9]+\/Channels$/', // root team channels folder
            '/^\/[0-9]+\/Channels\/[^\/]+$/', // root channel folder
            '/^\/[0-9]+\/Channels\/[^\/]+\/files$/', // root channel files folder
            '/^\/[0-9]+\/Channels\/[^\/]+\/wiki$/', // root channel wiki folder
            '/^\/[0-9]+\/Channels\/[^\/]+\/deleted items$/', // root channel deleted items folder
            '/^\/[0-9]+\/Channels\/[^\/]+\/files\/attachments$$/' // channel attachments folder
        ];

        foreach ($systemPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                $flags = array_merge($flags, ['SYSTEM']);
            }
        }

        // ... other patterns

        return $flags;
    }

    /**
     * @param FSEntryInterface $fsEntry
     * @param string|null $relativePath
     * @param string $displayPath
     * @param string $versionId
     * @return static
     */
    public static function createFromFSEntry(FSEntryInterface $fsEntry,
                                             ?string $relativePath = null,
                                             string $displayPath = '',
                                             string $versionId = ''): self
    {

        /** @var self $fso */
        if ($fsEntry instanceof FolderInterface) {
            $fso = new Folder();
        } else {
            $fso = new File();
        }

        $displayName = $fsEntry->getName();
        if (preg_match('/[^\/]+$/', $displayPath, $matches)) {
            $displayName = $matches[0];
        }

        $flags = static::getPathFlags($fsEntry->getPath());

        $fso->props['id'] = $fsEntry->getId();
        $fso->props['name'] = $fsEntry->getName();
        $fso->props['displayname'] = $displayName;
        $fso->props['fullpath'] = $relativePath ?? "/f{$fsEntry->getPath()}";
        $fso->props['displaypath'] = $displayPath;

        preg_match('/(.*)\/[^\/]*$/', $fso->props['fullpath'], $matches);
        $fso->props['parent'] = $matches[1] ?? '';

        if (in_array('SYSTEM', $flags)) {
            $fso->props['creation'] = null;
            $fso->props['modification'] = null;
        } else {
            $fso->props['creation'] = $fsEntry->getCreatedOn()->format('Y-m-d H:i:s');
            $fso->props['modification'] = $fsEntry->getModifiedOn() === null ? '' :  $fsEntry->getModifiedOn()->format('Y-m-d H:i:s');
        }
        $fso->props['access'] = $fsEntry->getLastAccessOn() === null ? '' : $fsEntry->getLastAccessOn()->format('Y-m-d H:i:s');
        $fso->props['versionidentifier'] = $versionId;
//        $fso->props['syncversion'] = $storageObject->getSyncVersionId();
        $fso->props['flags'] = $flags;


        if ($fsEntry instanceof FolderInterface) {
            $fso->props['type'] = 'folder';
        }

        if ($fsEntry instanceof FileInterface) {
            $fso->props['by'] = $fsEntry->getOwner();
            $fso->props['type'] = 'file';
            $fso->props['ext'] = $fsEntry->getExtension();
            $fso->props['size'] = $fsEntry->getFileSize();
        }

        return $fso;
    }

    /**
     * @param StorageObject $storageObject
     * @param string $resourcePrefix
     * @param string $resourceDisplayPathPrefix
     * @param array $flags
     * @return File|Folder
     * @throws \Exception
     * @deprecated
     */
    public static function create(StorageObject $storageObject, string $resourcePrefix = "", string $resourceDisplayPathPrefix = "", array $flags = array())
    {
        if ($storageObject->isFolder())
            $fo = new Folder();
        else
            $fo = new File();

        $fo->loadFromStorageObject($storageObject, $resourcePrefix, $resourceDisplayPathPrefix, $flags);
        return $fo;
    }

    public static function createFromData(string $name,
                                          string $path,
                                          string $ext = "",
                                          string $type = "folder",
                                          int $size = 0,
                                          string $creation = "",
                                          string $modification = "",
                                          string $access = "",
                                          string $displayName = "",
                                          string $displayPath = "",
                                          string $syncversion = "",
                                          array $flags = [])
    {
        $fo = $type  === "folder" ? new Folder() : new File();
        $fo->loadFromData($name, $path, $ext, $type, $size, $creation, $modification, $access, $displayName, $displayPath, $syncversion, $flags);
        return $fo;
    }

     /**
      * @return array|mixed
      * @deprecated Must be implemented on the child class
      */
    abstract public function jsonSerialize();

    public function canDownload() : bool
    {
        if ($this->isFlagSet('SYSTEM'))
            return false;
        return true;
    }

    public function canMove() : bool
    {
        if ($this->isFlagSet('SYSTEM'))
            return false;
        if ($this->isFlagSet('TOPLEVEL'))
            return false;
        return true;
    }

    public function canDelete() : bool
    {
        if ($this->isFlagSet('SYSTEM'))
            return false;
        if ($this->isFlagSet('TOPLEVEL'))
            return false;
        return true;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return  $this->props['name'];
    }

    /**
     * @return string
     */
    public function getDisplayName() : string
    {
        return  $this->props['displayname'];
    }

    public function setDisplayName($displayName)
    {
       $this->props['displayname'] = $displayName;
    }


    /**
     * @return string
     */
    public function getFullPath() : string
    {
        return  $this->props['fullpath'];
    }

    /**
     * @return string
     */
    public function getDisplayPath() : string
    {
        return  $this->props['displaypath'];
    }

    public function setDisplayPath($path)
    {
        return $this->props['displaypath'] = $path;
    }

    /**
     * @return string|null
     */
    public function getExtension() : ?string
    {
        return $this->props['ext'];
    }

    /**
     * @return string|null
     */
    public function getType() : ?string
    {
        return $this->props['type'];
    }

    /**
     * @return float
     */
    public function getSize() : float
    {
        return  $this->props['size'];
    }

    /**
     * @return string
     */
    public function getCreationDate() : ?string
    {
        return $this->props['creation'];
    }

    /**
     * @return string
     */
    public function getModificationDate() : ?string
    {
        return $this->props['modification'];
    }

    public function getAccessDate() : string
    {
        return $this->props['access'];
    }

    public function getBy() : string
    {
        return $this->props['by'];
    }

    public function getParent()
    {
        return $this->props['parent'];
    }

    public function getVersionIdentifier()
    {
        return $this->props['versionidentifier'];
    }

    public function getSyncVersion()
    {
        return $this->props['syncversion'];
    }

    public function setFlags($flags)
    {
        $this->props['flags'] = array_merge($this->props['flags'], $flags);
    }

    public function getFlags(): array
    {
        return $this->props['flags'];
    }

    public function isFlagSet(string $key): bool
    {
        return in_array($key, $this->props['flags']);
    }

    public function getId(): ?int
    {
        return (int) $this->props['id'] ?? null;
    }

    public function getArray(): array
    {
        return $this->jsonSerialize();
    }
}