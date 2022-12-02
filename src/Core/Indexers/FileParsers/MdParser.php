<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

use GuzzleHttp\Psr7\Stream;

class MdParser extends AbstractParser
{

    protected function extractText(string $filePath): string
    {
        return file_get_contents($filePath);
    }

    protected function sanitizeContent(string $text): string
    {
        // remove mentions
        $text = preg_replace('/!?\[([^]]+)]\([^)]+\)/', '$1', $text);

        // remove quotes
        $text = str_replace('`', '', $text);

        // remove multiple line breaks
        $text = preg_replace('/\R{2,}/', "\n", $text);

        // remove horizontal lines
        $text = preg_replace('/-{2,}|={2,}|_{2,}/', '', $text);

        return $text;
    }
}