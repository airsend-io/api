<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Serializers\JSONSerializer;

abstract class JsonOutput
{

    /**
     * @param int $httpCode
     * @return JSONSerializer
     * @throws InvalidSuccessfulHttpCodeException
     */
    public static function success(int $httpCode = 200)
    {
        if ($httpCode < 200 || $httpCode > 299) {
            throw new InvalidSuccessfulHttpCodeException($httpCode);
        }
        return (new JSONSerializer(true))->withHTTPCode($httpCode);
    }

    /**
     * @param string $message
     * @param int $httpCode
     * @return JSONSerializer
     * @throws InvalidFailedHttpCodeException
     */
    public static function error(string $message, int $httpCode = 400)
    {
        if ($httpCode < 400) {
            throw new InvalidFailedHttpCodeException($httpCode);
        }
        return (new JSONSerializer(false))->withHTTPCode($httpCode)->withError($message);
    }
}