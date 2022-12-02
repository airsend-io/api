<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Shared;


/**
 * Class FolderPropertiesObject
 * @package CodeLathe\Service\Storage\Shared
 */
class FolderPropertiesObject
{
    private $totalFolderCount = 0;
    private $totalLiveFileCount = 0;
    private $totalLiveFileSize = 0;
    private $totalNonLiveFileCount = 0;
    private $totalNonLiveFileSize = 0;
    private $totalSidecarFileCount = 0;
    private $totalSidecarFileSize = 0;
    private $totalFileCount = 0;
    private $totalFileSize = 0;

    /**
     * @return int
     */
    public function getTotalFolderCount() : int
    {
        return $this->totalFolderCount;
    }

    /**
     * @param int $totalFolderCount
     */
    public function setTotalFolderCount(int $totalFolderCount): void
    {
        $this->totalFolderCount = $totalFolderCount;
    }

    /**
     * @return mixed
     */
    public function getTotalLiveFileCount() : int
    {
        return $this->totalLiveFileCount;
    }

    /**
     * @param int $totalLiveFileCount
     */
    public function setTotalLiveFileCount(int $totalLiveFileCount): void
    {
        $this->totalLiveFileCount = $totalLiveFileCount;
    }

    /**
     * @return int
     */
    public function getTotalLiveFileSize() : int
    {
        return $this->totalLiveFileSize;
    }

    /**
     * @param int $totalLiveFileSize
     */
    public function setTotalLiveFileSize(int $totalLiveFileSize): void
    {
        $this->totalLiveFileSize = $totalLiveFileSize;
    }

    /**
     * @return int
     */
    public function getTotalNonLiveFileCount() : int
    {
        return $this->totalNonLiveFileCount;
    }

    /**
     * @param int $totalNonLiveFileCount
     */
    public function setTotalNonLiveFileCount(int $totalNonLiveFileCount): void
    {
        $this->totalNonLiveFileCount = $totalNonLiveFileCount;
    }

    /**
     * @return int
     */
    public function getTotalNonLiveFileSize() :int
    {
        return $this->totalNonLiveFileSize;
    }

    /**
     * @param int $totalNonLiveFileSize
     */
    public function setTotalNonLiveFileSize(int $totalNonLiveFileSize): void
    {
        $this->totalNonLiveFileSize = $totalNonLiveFileSize;
    }

    /**
     * @return int
     */
    public function getTotalFileCount() : int
    {
        return $this->totalFileCount;
    }

    /**
     * @param int $totalFileCount
     */
    public function setTotalFileCount(int $totalFileCount): void
    {
        $this->totalFileCount = $totalFileCount;
    }

    /**
     * @return int
     */
    public function getTotalFileSize() : int
    {
        return $this->totalFileSize;
    }

    /**
     * @param int $totalFileSize
     */
    public function setTotalFileSize(int $totalFileSize): void
    {
        $this->totalFileSize = $totalFileSize;
    }

    /**
     * @return int
     */
    public function getTotalSidecarFileCount(): int
    {
        return $this->totalSidecarFileCount;
    }

    /**
     * @param int $totalSidecarFileCount
     */
    public function setTotalSidecarFileCount(int $totalSidecarFileCount): void
    {
        $this->totalSidecarFileCount = $totalSidecarFileCount;
    }

    /**
     * @return int
     */
    public function getTotalSidecarFileSize(): int
    {
        return $this->totalSidecarFileSize;
    }

    /**
     * @param int $totalSidecarFileSize
     */
    public function setTotalSidecarFileSize(int $totalSidecarFileSize): void
    {
        $this->totalSidecarFileSize = $totalSidecarFileSize;
    }
}