<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;

class Attachment implements AttachmentInterface
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var string
     */
    protected $mime;


    /**
     * Attachment constructor.
     *
     * @param string $name
     * @param string $mime
     * @param string $content
     */
    public function __construct(string $name, string $mime, string $content)
    {
        $this->name = $name;
        $this->content = $content;
        $this->mime = $mime;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getFileName(): string
    {
        return $this->name;
    }
}