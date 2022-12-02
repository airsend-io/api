<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

class HtmlParser extends AbstractParser
{
    use PhpWordParserTrait;

    protected function reader(): string
    {
        return 'HTML';
    }
}