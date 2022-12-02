<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\Action;
use CodeLathe\Core\Objects\File;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Objects\User;

class SearchResult implements \JsonSerializable, ObjectInterface
{

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $highlighted;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var array
     */
    protected $subject;

    /**
     * SearchToast constructor.
     * @param string $type
     * @param array $highlighted
     * @param array $subject
     */
    public function __construct(string $type, array $highlighted, array $subject)
    {
        $this->type = $type;
        $this->highlighted = $highlighted;
        $this->subject = $subject;
    }

    /**
     * @param string $type
     * @param array $data
     * @param array|null $highlights
     * @return SearchResult|null
     */
    public static function withDBData(string $type, array $data, ?array $highlights = []): ?self
    {
        if (in_array($type, ['message', 'user', 'action', 'file', 'channel'])) {
            return new static($type, $highlights, $data);
        }
        return null;
    }

    public function getArray(): array
    {
        return [
            'type' => $this->type,
            'highlighted' => $this->highlighted,
            $this->type => $this->subject
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
}