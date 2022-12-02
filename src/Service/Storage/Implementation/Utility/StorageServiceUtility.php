<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Service\Storage\Implementation\Utility;

use CodeLathe\Service\Storage\Backstore\AbstractBackstore;
use CodeLathe\Service\Storage\Config\StorageServiceConfig;
use CodeLathe\Service\Storage\StorageService;


/**
 * Class StorageServiceUtility
 * @package CodeLathe\Service\Storage\Util
 */
class StorageServiceUtility
{

    /**
     * @return string
     */
    public static function generateUniqueID() : string
    {
        return str_replace(".", "", \uniqid('', true));
    }

    /**
     * @param $filepath
     * @return array
     */
    public static function getPathInfo($filepath): array
    {
        if (preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $filepath, $m)) {
            $ret = [];
            if (isset($m[1]))
                $ret['dirname'] = $m[1];
            if (isset($m[2]))
                $ret['basename'] = $m[2];
            if (isset($m[5]))
                $ret['extension'] = $m[5];
            if (isset($m[3]))
                $ret['filename'] = $m[3];
            return $ret;
        }
        return [];

    }

    public static function getPathExtension($filePath): ?string
    {
        $extension = static::getPathInfo($filePath)['extension'] ?? null;
        return $extension === null ? null : strtolower($extension);
    }

}