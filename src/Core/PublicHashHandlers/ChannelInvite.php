<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\PublicHashHandlers;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Http\Message\ServerRequestInterface;

class ChannelInvite extends AbstractPublicHashHandler
{

    public function allow(ServerRequestInterface $request, string $resourceId): bool
    {

        // The strategy here is to find the channel id related to object of the request
        // and compare it with the resource id stored on the public hash.

        $channelId = null;

        // split the route
        $route = $this->splitRoute($request);

        $params = $request->getQueryParams() ?? [];

        $path = null;

        // file operations (anything that starts with `file.`)
        if (preg_match('/^file\./', $route)) {

            // get the path of the file
            $path = $params['fspath'] ?? null;
        }

        // wiki operations
        if (preg_match('/^wiki\.[^\/]+(\/.+)/', $route, $matches)) {

            $path = $matches[1];

        }

        // if there is a path...
        if ($path !== null) {
            // translate it
            /** @var FileOperations $fops */
            $fops = ContainerFacade::get(FileOperations::class);
            $translatedPath = $fops->translatePath($path);

            // translation process gives us the channel id (when it makes sense for the path)
            if (($channel = $translatedPath->getChannel()) === null) {
                return false;
            }
            $channelId = $channel->getId();

        }

        // channel and actions operations (anything that starts with channel or action. )
        if (preg_match('/^(?:channel|action)\./', $route)) {

            // extract the channelId from the request
            $channelId = isset($params['channel_id']) ? ((int) $params['channel_id']) : null;
        }

        // user profile route (only authorize images from users that are members of the channel)
        if ($route == 'user.image.get') {
            $userId = (int)($params['user_id'] ?? 0);
            $channelUsers = $this->dataController->getUserChannelsByUserId($userId);
            foreach ($channelUsers as $channelUser) {
                if ($channelUser['channel_id'] == $resourceId ) {
                    $channelId = (int)$channelUser['channel_id'];
                    break;
                }
            }
        }

        // channelId must be set at this point, and the channel must exist
        if ($channelId !== null && ($channel = $this->dataController->getChannelById($channelId)) !== null) {

            // for the channel.info and channel.image.get route, we always allow read, even if the allow external read is disabled
            if (in_array($route, ['channel.info', 'channel.image.get'])) {
                return $channelId === (int)$resourceId;
            }

            // allow when the channel allows external read, and the public hash resource id is equal to the channel id.
            return $channel->getAllowExternalRead() && $channelId === (int)$resourceId;
        }

        // otherwise, halt
        return false;

    }
}