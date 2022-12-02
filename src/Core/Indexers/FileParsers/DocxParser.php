<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

class DocxParser extends AbstractParser
{
    use PhpWordParserTrait;

    protected function reader(): string
    {
        return 'Word2007';
    }
}