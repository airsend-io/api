<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;

class MessageAttachment
{
    const ATTACHMENT_TYPE_FILE = "ATTACHMENT_TYPE_FILE";
    const ATTACHMENT_TYPE_UNFURL = "ATTACHMENT_TYPE_UNFURL";


    protected $attachment;

    /**
     * MessageAttachment constructor.
     *
     * @param array $content
     * @param string $contentType
     * @param string $key
     * @param int|null $messageId
     * @return MessageAttachment
     */
    public static function create(array $content, string $contentType, string $key, ?int $messageId = null) : MessageAttachment
    {
        $instance = new self();
        $instance->attachment['content'] = $content;
        $instance->attachment['attachment_type'] = $contentType;
        $instance->attachment['attachment_key'] = $key;
        $instance->attachment['message_id'] = $messageId;
        return $instance;
    }

    public function loadWithDBData(array $record) : void
    {
        $this->attachment['content'] = Convert::toArrayNull($record['content']);
        $this->attachment['attachment_type'] = Convert::toStrNull($record['attachment_type']);
        $this->attachment['attachment_key'] = Convert::toStrNull($record['attachment_key']);
        $this->attachment['message_id'] = Convert::toIntNull($record['message_id']);
    }

    public static function withDBData(array $a_record) : self
    {
        $instance = new self();
        $instance->loadWithDBData($a_record);
        return $instance;
    }

    public function setId(int $id): void
    {
        $this->attachment['id'] = $id;
    }

    public function getContent() : array
    {
        return $this->attachment['content'];
    }

    public function setContent(string $value) : void
    {
        $this->attachment['content'] = $value;
    }

    public function getContentType() : string
    {
        return $this->attachment['attachment_type'];
    }

    public function setContentType(string $value) : void
    {
        $this->attachment['attachment_type'] = $value;
    }

    public function setSize(int $value) : void
    {
        $this->attachment['size'] = $value;
    }

    public function getSize() : ?int
    {
        return $this->attachment['size'];
    }

    public function getArray() : array
    {
        return $this->attachment;
    }

    public function setMessageId(int $messageId): void
    {
        $this->attachment['message_id'] = $messageId;
    }

    public function getMessageId(): int
    {
        return $this->attachment['message_id'];
    }

}