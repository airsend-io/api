<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

use Symfony\Component\Process\Process;

class PdfParser extends AbstractParser
{

    const PARSER_BIN_PATH = "/usr/bin/pdftotext";

    protected function extractText(string $filePath): string
    {

        // run pdftotext convert
        $destinyPath = $filePath . '_converted.txt';
        $command = [
            static::PARSER_BIN_PATH,
            '-nopgbrk',
            $filePath,
            $destinyPath
        ];
        $process = new Process($command);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception("Error from pdftotext: " . $process->getErrorOutput());
        }

        // read the text file contents and sanitize it
        $contents = $this->sanitizeContent(file_get_contents($destinyPath));

        // remove the generated text file
        unlink($destinyPath);

        // return
        return $contents;

    }

    protected function sanitizeContent(string $text): string
    {
        $text = preg_replace('/\s+([.,;:]+)/', '$1', $text);
        return preg_replace('/\s{2,}/', ' ', $text);
    }
}