<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Auth;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Utility\Base64;
use CodeLathe\Core\Utility\Utility;
use CodeLathe\Service\Auth\Exceptions\InvalidTokenException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Class JwtService
 *
 * JWT interface implementation that uses asymmetric (SHA256) keys, with key rotation and keeping the public key
 * on the token header, what means that the token can be auto-verified on client applications, but only the issuer
 * can ensure that it was signed with it's private key.
 * A PSR6 cache implementation is required to store the keys and the caller can provide the keys that will be used on
 * the storage (just to avoid conflicts with other keys used by the application, since the keys have default values).
 * The caller can also define the TTL values for key rotation. The private keys, used to sign the tokens, have a TTL
 * that defaults to 1 hour (this value can be override on the constructor). The public key, used to verify the signature
 * have a TTL that defaults to 2 hours. Public key TTL MUST be bigger than private key TTL. It means that once a private
 * key is generated, all tokens issued on the next hour will be signed using this key. When this key expires, tokens
 * signed with it, can still be successfully verified for more one our. It's up to the caller to sync those TTL with the
 * token expiration times.
 *
 * @package CodeLathe\Service\Auth
 */
class JwtService implements JwtServiceInterface
{

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var int
     */
    protected $privateKeyTTL;

    /**
     * @var int
     */
    protected $tokenTTL;

    /**
     * @var string
     */
    protected $privateKeyCacheKey;

    /**
     * @var string
     */
    protected $publicKeysCacheKey;

    /**
     * @var string
     */
    protected $tokenIssuer;

    /**
     * JwtService constructor.
     * @param CacheItemPoolInterface $cache
     * @param ConfigRegistry $registry
     * @param string|null $privateKeyCacheKey
     * @param string|null $publicKeysCacheKey
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        ConfigRegistry $registry,
        ?string $privateKeyCacheKey = 'jwt.private_key',
        ?string $publicKeysCacheKey = 'jwt.public_key'
    )
    {
        $this->cache = $cache;

        // get the private key ttl from the config
        $this->privateKeyTTL = $registry->get('/auth/jwt/private_key_ttl');

        // get the token ttl
        $this->tokenTTL = $registry->get('/auth/jwt/ttl');

        // get the token issuer from the config
        $this->tokenIssuer = $registry->get('/auth/jwt/issuer');

        // cache keys
        $this->privateKeyCacheKey = $privateKeyCacheKey;
        $this->publicKeysCacheKey = $publicKeysCacheKey;
    }

    /**
     * @return array
     */
    protected function generateKeyPair(): array
    {
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => 1024,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        // generate the key pair
        $res = openssl_pkey_new($config);

        // Extract the private key
        openssl_pkey_export($res, $privateKey);

        // Extract the public key
        $publicKeyInfo = openssl_pkey_get_details($res);

        return [$privateKey, $publicKeyInfo["key"]];
    }

    /**
     * @param string $privateKey
     * @return string
     */
    protected function extractPublicKey(string $privateKey): string
    {
        $res = openssl_get_privatekey($privateKey);
        $publicKeyInfo = openssl_pkey_get_details($res);
        return $publicKeyInfo['key'];
    }

