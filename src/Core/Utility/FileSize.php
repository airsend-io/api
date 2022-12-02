<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;


use Illuminate\Translation\Translator;
use Psr\Container\ContainerInterface;

/**
 * Class FileSize
 * @package CodeLathe\Core\Utility
 */
abstract class FileSize
{

    protected static $units = [
        'K' => 1024,
        'KB' => 1024,
        'M' => 1024 * 1024,
        'MB' => 1024 * 1024,
        'G' => 1024 * 1024 * 1024,
        'GB' => 1024 * 1024 * 1024,
        'T' => 1024 * 1024 * 1024 * 1024,
        'TB' => 1024 * 1024 * 1024 * 1024,
    ];

    public static function toBytes(string $size): int
    {
        if (preg_match('/^([0-9]+)([KMGTkmgt][Bb]?)$/', trim($size), $matches)) {
            return ((int)$matches[1]) * static::$units[strtoupper($matches[2])];
        }

        throw new \Exception('Invalid file size provided');
    }
}