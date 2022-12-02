<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\LoggerFacade;
use Slim\Logger;

class Message implements \JsonSerializable, ObjectInterface
{
    CONST MESSAGE_TYPE_ADMIN        = 0;
    CONST MESSAGE_TYPE_NEW          = 1;
    CONST MESSAGE_TYPE_REPLY        = 2;
    CONST MESSAGE_TYPE_QUOTE        = 3;
    CONST MESSAGE_TYPE_FORWARD      = 4;
    CONST MESSAGE_TYPE_BOT          = 5;

    CONST MESSAGE_DELIVERY_CHAT             = 0;
    CONST MESSAGE_DELIVERY_CHAT_AND_MAIL    = 1;

    const MESSAGE_SOURCE_MAP = [
        0 => 'chat',
        1 => 'email'
    ];

    const MESSAGE_MAX_LENGTH = 5000;

    protected $message;

    /**
     * Message constructor.
     *
     * @param int $userId
     * @param int $channelId
     * @param string $userDisplayName
     * @param string $text
     * @param int $messageType
     * @param string|null $source
     * @return Message
     */
    public static function create(int $userId, int $channelId, string $userDisplayName,
                                    string $text, int $messageType, ?string $source = 'chat') : Message
    {
        $instance = new self();
        $instance->message['user_id'] = $userId;
        $instance->message['channel_id'] = $channelId;
        $instance->message['display_name'] = $userDisplayName;
        $instance->message['content_text'] = $text;
        $instance->message['attachments'] = null;
        $instance->message['message_type'] = $messageType;
        $instance->message['is_edited'] = false;
        $instance->message['is_deleted'] = false;
        $instance->message['parent_message'] = null;
        $instance->message['source'] = array_search($source, static::MESSAGE_SOURCE_MAP) ?: 0;
        $instance->message['emoticons'] = null;
        $instance->message['created_on'] = date('Y-m-d H:i:s');
        $instance->message['send_email'] = false;
        return $instance;
    }


    public static function withDBData(array $a_record) : ?self
    {
        if(array_filter($a_record)){
            $instance = new self();
            $instance->loadWithDBData($a_record);
            return $instance;
        }
        else
            return null;
    }

    public function loadWithDBData(array $a_record) : void
    {
        $this->message['id']                = Convert::toIntNull($a_record['id']);
        $this->message['user_id']           = Convert::toIntNull($a_record['user_id']);
        $this->message['channel_id']        = Convert::toIntNull($a_record['channel_id']);
        $this->message['display_name']      = Convert::toStrNull($a_record['display_name']);
        $this->message['content_text']      = Convert::toStrNull($a_record['content_text']);
        $this->message['attachments']       = Convert::toStrNull($a_record['attachments']);
        $this->message['message_type']      = Convert::toIntNull($a_record['message_type']);
        $this->message['is_edited']         = Convert::toBool($a_record['is_edited']);
        $this->message['is_deleted']         = Convert::toBool($a_record['is_deleted']);
        $this->message['parent_message']    = Convert::toStrNull($a_record['parent_message']);
        $this->message['source']            = Convert::toStrNull($a_record['source']);
        $this->message['emoticons']         = Convert::toStrNull($a_record['emoticons']);
        $this->message['created_on']        = Convert::toStrNull($a_record['created_on']);
        $this->message['send_email']         = Convert::toBool($a_record['send_email']);
    }

    public function getId() : int
    {
        return $this->message['id'];
    }

    public function setId(int $id) : void
    {
        $this->message['id'] = $id;
    }

    public function getChannelId() : int
    {
        return $this->message['channel_id'];
    }

    public function setChannelId(int $id) : void
    {
        $this->message['channel_id'] = $id;
    }

    public function getUserId() : int
    {
        return $this->message['user_id'];
    }

    public function setUserId(int $id) : void
    {
        $this->message['user_id'] = $id;
    }

