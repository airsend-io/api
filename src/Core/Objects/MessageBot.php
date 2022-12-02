<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;


use CodeLathe\Core\Utility\Convert;
use mysql_xdevapi\Exception;

class MessageBot implements \JsonSerializable, ObjectInterface
{

    protected $botMessage;

    /**
     * MessageEmoticon constructor.
     *
     * @param int $type
     * @param string $message
     * @param string|null $i18nKey
     * @param int|null $i18nCount
     * @param array $i18nParams
     * @return MessageBot
     */
    public static function create(int $type,
                                  string $message,
                                  ?string $i18nKey = null,
                                  ?int $i18nCount = null,
                                  array $i18nParams = []) : MessageBot
    {

        $instance = new self();

        $instance->botMessage['type'] = $type;
        $instance->botMessage['bot_message'] = $message;
        if ($i18nKey !== null) {
            $instance->botMessage['i18n'] = [
                'key' => $i18nKey,
                'count' => $i18nCount,
                'params' => $i18nParams,
            ];
        }
        return $instance;
    }

    public function getArray() : array
    {
        return $this->botMessage;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize ()
    {
        return $this->botMessage;
    }
}