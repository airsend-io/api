<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

interface AttachmentInterface
{

    public function getFileName(): string;

    /**
     * Name of the recipient. The name is optional, so it can return null.
     * @return string|null
     */
    public function getContent(): string;

    /**
     * Attachment mime type. Example: text/csv
     * @return string
     */
    public function getMime(): string;

}