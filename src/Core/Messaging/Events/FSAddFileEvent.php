<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\TranslatedPath;

class FSAddFileEvent extends FSEvent implements RtmInterface
{

    /**
     * @var int
     */
    protected $fileSize;

    /**
     * @var bool
     */
    protected $isNew;

    public function __construct(TranslatedPath $translatedPath, int $fileId, string $fileName, int $userid, int $fileSize, bool $isNew, bool $genAirBot)
    {
        $this->fileSize = $fileSize;
        $this->isNew = $isNew;

        parent::__construct($translatedPath, $fileId, $fileName, $userid, $genAirBot);
    }

    /**
     * Returns the filesize associated with this event
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    public static function eventName(): string
    {
        return 'fs.add';
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }
}