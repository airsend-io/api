<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use CodeLathe\Core\Exception\SecurityException;

class SafeFile
{
    /**
     * Check if the path is in allowed path types
     * @param string $path
     * @return bool
     */
    public static function inAllowedRootPaths(string $path)
    {
        $allowed = [
            CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'dev',
            CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'wiki',
            CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'wiki'.DIRECTORY_SEPARATOR.'math',
            CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'wiki'.DIRECTORY_SEPARATOR.'math'.DIRECTORY_SEPARATOR.'fonts',
            CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'images',
            CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch'
        ];

        foreach ($allowed as $allowedPath) {
            if (strpos($path, $allowedPath) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function isAllowedPath(string $path)
    {
        $realPath = realpath($path);
        if (!$realPath) {
            throw new SecurityException();
        }

        if (!SafeFile::inAllowedRootPaths($realPath)) {
            throw new SecurityException();
        }

        return true;
    }

    public static function file_get_contents($filename)
    {
        if (realpath($filename) === false) {
            throw new SecurityException();
        }

        if (!SafeFile::inAllowedRootPaths(realpath($filename))) {
            throw new SecurityException();
        }

        return file_get_contents(realpath($filename));
    }

    public static function file_exists(string $path)
    {
        if (realpath($path) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($path))) {
            throw new SecurityException();
        }

        return file_exists(realpath($path));
    }

    public static function scandir(string $path)
    {
        if (realpath($path) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($path))) {
            throw new SecurityException();
        }

        return scandir(realpath($path));
    }

    public static function is_dir(string $path)
    {
        if (realpath($path) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($path))) {
            throw new SecurityException();
        }

        return is_dir(realpath($path));
    }

    public static function rename(string $oldname, string $newname)
    {
        if (realpath($oldname) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($oldname))) {
            throw new SecurityException();
        }

        if (!SafeFile::inAllowedRootPaths($newname)) {
            throw new SecurityException();
        }

        return rename(realpath($oldname), $newname);
    }

    public static function unlink(string $fpath)
    {
        if (realpath($fpath) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($fpath))) {
            throw new SecurityException();
        }

        return unlink(realpath($fpath));
    }

    public static function opendir(string $fpath)
    {
        if (realpath($fpath) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($fpath))) {
            throw new SecurityException();
        }

        return opendir(realpath($fpath));
    }

    public static function filesize(string $fpath)
    {
        if (realpath($fpath) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($fpath))) {
            throw new SecurityException();
        }

        return filesize(realpath($fpath));
    }

    public static function filectime(string $fpath)
    {
        if (realpath($fpath) === false) {
            return false;
        }

        if (!SafeFile::inAllowedRootPaths(realpath($fpath))) {
            throw new SecurityException();
        }

        return filectime(realpath($fpath));
    }

    public static function closedir($handle)
    {
        return closedir($handle);
    }
}