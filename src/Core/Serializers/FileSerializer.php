<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Serializers;

use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Utility\MimeType;
use CodeLathe\Core\Utility\SafeFile;
use GuzzleHttp\Psr7\LazyOpenStream;

class FileSerializer
{
    protected  $filepath;

    /**
     * FileSerializer constructor.
     * @param string $filepath
     * @throws SecurityException
     */
    public function __construct(string $filepath)
    {
        if (!SafeFile::isAllowedPath($filepath))
            throw new SecurityException();

        $this->filepath = $filepath;
    }

    public function write(\Psr\Http\Message\ResponseInterface &$response)
    {
        $newStream = new LazyOpenStream($this->filepath, 'r');

        $pi = Path::mb_pathinfo($this->filepath);
        $extension = "";
        if (isset($pi["extension"]))
            $extension = mb_strtolower($pi["extension"]);
        $mimeType = MimeType::getFileExtension($extension);
        return $response->withBody($newStream)->withHeader('Content-Type', $mimeType);
    }
};