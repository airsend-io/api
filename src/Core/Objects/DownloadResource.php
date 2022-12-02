<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Serializers\FileSerializer;
use CodeLathe\Core\Utility\ContainerFacade;
use Exception;
use Mimey\MimeTypes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class DownloadResource
{
    protected $type;

    protected $payload;

    public function __construct(string $type, array $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(ResponseInterface $response): ResponseInterface
    {
        $handler = [$this, "{$this->type}Handler"];

        if (!is_callable($handler)) {
            throw new Exception('Invalid download handler');
        }

        try {
            return $handler($response);
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function redirectHandler(ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(302)->withHeader('Location', $this->payload['url']);
    }

    protected function fileHandler(ResponseInterface $response): ResponseInterface
    {
        return (new FileSerializer($this->payload['file']))->write($response);
    }

    protected function contentHandler(ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->payload['content']);
        return $response->withHeader('Content-Type', $this->payload['type']);
    }

    protected function localHandler(ResponseInterface $response): ResponseInterface
    {
        $content = file_get_contents($this->payload['tmpfile']);
        $response->getBody()->write($content);
        foreach ($this->payload as $key => $entry) {
            if (is_int($key) && preg_match('/^([^:]+):(.*)$/', $entry, $matches)) {
                $response = $response->withHeader($matches[1], $matches[2]);
            }
        }
        return $response;
    }

    protected function streamHandler(ResponseInterface $response): ResponseInterface
    {
        /** @var StreamInterface $stream */
        $stream = $this->payload['stream'];

        // parse extension
        $extension = $this->payload['extension'] ?? null;
        if ($extension !== null) {
            /** @var MimeTypes $mimeParser */
            $mimeParser = ContainerFacade::get(MimeTypes::class);
            $mimeType = $mimeParser->getMimeType($extension);
            if ($mimeType !== null) {
                $response = $response->withHeader('Content-Type', $mimeType);
            }
        }

        // parse size
        $size = $this->payload['size'] ?? null;
        if ($size !== null) {
            $response = $response->withHeader('Content-Length', $size);
        }

        return $response->withBody($stream);

    }
}