    public function getDisplayName() : string
    {
        return $this->message['display_name'];
    }

    public function setDisplayName(string $value) : void
    {
        $this->message['display_name'] = $value;
    }

    public function getIsEdited() : bool {
        return $this->message['is_edited'];
    }

    public function setIsEdited(bool $value) : void
    {
        $this->message['is_edited'] = $value;
    }

    public function getIsDeleted() : bool {
        return $this->message['is_deleted'];
    }

    public function setIsDeleted(bool $value) : void
    {
        $this->message['is_deleted'] = $value;
    }

    public function getText() : ?string
    {
        return $this->message['content_text'];
    }

    public function setText(string $value) : void
    {
        $this->message['content_text'] = $value;
    }

    public function getMessageType() : int
    {
        return $this->message['message_type'];
    }

    public function setMessageType(int $value) : void
    {
        $this->message['message_type'] = $value;
    }

    public function addParentMessage(MessageParent $parentMessage) : void
    {
        $this->convertJsonTypesToArray();
        $this->message['parent_message'][] = $parentMessage->getArray();
    }

    public function deleteParentMessage(int $messageId) : bool
    {
        $this->convertJsonTypesToArray();
        foreach($this->message['parent_message'] as $index => $item)
        {
            $id = (int)$item['mid'];
            if ($messageId === $id)
            {
                unset($this->message['parent_message'][$index]);
                $this->message['parent_message'] = array_values($this->message['parent_message']);
                return true;
            }
        }
        return false;
    }

    public function removeAllParentMessages() : void
    {
        $this->convertJsonTypesToArray();
        unset ($this->message['parent_message']);
    }

    /**
     * @return MessageParent[]
     */
    public function getParentMessages() : array
    {
        $this->convertJsonTypesToArray();
        $messages = [];
        foreach ($this->message['parent_message'] as $item) {
            $parentMsg = MessageParent::create($item['mid'],$item['uid'], $item['txt'],$item['ts']);
            $messages[] = $parentMsg;
        }
        return $messages;
    }


    /**
     * @param MessageAttachment $attachment
     * @deprecated Attachment field is replaced by message_attachments table. This method is kept for compatibility and
     * should be removed soon.
     */
    public function addAttachment(MessageAttachment $attachment) : void
    {
        $this->convertJsonTypesToArray();
        $this->message['attachments'][] = [
            'ctp' => $attachment->getContentType(),
            'content' => json_encode($attachment->getContent()),
        ];
    }

    /**
     * @param string $type
     * @param string $value
     * @return void
     * @deprecated attachment column was replaced with the message_attachments table
     */
    public function deleteAttachment(string $type, string $value): void
    {
        $this->convertJsonTypesToArray();
        foreach($this->message['attachments'] as $index => $item) {
            $key = $type === MessageAttachment::ATTACHMENT_TYPE_FILE ? 'path' : 'url';
            $content  = json_decode($item['content'], true);
            if ($item['ctp'] === $type && ($content[$key] ?? '') === $value) {
                unset($this->message['attachments'][$index]);
            }
        }
        $this->message['attachments'] = array_values($this->message['attachments']);
    }

    /**
     * @return MessageAttachment[]
     * @deprecated attachments field from Message table/object was replaced with message_attachments table
     */
    public function getAttachments() : array
    {
        $this->convertJsonTypesToArray();
        $attachments = [];
        foreach ($this->message['attachments'] as $item) {
            $content = Json::decode($item['content'], true);
            $key = $item['ctp'] === MessageAttachment::ATTACHMENT_TYPE_FILE ? $content['path'] : $content['url'];
            $attachment = MessageAttachment::create(json_decode($item['content'], true), $item['ctp'], $key);
            $attachments[] = $attachment;
        }
        return $attachments;
    }

    /**
     * @deprecated Attachments column was replaced with the message_attachments table
     */
    public function clearAttachments(): void
    {
        $this->message['attachments'] = null;
    }


