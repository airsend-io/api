<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Implementation\Objects;

use CodeLathe\Service\Storage\Contracts\Objects\FileInterface;

class File extends FSEntry implements FileInterface
{

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var string
     */
    protected $storageZone;

    /**
     * @var string
     */
    protected $storagePath;

    /**
     * @var string
     */
    protected $fileId;

    /**
     * @var bool
     */
    protected $complete;

    /**
     * @var string
     */
    protected $backstoreData;

    /**
     * @var string
     */
    protected $owner;

    /**
     * @var string|null
     */
    private $versionedDate;

    public function __construct(int $id,
                                string $path,
                                ?int $parentId,
                                string $extension,
                                int $size,
                                string $fileId,
                                string $storageZone,
                                string $storagePath,
                                ?string $backstoreData,
                                bool $complete,
                                string $createdOn,
                                ?string $modifiedOn,
                                ?string $lastAccessOn,
                                ?string $owner,
                                ?string $versionedDate = null)
    {
        $this->extension = strtolower($extension);
        $this->size = $size;
        $this->fileId = $fileId;
        $this->storageZone = $storageZone;
        $this->storagePath = $storagePath;
        $this->complete = $complete;
        $this->backstoreData = $backstoreData ?? '';
        $this->owner = $owner;
        $this->versionedDate = $versionedDate;
        parent::__construct($id, $path, $parentId, $createdOn, $modifiedOn, $lastAccessOn);
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getFileSize(): int
    {
        return $this->size;
    }

    public function getStorageZoneId(): string
    {
        return $this->storageZone;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function getBackstoreData(): string
    {
        return $this->backstoreData;
    }

    public function getOwner(): ?string
    {
        return $this->owner ?? null;
    }

    public function getVersionedDate(): ?string
    {
        return $this->versionedDate;
    }
}