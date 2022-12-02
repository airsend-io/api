<?php
/**
 *  Copyright (c) 2020 CodeLathe. All rights Reserved.
 *  This file is part of FileCloud  http://www.getfilecloud.com
 */

namespace CodeLathe\Core\Managers;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Serializers\FileSerializer;
use CodeLathe\Core\Serializers\JSONSerializer;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class StaticManager
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * StaticManager constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws SecurityException
     */
    public function get(Request $request, Response $response, $args)
    {
        if (!isset($args['path'])) {
            return (new JSONSerializer(false))->write($response);
        }

        $path = DIRECTORY_SEPARATOR . $args['path'];

        // For now, for security we only allow very specific paths
        $allowedArray = [
            '/wiki/math/katex.min.css',
            '/wiki/math/katex.min.js',
            '/wiki/math/auto-render.min.js'
        ];
        if (in_array($path, $allowedArray))
        {
            $path = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR.  $path;
            return (new FileSerializer($path))->write($response);
        }

        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        if (($dir != '/wiki/math/fonts') || ($ext == 'woff' || $ext == 'woff2')) {
            $path = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . $path;
            return (new FileSerializer($path))->write($response);
        }
        $this->logger->error('Bad Static Request: '.$path);
        return (new JSONSerializer(false))->write($response);
    }
}