<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\ResponseFilterException;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\JsonOutput;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Stream;

/**
 * Class CaptchaMiddleware
 *
 * Verifies Google ReCaptcha tokens
 *
 * @package CodeLathe\Application\Middleware
 */
class ResponseFilterMiddleware implements MiddlewareInterface
{

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws InvalidFailedHttpCodeException
     * @throws GuzzleException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // try the query string
        $responseFilter = null;
        $query = $request->getQueryParams();
        if (isset($query['response_filter'])) {

            // store the response filter and remove it from query
            $responseFilter = $query['response_filter'] ?? null;
            unset($query['response_filter']);
            $request = $request->withQueryParams($query);

        }

        // try the body params (if request filter is not on query)
        if ($responseFilter === null) {

            $params = $request->getParsedBody();
            if (isset($params['response_filter'])) {

                // store the response filter and remove it from body
                $responseFilter = $params['response_filter'];
                unset($params['response_filter']);
                $request = $request->withParsedBody($params);

            }

        }

        $response = $handler->handle($request);

        // only filter if there is a response_filter and content-type is json
        if ($responseFilter !== null && $response->hasHeader('content-type') && $response->getHeader('content-type')[0] === 'application/json') {
            try {
                $response = $this->filterResponse($response, $responseFilter);
            } catch (ResponseFilterException $e) {
                $response = $this->clearResponse($response);
                return JsonOutput::error("Invalid response filter: `{$e->getMessage()}`", 422)->write($response);
            }
        }

