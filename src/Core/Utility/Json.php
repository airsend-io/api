<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use InvalidArgumentException;

abstract class Json
{

    /**
     * @param $value
     * @param int $options
     * @param int $depth
     * @return string
     * @throws InvalidArgumentException
     */
    public static function encode($value, int $options = 0, int $depth = 512): string
    {
        $result = json_encode(self::utf8ize($value), $options, $depth);
        if ($result === false || JSON_ERROR_NONE !== json_last_error()) {
            LoggerFacade::error('Error encoding object to JSON: ' . print_r($value, true));
            throw new InvalidArgumentException('json_encode error: ' . json_last_error_msg());
        }
        return $result;
    }

    /**
     * Convert all non utf to utf8 to prevent encoding errors
     * @param $mixed
     * @return array|false|string|string[]|null
     */
    public static function utf8ize( $mixed ) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = self::utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
        }
        return $mixed;
    }


    /**
     * @param $value
     * @param bool $assoc
     * @return mixed
     * @throws InvalidArgumentException
     */
    public static function decode($value, bool $assoc = false)
    {
        $result = json_decode($value, $assoc);
        if ($result === null || JSON_ERROR_NONE !== json_last_error()) {
            LoggerFacade::error('Error decoding object to JSON: ' . print_r($value, true));
            throw new InvalidArgumentException('json_decode error: ' . json_last_error_msg());
        }
        return $result;

    }

}