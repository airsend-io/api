<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Files;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Indexers\FileIndexer;
use CodeLathe\Core\Managers\Chat\ChatOperations;
use CodeLathe\Core\Messaging\Events\FSAddFileEvent;
use CodeLathe\Core\Messaging\Events\FSCopyEvent;
use CodeLathe\Core\Messaging\Events\FSCreateFolderEvent;
use CodeLathe\Core\Messaging\Events\FSDeleteEvent;
use CodeLathe\Core\Messaging\Events\FSEvent;
use CodeLathe\Core\Messaging\Events\FSUpdateEvent;
use CodeLathe\Core\Messaging\Notification\NotificationFilter;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\MessageBot;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Objects\TranslatedPath;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\FileSize;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FileEventHandler implements EventSubscriberInterface
{
    protected $logger;
    protected $chat;

    /**
     * @var DataController
     */
    protected $dc;

    /**
     * @var FileIndexer
     */
    protected $fileIndexer;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var NotificationFilter
     */
    protected $notificationFilter;

    /**
     * @var FileOperations
     */
    protected $fops;

    /**
     * @var StorageServiceInterface
     */
    protected $storageService;

    public function __construct(LoggerInterface $logger,
                                ChatOperations $chat,
                                DataController $dc,
                                StorageServiceInterface $storageService,
                                FileIndexer $fileIndexer,
                                ConfigRegistry $config,
                                NotificationFilter $notificationFilter,
                                FileOperations $fops)
    {
        $this->logger = $logger;
        $this->chat = $chat;
        $this->dc = $dc;
        $this->fileIndexer = $fileIndexer;
        $this->config = $config;
        $this->notificationFilter = $notificationFilter;
        $this->fops = $fops;
        $this->storageService = $storageService;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            FSCreateFolderEvent::backgroundEventName() => 'onFSCreateFolderEvent',
            FSAddFileEvent::backgroundEventName() => 'onFSAddFileEvent',
            FSUpdateEvent::backgroundEventName() => 'onFSCopyOrMoveEvent',
            FSCopyEvent::backgroundEventName() => 'onFSCopyOrMoveEvent',
            FSDeleteEvent::backgroundEventName() => 'onFSDeleteEvent',
        ];
    }

    /**
     * @param FSEvent $event
     * @return array
     * @deprecated replaced with the TranslatedPath object
     */
    protected function extractPathInfo(FSEvent $event): array
    {
        $fullPath = $event->getResource()->getResourceIdentifier();

        $fileName = $event->getFileName();
        if (strlen($event->getFileName()) > 0) {
            $fullPath = $fullPath . '/' . $event->getFileName();
            $displayName = $event->getResource()->getResourceDisplayName();
        } else {
            $path = Path::createFromPath($fullPath);
            $fileName = $path->getName();

            $dpath = Path::createFromPath(
                Path::createFromPath($event->getResource()->getResourceDisplayPath())->getParent()
            );
            $displayName = $dpath->getName();
        }

        return [$fullPath, $fileName, $displayName];
    }

    public function onFSCreateFolderEvent(FSCreateFolderEvent $event)
    {

        $this->logger->debug("On FSCreateFolderEvent: {$event->getTranslatedPath()->getPath()}");

        try {

            // update the index
            $this->updateFsIndex($event->getFileId(), $event->getTranslatedPath(), $event->getFileName(), 'folder');

        } catch (\Exception $ex) {
            $this->logger->error(
                __CLASS__ . ":" . __FUNCTION__ . " Folder created exception message: " . $ex->getMessage()
            );
        }

        $this->raiseBotMessage($event, $event->getFileName(), $event->getTranslatedPath()->getChannelDisplayPath());
    }

    public function onFSAddFileEvent(FSAddFileEvent $event)
    {

        $this->logger->debug("On FSAddFileEvent: {$event->getTranslatedPath()->getPath()}/{$event->getFileName()}, size: {$event->getFileSize()}");

        try {
            // update the index
            $this->updateFsIndex($event->getFileId(), $event->getTranslatedPath(), $event->getFileName(), 'file', $event->getFileSize());
        } catch (\Exception $ex) {
            $this->logger->error(
                __CLASS__ . ":" . __FUNCTION__ . " File Uploaded Exception message: " . $ex->getMessage()
            );
        }

        $this->raiseBotMessage($event, $event->getFileName(), $event->getTranslatedPath()->getChannelDisplayPath());
    }

    /**
     * @param FSEvent|FSUpdateEvent|FSCopyEvent $event
     */
    public function onFSCopyOrMoveEvent(FSEvent $event)
    {

        // TODO - recursively delete index from-entries when a file is moved

        // TODO - recursively update index to-entries (for move or copy)
        try {
            // update the index
            $this->updateFsIndex($event->getFileId(),
                $event->getTranslatedPath(),
                $event->getFileName(),
                $event->getFileType(),
                $event->getSize());

        } catch (\Exception $ex) {
            var_dump($ex->getMessage());
            $this->logger->error(
                __CLASS__ . ":" . __FUNCTION__ . " File move/copy Exception message: " . $ex->getMessage()
            );
        }
    }

    public function onFSDeleteEvent(FSDeleteEvent $event)
    {
        // check if the deleted file is an attachment on a message
        $translatedPath = $event->getTranslatedPath();
        if (($relativePath = $translatedPath->getRelativePath()) !== null) {

            // first list the messages, to be able to trigger the events later
            $messages = $this->dc->findMessagesForAttachment(MessageAttachment::ATTACHMENT_TYPE_FILE, $relativePath);
            if (!empty($messages)) {

                // remove the attachments from database
                $this->dc->removeMessageAttachment(MessageAttachment::ATTACHMENT_TYPE_FILE, $relativePath);

            }
        }

        // TODO - recursively delete entries from the index
    }

    /**
     * @param int $fileId
     * @param TranslatedPath $parentTranslatedPath
     * @param string $name
     * @param string $fileType
     * @param int|null $fileSize
     * @throws \CodeLathe\Service\Storage\Exceptions\NotAFileException
     * @throws \CodeLathe\Service\Storage\Exceptions\NotFoundException
     */
    protected function updateFsIndex(int $fileId, TranslatedPath $parentTranslatedPath, string $name, string $fileType, ?int $fileSize = null)
    {

        $fileSizeLimit = FileSize::toBytes($this->config->get('/search/content_size_limit'));
        if ($fileType === 'file' && preg_match('/\.(.+)$/', $name, $matches)) {
            $extension = $matches[1];
        } else {
            $extension = '';
        }

        $channel = $parentTranslatedPath->getChannel();
        $channelId = $channel instanceof Channel ? $channel->getId() : null;

        $physicalPath = "{$parentTranslatedPath->getPhysicalPath()}/$name";
        $relativePath = "{$parentTranslatedPath->getRelativePath()}/$name";

        $contentParsersConfig = $this->config->get('/search/content_parsers');
        $indexed = false;
        if (!empty($extension) && in_array($extension, array_keys($contentParsersConfig)) && $fileSize !== null && $fileSize < $fileSizeLimit) {

            $download = $this->storageService->download($physicalPath, null, 'local');

            $localFile = $download->getPayload()['tmpfile'] ?? null;

            $parser = ContainerFacade::get($contentParsersConfig[$extension]);
            try {
                $this->fileIndexer->indexDocumentContent($fileId, $name, $physicalPath, $relativePath, $channelId, $extension, $parser, $localFile);
                $indexed = true;
            } catch (\Throwable $e) {
                $indexed = false; // if the content indexing fails, ensure the name indexing
            }
        }

        if (!$indexed) {
            $this->fileIndexer->indexDocumentName($fileId, $name, $physicalPath, $relativePath, $channelId, $extension, $fileType);
        }
    }

    protected function removeFsIndex(string $indexEntryId)
    {
        try {
            $this->fileIndexer->remove($indexEntryId);
        } catch (\Throwable $e) {
            $this->logger->warning("Failed to delete the index for $id. Error: " . $e->getMessage());
        }

    }

    private function raiseBotMessage(FSEvent $event, string $fileName, string $displayName): void
    {

        // only go forward if a bot message is required
        if (!$event->genAirBot()) {
            return;
        }

        // ignore if there is no channel associated with the path
        $channelID = $event->getAssociatedChannelId();
        if ($channelID == -1) {
            return;
        }

        // find the user info
        $user = $this->dc->getUserById($event->getUserID());
        $username = 'Unknown';
        if (isset($user)) {
            $username = $user->getDisplayName();
        }

        // find the channel info
        $channelFilePath = $event->getAssociatedFSPath();

        // if the path display name is empty, replace it with the channel name
        if (empty($displayName) && ($channel = $event->getTranslatedPath()->getChannel()) !== null) {
            $displayName = $channel->getName();
        }

        // raise the bot message
        $this->logger->debug("Raising AirBOT Notification: " . $channelID);

        $actionStr = "added";


        if ($event->getTranslatedPath()->getType() === 'wiki') {
            $fileBlurb = "[{$fileName}](wiki://{$channelFilePath}/{$fileName})";
        } else {
            $fileBlurb = "[{$fileName}](path://{$channelFilePath})";
        }
        if ($event instanceof FSAddFileEvent && !$event->isNew()) {
            $actionStr = "updated";
        }
        if ($event instanceof FSDeleteEvent) {
            $actionStr = "deleted";
            $fileBlurb = $fileName;
        }

        if ($event->getTranslatedPath()->getType() == 'wiki') {
            $botMessage = "[{$username}](user://{$event->getUserID()}) has {$actionStr} the wiki file {$fileBlurb}";
            $botI18nKey = "bot.file_{$actionStr}_wiki";
            $botI18nParams = [
                'user_name' => $username,
                'user_id' => $event->getUserID(),
                'file_blurb' => $fileBlurb
            ];
        } else {
            $botMessage = "[{$username}](user://{$event->getUserID()}) {$actionStr} {$fileBlurb} in [{$displayName}](path://{$channelFilePath})";
            $botI18nKey = "bot.file_{$actionStr}_channel";
            $botI18nParams = [
                'user_name' => $username,
                'user_id' => $event->getUserID(),
                'file_blurb' => $fileBlurb,
                'channel_name' => $displayName,
                'file_path' => $channelFilePath,
            ];
        }

        $botEvent = MessageBot::create(1, $botMessage, $botI18nKey, null, $botI18nParams);
        $this->chat->raiseBotNotification($botEvent, $channelID);
    }
}