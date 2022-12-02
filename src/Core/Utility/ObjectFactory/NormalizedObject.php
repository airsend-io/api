<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Utility\ObjectFactory;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Objects\MessageAttachment;
use CodeLathe\Core\Objects\ObjectInterface;
use CodeLathe\Core\Utility\ContainerFacade;
use JsonSerializable;

class NormalizedObject implements ObjectInterface, \JsonSerializable
{
    protected $array;

    public function __construct (array $array)
    {
        $this->array = $array;

        $this->normalizeData();
    }

    /**
     * Normalize the data to the expected structure in output
     */
    private function normalizeData(): void
    {

        $this->createTsVersion('created_on');
        $this->createTsVersion('updated_on');
        $this->createTsVersion('last_active_on');
        $this->createTsVersion('due_on');


        if (isset($this->array['content_text'])) {
            if (!empty($this->array['content_text'])) {
                $this->array['content'] = $this->array['content_text'];
            } else {
                $this->array['content'] = null;
            }
        }

        if (!empty($this->array['created_by'])) {
            $this->array['created_by'] = (int) $this->array['created_by'] ;
        }

        if (!empty($this->array['owned_by'])) {
            $this->array['owned_by'] = (int) $this->array['owned_by'] ;
        }

        if (!empty($this->array['updated_by'])) {
            $this->array['updated_by'] = (int) $this->array['updated_by'] ;
        }

        if (!empty($this->array['channel_id'])) {
            $this->array['channel_id'] = (int) $this->array['channel_id'] ;
        }

        if (array_key_exists('content_text', $this->array)) {
            unset($this->array['content_text']);
        }

        if (isset($this->array['parent_message']) && count($this->array['parent_message']) == 1) {
            $parent = [];
            $parent['message_id'] = (int)$this->array['parent_message'][0]['mid'];
            $parentMessage = ContainerFacade::get(DataController::class)->getMessageById((int)$parent['message_id']);
            if (!empty(($parentMessage->getArray())['attachments'])) {

                $attachmentArray = ($parentMessage->getArray())['attachments'];
                $attachObjArray = [];
                foreach ($attachmentArray as $attachment) {
                    //$this->logger->info(print_r($attachment,true));
                    if ($attachment['ctp'] == MessageAttachment::ATTACHMENT_TYPE_FILE) {
                        $content_array = json_decode($attachment['content'], true);
                        $content_array['has_thumb'] = ContainerFacade::get(FileOperations::class)->hasThumb($content_array['path'], 120, 120);
                        $attachment['content'] = $content_array;
                    } else {
                        $attachment['content'] = json_decode($attachment['content'], true);
                    }
                    $attachObjArray[] = $attachment;
                }
                $parent['attachments'] =  $attachObjArray;
            }

            $parent['content'] = $this->array['parent_message'][0]['txt'];
            $parent['user_id'] = (int)$this->array['parent_message'][0]['uid'];
            $parent['created_on'] = $this->array['parent_message'][0]['ts'];
            $parent['created_on_ts'] = strtotime($this->array['parent_message'][0]['ts']);

            $parentUser = ContainerFacade::get(DataController::class)->getUserById((int)$parent['user_id']);
            if (empty($parentUser)) {
                $parent['display_name'] = "(Unknown)";
            }
            else {
                $parent['display_name'] = $parentUser->getDisplayName();
            }

            unset($this->array['parent_message']);
            $this->array['parent_message'] = $parent;
        }
        else {
            // Set this to null only if it is present
            if (isset($this->array['parent_message'])) {
                $this->array['parent_message'] = null;
            }
        }
    }


    public function createTsVersion(string $key)
    {
        if (array_key_exists($key, $this->getArray())) {
            if (!empty($this->array[$key])) {
                $this->array[$key.'_ts'] = strtotime($this->array[$key]);
            }
            else {
                $this->array[$key.'_ts'] = null;
            }
        }

    }

    public function getValueForKey($key)
    {
        return $this->array[$key] ?? NULL;
    }

    public function getArray () : array
    {
        return $this->array;
    }


    public function addObjectPayload (string $key, ?ObjectInterface $payload) : void
    {
        //if (!empty($payload))
            $this->array[$key] = $payload;
    }

    public function addArray (string $key, array $payload) : void
    {
        $this->array[$key] = $payload;
    }


    public function addInt (string $key, int $payload) : void
    {
        $this->array[$key] = $payload;
    }

    public function addBool (string $key, bool $payload) : void
    {
        $this->array[$key] = $payload;
    }


    public function addPOD (string $key, string $payload) : void
    {
        $this->array[$key] = $payload;
    }

    public function addObject(ObjectInterface $payload)
    {
        $this->array = array_merge($this->array, $payload->getArray());
    }

    public function transform(string $key, callable $callback): void
    {
        $this->array[$key] = $callback($this->array[$key]);
    }

    public function remove(string $key)
    {
        unset($this->array[$key]);
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
        return $this->array;
    }
}