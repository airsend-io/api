<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Indexers\FileParsers;

class RtfParser extends AbstractParser
{
    use PhpWordParserTrait;

    protected function reader(): string
    {
        return 'RTF';
    }
}