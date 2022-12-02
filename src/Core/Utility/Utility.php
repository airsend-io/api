<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\UserDataStore;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Managers\User\UserOperations;
use Exception;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

class Utility
{
   public static function isValidEmail(string $value)
   {
       return (filter_var(trim($value), FILTER_VALIDATE_EMAIL));
   }

   public static function isValidPhoneFormat(string $value)
   {
       return preg_match('/^\+[0-9\-\(\)\/\+\s]*$/', trim($value), $matches);
   }

    public static function bytesToGB(float $bsize)
    {
        $inGB=0;

        if($bsize>0)
            $inGB = round($bsize/1073741824, $precision = 2);

        return $inGB;
    }

    public static function GBTobytes(float $gb)
    {
        return round ($gb * 1073741824);
    }

    /**
     * This method allows the merging of multiple iterables / generators, to be drained in order
     * (works like an array_merge for iterables)
     *
     * @param iterable ...$its
     * @return iterable
     */
    public static function mergeIterables(iterable ...$its): iterable
    {

        // while we have iterables to yield, go through each one of them
        while(count($its)) {

            // remove the first iterable from the list
            $iterable = array_shift($its);

            // yield it until there is no more elements to yield
            while($iterable->valid()) {

                // yield current value
                yield($iterable->current());

                // increment it
                $iterable->next();
            }

        }
    }

    /**
     * List of supported document extension which that we support generating previews
     * for.
     * @param string $ext
     * @return bool
     */
    public static function isFilePreviewSupported(string $ext): bool
    {
        $supported = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'pdf', 'numbers', 'pages', 'key', 'mp4', 'mov', 'avi'];
        return in_array(strtolower($ext), $supported);
    }

    public static function isValidParams($params, string $value) : bool
    {
        return (isset($params[$value]) && trim(strval($params[$value])) != "");

    }

    /**
     * Safe ipv4 and ipv6 compare function
     *
     * @param string $ip1
     * @param string $ip2
     * @return bool
     */
    public static function sameIp(string $ip1, string $ip2): bool
    {

        // first check if ip1 is ipv4
        if (($ipLong1 = ip2long($ip1)) !== false) {
            $ipLong2 = ip2long($ip2);
            return $ipLong1 === $ipLong2;
        }

        // it's probably ipv6, so try to get a inet hash from them
        if (($ipHash1 = inet_pton($ip1)) === false) {
            return false; // return false if it's not a valid ipv6 ip
        }

        if (($ipHash2 = inet_pton($ip2)) === false) {
            return false; // return false if it's not a valid ipv6 ip
        }

        return $ipHash1 === $ipHash2;
    }

    /**
     * Generates a universal unique token, to be used for authentication.
     * @throws Exception
     */
    public static function uniqueToken(): string
    {
        return Base64::urlEncode(uniqid(StringUtility::generateRandomString(8), true));
    }

    /**
     * TODO - Refactor this inside a middleware
     * Every incoming internal message must contain token
     * @param array $params
     * @param LoggerInterface $logger
     * @return bool
     */
    public static function internalAuthenticate(array $params, LoggerInterface $logger): bool
    {
        if (empty($params['auth_token'])) {
            $logger->error("Failed authenticating message. Token not found. Rejecting");
            return false;
        }

        // TODO - Make this key dynamic (rotate it periodically)
        $key = ContainerFacade::get(ConfigRegistry::class)['/app/internal/auth_token'];
        if ($key !== $params['auth_token']) {
            $logger->error($params['auth_token']);
            $logger->error("Failed authenticating message. Token incorrect. Rejecting");
            return false;
        }

        return true;
    }


    public static function codelathify(string $in): string
    {
        if (StringUtility::endsWith($in, 'filecloud.com')) {
            // Check if there is a codelathe.com account present and swap it
            /** @var UserDataStore $userDs */
            $userDs = ContainerFacade::get(UserDataStore::class);
            try {
                $out = StringUtility::replaceEndString('filecloud.com', 'codelathe.com', $in);
                if (!empty($user = $userDs->getUserByEmail($out))) {
                    $in = $out;
                }
            }
            catch (DatabaseException $e) {
            }
        }
        return $in;
    }

}