    public function addEmoticon(MessageEmoticon $emoticon) : void
    {
        $userId = $emoticon->getUserId();
        $emojiValue = $emoticon->getEmojiValue();

        $this->convertJsonTypesToArray();
        $found = false;
        foreach($this->message['emoticons'] as $index => $item)
        {
            $ev = (string)$item['ev'];
            $uid = (int)$item['uid'];
            if ($uid === $userId && $ev === $emojiValue)
            {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->message['emoticons'][] = $emoticon->getArray();
        }
    }

    public function deleteEmoticon(MessageEmoticon $emoticon) : bool
    {
        $userId = $emoticon->getUserId();
        $emojiValue = $emoticon->getEmojiValue();
        $this->convertJsonTypesToArray();
        foreach($this->message['emoticons'] as $index => $item)
        {
            $ev = (string)$item['ev'];
            $uid = (int)$item['uid'];
            if ($uid === $userId && $ev === $emojiValue)
            {
                unset($this->message['emoticons'][$index]);
                $this->message['emoticons'] = array_values($this->message['emoticons']);
                return true;
            }
        }
        return false;
    }

    public function getEmoticons() : array
    {
        $this->convertJsonTypesToArray();
        $annotations = [];
        foreach ($this->message['emoticons'] as $item) {
            $annotation = MessageEmoticon::create($item['uid'],$item['dn'], $item['ev']);
            $annotations[] = $annotation;
        }
        return $annotations;
    }

    private function convertJsonTypesToArray() : void
    {
        if (!isset($this->message['attachments'])) {
            $this->message['attachments'] = [];
        }
        if (is_string($this->message['attachments'])) {
            $this->message['attachments'] = json_decode($this->message['attachments'], true);
        }

        if (!isset($this->message['emoticons']))
            $this->message['emoticons'] = [];

        if (is_string($this->message['emoticons'])) {
            $this->message['emoticons'] = json_decode($this->message['emoticons'], true);
        }

        if (!isset($this->message['parent_message']))
            $this->message['parent_message'] = [];

        if (is_string($this->message['parent_message'])) {
            $this->message['parent_message'] = json_decode($this->message['parent_message'], true);
        }
    }

    private function convertJsonTypesToNullableString() : void
    {
        if (!isset($this->message['attachments'])) {
            $this->message['attachments'] = null;
        }
        if (is_array($this->message['attachments'])) {
            $this->message['attachments'] = Json::encode($this->message['attachments']);
        }

        if (!isset($this->message['emoticons'])) {
            $this->message['emoticons'] = null;
        }
        if (is_array($this->message['emoticons'])) {
            $this->message['emoticons'] = Json::encode($this->message['emoticons']);
        }

        if (!isset($this->message['parent_message'])) {
            $this->message['parent_message'] = null;
        }
        if (is_array($this->message['parent_message'])) {
            $this->message['parent_message'] = Json::encode($this->message['parent_message']);
        }

    }

    public function getCreatedOn() : string
    {
        return $this->message['created_on'];
    }

    public function setCreatedOn(string $value) : void
    {
        $this->message['created_on'] = $value;
    }

    public function setSendEmail(bool $value): void
    {
        $this->message['send_email'] = $value;
    }

    public function getSendEmail(): bool
    {
        return $this->message['send_email'];
    }

    public function getArray() : array
    {
       $this->convertJsonTypesToArray();
       return $this->message;
    }

    public function getArrayWithJsonString() : array
    {
        $this->convertJsonTypesToNullableString();
        return $this->message;
    }

    public function jsonSerialize() : array
    {
        $this->convertJsonTypesToArray();
        return  $this->message;
    }

    public function getArrayForUpdate(): array
    {
        $array = $this->getArrayWithJsonString();
        $array['source'] = array_search($array['source'], self::MESSAGE_SOURCE_MAP);
        return $array;
    }
};
