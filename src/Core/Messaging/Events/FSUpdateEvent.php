<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\TranslatedPath;

class FSUpdateEvent extends FSEvent implements RtmInterface
{

    /**
     * @var TranslatedPath
     */
    protected $fromTranslatedPath;

    /**
     * @var string
     */
    protected $fromName;

    /**
     * @var int
     */
    protected $fromId;

    /**
     * @var string
     */
    protected $fileType;

    /**
     * @var int|null
     */
    protected $size;

    public function __construct(TranslatedPath $fromTranslatedPath,
                                TranslatedPath $toTranslatedPath,
                                int $fromId,
                                int $toId,
                                string $fromName,
                                string $toName,
                                int $userId,
                                string $fileType = 'file',
                                ?int $size = null,
                                bool $genAirBot = false)
    {
        $this->fromTranslatedPath = $fromTranslatedPath;
        $this->fromName = $fromName;
        $this->fromId = $fromId;
        $this->fileType = $fileType;
        $this->size = $size;
        parent::__construct($toTranslatedPath, $toId, $toName, $userId, $genAirBot);
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    public static function eventName(): string
    {
        return 'fs.update';
    }

    /**
     * @return string
     */
    public function getFileType(): string
    {
        return $this->fileType;
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * @return TranslatedPath
     */
    public function getFromTranslatedPath(): TranslatedPath
    {
        return $this->fromTranslatedPath;
    }
}