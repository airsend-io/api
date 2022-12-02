<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

use CodeLathe\Core\Objects\TranslatedPath;

abstract class FSEvent extends ASEvent implements RtmInterface
{

    /**
     * @var TranslatedPath
     */
    protected $translatedPath;


    protected $userId;
    protected $filename;
    protected $genAirBot;
    protected $channelId;
    protected $fspath;
    protected $fileId;

    public function __construct(TranslatedPath $translatedPath, int $fileId, string $fileName, int $userId, bool $genAirBot)
    {
        $this->translatedPath = $translatedPath;
        $this->userId = $userId;
        $this->filename = $fileName;
        $this->genAirBot = $genAirBot;
        $this->channelId = $translatedPath->getChannel()->getId();
        $this->fileId = $fileId;
    }

    public function getTranslatedPath(): TranslatedPath
    {
        return $this->translatedPath;
    }

    /**
     * Returns the User who performed this action
     * @return int
     */
    public function getUserID(): int
    {
        return $this->userId;
    }

    /**
     * Returns the file name associated with this action. Might be empty sometimes
     * @return string
     */
    public function getFileName()
    {
        return $this->filename;
    }

    /**
     * Whether to generate a AirBot message or not
     * @return bool
     */
    public function genAirBot(): bool
    {
        return $this->genAirBot;
    }

    /**
     * This method is to get the base event Name
     * @return string
     */
    abstract public static function eventName(): string;

    /**
     * Get array representation of the event payload
     * @return array
     */
    public function getPayloadArray(): array
    {
        return [$this->translatedPath->getPrefixedPath()];
    }

    /**
     * Every RTM event is associated with a channel. Any event
     * that is a RTM event must be able to provide this
     * @return int
     */
    public function getAssociatedChannelId(): int
    {
        // ... This might return -1 if no channel is found
        return $this->channelId;
    }

    /**
     * Returns the associated FS path, it can be a channel path if available or full fs path if not associated with the channel
     * @return string
     */
    public function getAssociatedFSPath() : string
    {
        return $this->translatedPath->getPath();
    }

    public function getFileId(): int
    {
        return $this->fileId;
    }
}