<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Implementation\Objects;

use CodeLathe\Core\Objects\ObjectInterface;

class FolderProperties implements \JsonSerializable, ObjectInterface
{
    /**
     * @var int
     */
    protected $folderCount;

    /**
     * @var int
     */
    protected $fileCount;

    /**
     * @var int
     */
    protected $fileSize;

    /**
     * @var int
     */
    protected $sidecarCount;

    /**
     * @var int
     */
    protected $sidecarSize;

    /**
     * @var int
     */
    protected $versionsCount;

    /**
     * @var int
     */
    protected $versionsSize;


    public function __construct(int $folderCount, int $fileCount, int $fileSize, int $versionsCount, int $versionsSize, int $sidecarCount, int $sidecarSize)
    {
        $this->folderCount = $folderCount;
        $this->fileCount = $fileCount;
        $this->fileSize = $fileSize;
        $this->sidecarCount = $sidecarCount;
        $this->sidecarSize = $sidecarSize;
        $this->versionsCount = $versionsCount;
        $this->versionsSize = $versionsSize;
    }

    /**
     * @return int
     */
    public function getFolderCount(): int
    {
        return $this->folderCount;
    }

    /**
     * @return int
     */
    public function getFileCount(): int
    {
        return $this->fileCount;
    }

    /**
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * @return int
     */
    public function getSidecarCount(): int
    {
        return $this->sidecarCount;
    }

    /**
     * @return int
     */
    public function getSidecarSize(): int
    {
        return $this->sidecarSize;
    }

    public function getTotalCount(): int
    {
        return $this->folderCount + $this->fileCount + $this->versionsCount + $this->sidecarCount;
    }

    public function getTotalSize(): int
    {
        return $this->fileSize + $this->versionsSize + $this->sidecarSize;
    }

    /**
     * @return int
     */
    public function getVersionsCount(): int
    {
        return $this->versionsCount;
    }

    /**
     * @return int
     */
    public function getVersionsSize(): int
    {
        return $this->versionsSize;
    }


    public function getArray(): array
    {
        return [
            'folderCount' => $this->folderCount,
            'fileCount' => $this->fileCount,
            'fileSize' => $this->fileSize,
            'sidecarCount' => $this->sidecarCount,
            'sidecarSize' => $this->sidecarSize,
            'versionsCount' => $this->versionsCount,
            'versionsSize' => $this->versionsSize,
        ];
    }

    public function jsonSerialize()
    {
        return $this->getArray();
    }
}
