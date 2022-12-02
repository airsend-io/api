<?php declare(strict_types=1);

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use CodeLathe\Core\Exception\MemoizingException;

/**
 * Class Memoizer
 *
 * This class is used to create a memoized version of a function.
 *
 * ATTENTION:
 * - It will cache the results of any function and always return the same result with the same parameters.
 * - This class cannot be used with functions that generates side effects
 * - Take care when caching database results, the function will always return the same set during the same execution
 * (request), even if the data change on database
 * - Don't use this on long running processes unless the function is a "pure function" (no side effects, no external
 * dependency)
 *
 * @package CodeLathe\Core\Utility
 */
class Memoizer
{

    protected static $cache = [];

    /**
     * @param callable $originalFunction
     * @return callable
     * @throws MemoizingException
     */
    public static function memoized(callable $originalFunction): callable
    {

        $keyPrefix = null;

        if (is_array($originalFunction) && is_object($originalFunction[0])) {

            // function is a instance method..
            $keyPrefix = get_class($originalFunction[0]) . "::{$originalFunction[1]}::";

        } elseif (is_array($originalFunction)) {

            // function is a static method
            $keyPrefix = "{$originalFunction[0]}::{$originalFunction[1]}::";

        } else {

            // only instance or static methods are supported
            throw new MemoizingException('Failed memoizing the function. Only instance or static methods are supported');

        }

        return function(...$params) use ($originalFunction, $keyPrefix) {

            $key = md5($keyPrefix . json_encode($params));

            if (!isset(static::$cache[$key])) {

                static::$cache[$key] = $originalFunction(...$params);
            }

            return static::$cache[$key];

        };

    }

}