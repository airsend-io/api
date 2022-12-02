<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Objects\ChannelFileResource;
use CodeLathe\Core\Objects\ChannelWikiResource;
use CodeLathe\Core\Objects\FileResource;
use CodeLathe\Core\Objects\Resource;
use CodeLathe\Core\Objects\User;

/**
 * Class ResourceManager
 * @package CodeLathe\Core\Managers
 * @deprecated - Replaced by the new storage system
 */
class ResourceManager {

    /**
     * Returns a Resource Object given a Resource Path
     *
     * @param string $resourceIdentifier
     * @throws UnknownResourceException if the resource string is unknown
     * @return Resource
     * @deprecated
     */
    static function getResource(string $resourceIdentifier)
    {
        if (preg_match('/^\/f\//', $resourceIdentifier))
            return new FileResource($resourceIdentifier);
        else if (preg_match('/^\/cf/', $resourceIdentifier))
            return new ChannelFileResource($resourceIdentifier);
        else if (preg_match('/^\/wf\//', $resourceIdentifier))
            return new ChannelWikiResource($resourceIdentifier);

        throw new UnknownResourceException();
    }

    /**
     * @param Resource $resource
     * @param User $user
     * @param DataController $dc
     * @return Resource
     * @throws BadResourceTranslationException
     * @deprecated
     */
    static function translatePath(Resource $resource, User $user, DataController $dc)
    {
        if (!$resource->translatePath($user, $dc)) {
            throw new BadResourceTranslationException($resource->getResourceIdentifier());
        }
        return $resource;
    }


}