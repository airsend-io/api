<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Serializers;

use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Utility\MimeType;
use CodeLathe\Core\Utility\SafeFile;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\LazyOpenStream;
use Psr\Http\Message\ResponseInterface;

class UrlStreamSerializer
{

    protected $url;
    protected $httpClient;

    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function from($url)
    {
        $this->url = $url;
        return $this;
    }

    public function write(ResponseInterface &$response)
    {
        $httpResponse = $this->httpClient->get($this->url, ['stream' => true]);

        foreach ($httpResponse->getHeaders() as $header => $value) {
            $response = $response->withHeader($header, $value);
        }
        $response = $response->withBody($httpResponse->getBody());
        return $response;
    }
};