    /**
     * @param int|string $subject
     * @param string $remoteAddr
     * @param string $remoteAgent
     * @param bool $admin
     * @param bool|null $rememberMe
     * @param string|null $scope
     * @return string
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    public function issueToken($subject,
                               string $remoteAddr,
                               string $remoteAgent,
                               bool $admin,
                               ?bool $rememberMe = false,
                               ?string $scope = 'global'): string
    {

        // try to get cached private key
        $privateKeyCacheItem = $this->cache->getItem($this->privateKeyCacheKey);
        if (!$privateKeyCacheItem->isHit()) {

            // there isn't a valid private key on the cache, so generate a new key pair
            [$privateKey, $publicKey] = $this->generateKeyPair();

            // store the generated public key on the cache (using a md5 hash) for the public key TTL
            // we add the md5 hash because is possible to have more than one active public key at the same time
            $key = $this->publicKeysCacheKey . '.' . md5($publicKey);
            $publicKeyCacheItem = $this->cache->getItem($key);
            $publicKeyCacheItem->set($publicKey);
            $this->cache->save($publicKeyCacheItem);

            // store the generated private key on the cache for the private key TTL
            $privateKeyCacheItem->expiresAfter($this->privateKeyTTL);
            $privateKeyCacheItem->set($privateKey);
            $this->cache->save($privateKeyCacheItem);

        } else {

            // we have a private key on the cache! Let's extract the public key and use it
            $privateKey = $privateKeyCacheItem->get();
            $publicKey = $this->extractPublicKey($privateKey);

        }

        $jwtHeader = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'x5c' => Base64::urlEncode($publicKey),
        ];

        // generate the token unique id (jti claim).
        $jti = Utility::uniqueToken();

        $now = time();
        $jwtPayload = [
            'sub' => $subject,
            'jti' => $jti,
            'iss' => $this->tokenIssuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->tokenTTL,
            'cip' => $remoteAddr, //client IP
            'cag' => $remoteAgent, // client remote agent
            'scp' => $scope, // token scope
            'admin' => $admin
        ];

        // only include the refresh token if the "remember me" mode is set
        if ($rememberMe) {
            $rtk = Utility::uniqueToken();
            $jwtPayload['rtk'] = $rtk;

            // store the $rtk token on the cache for later checking
            $cacheItem = $this->cache->getItem("refresh.token.$rtk");
            if (!$cacheItem->isHit()) {
                // the value of the entry is the user id, for validation
                $cacheItem->set($subject);
                $this->cache->save($cacheItem);
            }
        }

        // generate the token
        $jwtToken = Base64::urlEncode(json_encode($jwtHeader)) . '.' . Base64::urlEncode(json_encode($jwtPayload));

        // sign it
        openssl_sign($jwtToken, $jwtSignature, $privateKey, OPENSSL_ALGO_SHA256);

        // store the jti for the user
        $cacheItem = $this->cache->getItem("jwt.jti.$subject.$jti");
        $cacheItem->set(1);
        $this->cache->save($cacheItem);

        // return
        return $jwtToken . '.' . Base64::urlEncode($jwtSignature);
    }

    /**
     * @param string $token
     * @param bool|null $checkTTL
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidTokenException
     */
    public function decodeToken(string $token, ?bool $checkTTL = true): array
    {

        // check the token basic structure
        if (!preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $token)) {
            throw new InvalidTokenException("Malformed JWT token: $token");
        }

        // split the token
        [$header, $payload, $signature] = explode('.', $token);

        // decode the header and get the public key
        $headerDecoded = json_decode(Base64::urlDecode($header), true);
        if ($headerDecoded['alg'] !== 'RS256' || $headerDecoded['typ'] !== 'JWT' || !isset($headerDecoded['x5c'])) {
            throw new InvalidTokenException("Malformed JWT token header: $header");
        }
        $publicKeyString = Base64::urlDecode($headerDecoded['x5c']);

        // check if the public key is active (in the cache)
        if (!$this->cache->hasItem($this->publicKeysCacheKey . '.' . md5($publicKeyString))) {
            throw new InvalidTokenException("Invalid public key");
        }

        // check the token signature
        $publicKey = openssl_pkey_get_public($publicKeyString);
        $signatureDecoded = Base64::urlDecode($signature);
        if (!openssl_verify("$header.$payload", $signatureDecoded, $publicKey, OPENSSL_ALGO_SHA256)) {
            throw new InvalidTokenException("Invalid token signature: $token");
        }

        // decode the payload
        $payloadDecoded = json_decode(Base64::urlDecode($payload), true);

        // check required claims
        $requiredClaims = ['sub', 'jti', 'iss', 'iat', 'nbf', 'exp', 'cip', 'cag','admin'];
        $missing = array_diff($requiredClaims, array_keys($payloadDecoded));
        if (!empty($missing)) {
            throw new InvalidTokenException("Missing claims on the payload: " . implode(',', $missing));
        }

        // Check if the token id is still valid (using jti from cache)
        if (!$this->cache->hasItem("jwt.jti.{$payloadDecoded['sub']}.{$payloadDecoded['jti']}")) {
            throw new InvalidTokenException("Token is revoked.");
        }

        // check token expiration
        if ($checkTTL) {
            $now = time();
            if ($payloadDecoded['nbf'] > $now || $payloadDecoded['exp'] < $now) {
                throw new InvalidTokenException("Token expired");
            }
        }

        // finally return the payload
        return $payloadDecoded;
    }
}