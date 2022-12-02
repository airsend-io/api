<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

/**
 * Class Directories
 *
 * Helper class that holds convenience methods to get the main app directories (for file location)
 *
 * @package CodeLathe\Core\Utility
 */
abstract class Directories
{

    public static function approot(?string $file = '')
    {
        return realpath(dirname(__FILE__, 4)) . (!empty($file) ? "/$file" : '');
    }

    public static function resources(?string $file = '')
    {
        return static::approot("resources/$file");
    }

    public static function images(?string $file = '')
    {
        return static::resources("images/$file");
    }

    public static function scratch(?string $file = '')
    {
        return static::approot("scratch/$file");
    }

    public static function src(?string $file = '')
    {
        return static::approot("src/$file");
    }

    public static function public(?string $file = '')
    {
        return static::approot("public/$file");
    }

    public static function config(?string $file = '')
    {
        return static::approot("config/$file");
    }

    public static function tmp(?string $file = '')
    {
        return static::scratch("tmp/$file");
    }
}