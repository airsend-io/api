<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;


class Convert
{
    /**
     * Convert a type to nullable integer
     *
     * @param $value
     * @return int|null
     */
    public static function toIntNull($value) :?int
    {
        if ($value == null)
            return null;
        else {
            return intval($value);
        }
    }

    /**
     * Convert a type to nullable string
     *
     * @param $value
     * @return string|null
     */
    public static function toStrNull($value) :?string
    {
        if ($value == null)
            return null;
        else {
            return strval($value);
        }
    }

    public static function toStr($value, $default = '')
    {
        if ($value === null) {
            return $default;
        }
        return strval($value);
    }

    /**
     * Convert a type to nullable boolean
     *
     * @param $value
     * @return bool|null
     */
    public static function toBoolNull($value) : ?bool
    {
        if ($value == null)
            return null;
        else
            return boolval($value);
    }

    /**
     * Convert a type to nullable boolean
     *
     * @param $value
     * @param $default
     * @return bool|null
     */
    public static function toBool($value, bool $default = false) : ?bool
    {
        if ($value == null)
            return $default;
        else
            return boolval($value);
    }

    public static function toArrayNull($value) : ?array
    {
        if ($value == null)
            return null;
        else if (is_array($value))
            return $value;
        else if (is_string($value) && static::isJson($value))
            return json_decode($value, true);
        else
            return null;

    }

    private static function isJson($value) : bool
    {
        json_decode($value);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Converts a value in bytes to another size unit.
     *
     * If the $desiredUnit param is provided, the given $size is converted to the given $unit. If not, it's converted to the
     * highest unit where the response is bigger than 1.
     *
     * This method will never work for exabytes, because of the 64 bits limitation of signed int (a good problem to have
     * if we get to this size)
     *
     * @param int $size size in bytes
     * @param string|null $desiredUnit
     * @param int|null $decimals
     * @return string
     */
    public static function toSizeUnit(int $size, ?string $desiredUnit = null, ?int $decimals = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        foreach ($units as $unit) {
            if ($unit == $desiredUnit || ($desiredUnit === null && $size / 1024 < 1)) {
                return number_format($size, $decimals, '.', '') . ' ' . $unit;
            }
            $size = $size / 1024;
        }
        return number_format($size, $decimals, '.', '') . ' ' . $unit;
    }

    public static function hexrgb2int(string $rgb): ?int
    {
        if (!preg_match('/^#?([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/', $rgb, $matches)) {
            return null;
        }
        $rgb = $matches[1];
        if (strlen($rgb) === 3) {
            $rgb = array_reduce(str_split($rgb), function ($carry, $item) {
                return $carry . $item . $item;
            }, '');
        }
        return hexdec($rgb);
    }
}