<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Messaging\Events;

class FSCreateFolderEvent extends FSEvent implements RtmInterface
{

    public static function eventName(): string
    {
        return 'fs.folder.create';
    }
}