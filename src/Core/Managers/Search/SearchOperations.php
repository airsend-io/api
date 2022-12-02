<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Search;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\SearchDataController;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSNotFoundException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\GlobalAuthContext;
use CodeLathe\Core\Objects\FileSystemObject;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\SearchResult;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Service\Storage\Contracts\StorageServiceInterface;
use CodeLathe\Service\Storage\Exceptions\NotFoundException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class SearchOperations
{

    /**
     * @var SearchDataController
     */
    protected $dataController;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    /**
     * @var FileOperations
     */
    protected $fileOperations;

    /**
     * @var GlobalAuthContext
     */
    protected $authContext;

    /**
     * @var array
     */
    protected $permissionsMap;

    /**
     * @var DataController
     */
    protected $dc;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * SearchService constructor.
     * @param SearchDataController $dataController
     * @param NormalizedObjectFactory $objectFactory
     * @param FileOperations $fileOperations
     * @param GlobalAuthContext $authContext
     * @param DataController $dc
     * @param LoggerInterface $logger
     */
    public function __construct(SearchDataController $dataController,
                                NormalizedObjectFactory $objectFactory,
                                FileOperations $fileOperations,
                                GlobalAuthContext $authContext,
                                DataController $dc,
                                LoggerInterface $logger)
    {
        $this->dataController = $dataController;
        $this->objectFactory = $objectFactory;
        $this->fileOperations = $fileOperations;
        $this->authContext = $authContext;
        $this->dc = $dc;
        $this->logger = $logger;
    }

    /**
     * @param User $loggedUser
     * @param string $searchQuery
     * @param int|null $limit
     * @param int|null $channelId
     * @param string|null $scope
     * @return SearchResult[]
     * @throws ChannelMalformedException
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws NoNodesAvailableException
     * @throws NotImplementedException
     * @throws \CodeLathe\Core\Exception\FSAuthorizationException
     * @throws \CodeLathe\Core\Exception\FSNotFoundException
     * @throws \CodeLathe\Core\Exception\FSOpException
     */
    public function search(User $loggedUser, string $searchQuery, int $limit, ?int $channelId, ?string $scope): array
    {

        // search messages
        $messageResults = [];
        if ($scope === null || $scope === 'message') {

            try {
                $messages = $this->dataController->searchMessages($loggedUser->getId(), $searchQuery, $limit, $channelId);
            } catch (Missing404Exception $e) {
                $this->logger->error('Messages index not found!');
                $messages = [];
            }
            foreach ($messages as $messageId => $result) {

                // checking if the user have read access to this result (based on the channel_id)
                if (!$this->checkPermissionForChannel((int)$result['channel_id'] ?? 0, $loggedUser->getId())) {
                    continue;
                }

                // if the access is granted, include the message on the results
                $message = $this->dc->getMessageById($messageId);
                if ($message) {

                    // ignore bot messages
                    if ($message->getMessageType() === Message::MESSAGE_TYPE_BOT) {
                        continue;
                    }

                    $messageOutput = $this->objectFactory->normalizedObject($message);
                    $result = SearchResult::withDBData('message', $messageOutput->getArray(), $result['highlights']);
                    $messageResults[] = $result;
                }
            }
        }

        // search actions
        $actionResults = [];
        if ($scope === null || $scope === 'action') {
            try {
                $actions = $this->dataController->searchActions($loggedUser->getId(), $searchQuery, $limit, $channelId);
            } catch (Missing404Exception $e) {
                $this->logger->error('Actions index not found!');
                $actions = [];
            }
            foreach ($actions as $actionId => $result) {

                // TODO - replace with the new authorization system
                // checking if the user have read access to this result (based on the channel_id)
                if (!$this->checkPermissionForChannel((int)$result['channel_id'] ?? 0, $loggedUser->getId())) {
                    continue;
                }

                $action = $this->dc->getActionById($actionId);
                if ($action) {
                    $actionOutput = $this->objectFactory->normalizedObject($action);
                    $actionResults[] = SearchResult::withDBData('action', $actionOutput->getArray(), $result['highlights']);
                }
            }
        }

        // search files
        $fileResults = [];
        if ($scope === null || $scope === 'file') {

            // first get the paths that match the search on the index
            try {
                $items = $this->dataController->searchFiles($loggedUser->getId(), $searchQuery, $channelId);
            } catch (Missing404Exception $e) {
                $this->logger->error('Files index not found!');
                $items = [];
            }

            // try to get file info for each path
            foreach ($items as $item) {

                // this already handle authorization
                try {
                    $file = $this->fileOperations->info($item['path'], $loggedUser);
                } catch (\Throwable $e) {
                    // if we have any problem looking for the file, just ignore it
                    continue;
                }

                $data = $file->jsonSerialize();
                $data['channel_id'] = $item['channel_id'];
                $data['is_wiki_file'] = preg_match('/^\/wf\/.+\.md$/', $item['path']);

                // if we got the info, include the result on search
                $fileResults[] = SearchResult::withDBData('file', $data, $item['highlights']);
            }
        }

        // search users
        $userResults = [];
        if ($scope === null || $scope === 'user') {

            try {
                $users = $this->dataController->searchUsers($loggedUser->getId(), $searchQuery, $limit, $channelId);
            } catch (Missing404Exception $e) {
                $this->logger->error('Users index not found!');
                $users = [];
            }

            foreach ($users as $foundUserId => $highlights) {
                $user = $this->dc->getUserById($foundUserId);
                if ($user) {
                    $userOutput = $this->objectFactory->normalizedObject($user);
                    $userResults[] = SearchResult::withDBData('user', $userOutput->getArray(), $highlights);
                }
            }
        }

        // search channel
        $channelResults = [];
        if ($scope === null || $scope === 'channel') {

            try {
                $channels = $this->dataController->searchChannels($loggedUser->getId(), $searchQuery, $limit);
            } catch (Missing404Exception $e) {
                $this->logger->error('Channels index not found!');
                $channels = [];
            }

            foreach ($channels as $channelId => $highlights) {

                // ensure that logged user is member of this channel
                if (!$this->dc->isChannelMember($channelId, $loggedUser->getId())) {
                    continue;
                }

                $channel = $this->dc->getChannelById($channelId);
                $channelNorm = $this->objectFactory->normalizedObject($channel);
                $channelResults[] = SearchResult::withDBData('channel', $channelNorm->getArray(), $highlights);
            }
        }

        return array_merge($messageResults, $actionResults, $fileResults, $userResults, $channelResults);

    }

    protected function checkPermissionForChannel(int $channelId, int $userId): bool
    {
        if (!isset($this->permissionsMap[$channelId][$userId])) {
            $this->permissionsMap[$channelId][$userId] = $this->authContext->getEffectivePermission($userId, GlobalAuthContext::CONTEXT_TYPE_CHANNEL, $channelId);
        }
        return $this->permissionsMap[$channelId][$userId] >= GlobalAuthContext::AUTH_READ;
    }

}