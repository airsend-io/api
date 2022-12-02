<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

class SearchToast implements \JsonSerializable, ObjectInterface
{

    protected const TYPE_MESSAGE = 'message';
    protected const TYPE_ACTION = 'action';
    protected const TYPE_FILE = 'file';

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $header;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var ObjectInterface
     */
    protected $subject;

    /**
     * SearchToast constructor.
     * @param string $type
     * @param string $header
     * @param string $text
     * @param ObjectInterface $subject
     */
    public function __construct(string $type, string $header, string $text, $subject)
    {
        $this->type = $type;
        $this->header = $header;
        $this->text = $text;
        $this->subject = $subject;
    }

    public static function buildFromMessage(Message $message): self
    {
        $header = 'Sent by ' . $message->getDisplayName();
        return new static(static::TYPE_MESSAGE, $header, $message->getText(), $message);
    }

    public static function buildFromAction(Action $action): self
    {
        // TODO
    }

    public static function buildFromFile(File $file): self
    {
        // TODO
    }

    public function getArray(): array
    {
        return [
            'type' => $this->type,
            'header' => $this->header,
            'text' => $this->text,
            'subject' => $this->subject->getArray()
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->getArray();
    }
};