        return $response;
    }

    /**
     * @param ResponseInterface $response
     * @param string $responseFilter
     * @return ResponseInterface
     * @throws ResponseFilterException
     */
    protected function filterResponse(ResponseInterface $response, string $responseFilter): ResponseInterface
    {

        // TODO - Convert everything that is outside the main process method into a reusable service

        $body = (string)$response->getBody();
        $payload = Json::decode($body, true);

        $output = [];
        $paths = array_map('trim', explode(";", $responseFilter));
        foreach ($paths as $path) {
            $output = $this->mergeRecursive($output, $this->extractPath($payload, $path));
        }

        // replaces the response body
        $bodyStream = new Stream(fopen('php://temp', 'w+b'));
        $bodyStream->write(Json::encode($output, JSON_PRETTY_PRINT));
        return $response->withBody($bodyStream);

    }

    protected function clearResponse(ResponseInterface $response): ResponseInterface
    {
        $bodyStream = new Stream(fopen('php://temp', 'w+b'));
        return $response->withBody($bodyStream);
    }

    /**
     * @param array $payload
     * @param string|array $path
     * @return array
     * @throws ResponseFilterException
     */
    protected function extractPath(array $payload, $path): array
    {
        if (!is_array($path)) {
            $path = explode('.', $path);
        }

        $step = array_shift($path);

        // last step, so finalize the recursion
        if (empty($path)) {

            if ($this->isArrayStep($step)) {
                // is an array step, so extract the range and return
                return $this->extractArray($payload, $step);
            }

            // key must exist
            if (!isset($payload[$step])) {
                throw new ResponseFilterException("Invalid key $step.");
            }

            // return witout recursion
            return [$step => $payload[$step]];

        }

        if ($this->isArrayStep($step)) {

            // is an array step, so extract the range and recursively extract each item
            $output = [];
            foreach ($this->extractArray($payload, $step) as $key => $value) {
                if (!is_array($value)) {
                    throw new ResponseFilterException("'$value' is not an array/object. Please review your filter.");
                }
                $output[$key] = $this->extractPath($value, $path);
            }
            return $output;
        }

        // key must exist
        if (!isset($payload[$step])) {
            throw new ResponseFilterException("Invalid key '$step'.");
        }

        // the item must be an array
        if (!is_array($payload[$step])) {
            throw new ResponseFilterException("'$step' is not an object or array.");
        }

        // recursively go forward to next step
        return [$step => $this->extractPath($payload[$step], $path)];

    }

    protected function isArrayStep(string $step): bool
    {
        return !preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $step);
    }

    /**
     * @param array $payload
     * @param string $step
     * @return array
     * @throws ResponseFilterException
     */
    protected function extractArray(array $payload, string $step): array
    {

        // split the property filter (if exists)
        $propertyFilter = null;
        if (preg_match('/^([^\[]+)\[([^]]+)\]$/', $step, $matches)) {
            $step = $matches[1];
            $propertyFilter = $matches[2];
        }

        $keys = [];

        // single array position
        if (preg_match('/^[0-9]+$/', $step)) {
            $keys[] = (int)$step;
        }

        // entire array
        if (strlen($step) === 0 || $step === '*') {
            $keys = array_keys($payload);
        }

        // specific array keys
        if (preg_match('/^[^,]+(?:,[^,]+)+$/', $step)) {
            $keys = explode(',', $step);
            $keys = array_map('trim', $keys);
        }

        if (preg_match('/^([0-9]+)?-([0-9]+)?$/', $step, $matches)) {
            $keys = array_keys($payload);
            $min = $matches[1] ?? min($keys);
            $max = $matches[2] ?? max($keys);
            $keys = array_intersect(range($min, $max), $keys);
        }

        if ($propertyFilter !== null) {
            $keys = array_intersect($keys, $this->handlePropertyFilter($propertyFilter, $payload));
        }

        // if no keys are found, consider it invalid
        if (empty($keys)) {
            throw new ResponseFilterException("Invalid step: '$step'");
        }

        $output = [];
        foreach ($keys as $key) {

            // handle nullable fields
            $nullable = false;
            if (is_string($key) && preg_match('/^(\?)(.+)$/', $key, $matches)) {
                $key = $matches[2];
                $nullable = true;
            }

            if (!$nullable && !array_key_exists($key, $payload)) {
                throw new ResponseFilterException("Invalid array/object key: '$key'");
            }

            $output[$key] = $payload[$key] ?? null;
        }

        return $output;
    }

    protected function mergeRecursive(array $a1, array $a2): array
    {
        $output = [];
        $keys1 = array_keys($a1);
        $keys2 = array_keys($a2);

        while (!empty($keys1)) {

            // get the first key from the first keys array
            $key1 = array_shift($keys1);

            if ($key1 !== null) {

                if (($i = array_search($key1, $keys2)) !== false) {
                    // key is present in both arrays, so merge it, and remove the key from keys2
                    $output[$key1] = $this->mergeRecursive($a1[$key1], $a2[$key1]);
                    unset($keys2[$i]);
                } else {
                    // key is present only on first array, so just include it
                    $output[$key1] = $a1[$key1];
                }
            }

        }

        foreach ($keys2 as $key) {
            $output[$key] = $a2[$key];
        }

        return $output;
    }

    /**
     * @param string $filter
     * @param array $payload
     * @return array
     * @throws ResponseFilterException
     */
    protected function handlePropertyFilter(string $filter, array $payload): array
    {

        $ops = [
            '=' => function($v1, $v2) { return $v1 == $v2; },
            '<>' => function($v1, $v2) { return $v1 != $v2; },
            '>' => function($v1, $v2) { return $v1 > $v2; },
            '<' => function($v1, $v2) { return $v1 < $v2; },
            '>=' => function($v1, $v2) { return $v1 >= $v2; },
            '<=' => function($v1, $v2) { return $v1 <= $v2; },
        ];

        $filter = trim($filter);
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_-]*)\s*(=|<>|<=|<|>=|>)\s*([^\s=].*)$/', $filter, $matches)) {
            throw new ResponseFilterException("Invalid property filter '$filter'");
        }

        $property = $matches[1];
        $op = $matches[2];
        $propertyValue = $matches[3];

        $output = [];
        foreach ($payload as $key => $value) {
            if (($ops[$op])($value[$property], $propertyValue)) {
                $output[] = $key;
            }
        }

        return $output;
    }

}