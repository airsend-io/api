<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

use CodeLathe\Core\Utility\Convert;
use JsonSerializable;

class ContactForm implements JsonSerializable, ObjectInterface
{

    protected $contactForm = [];

    public static function create(int $ownerId,
                                  string $formTitle,
                                  string $confirmationMessage,
                                  ?int $copyFromChannelId,
                                  bool $enableOverlay,
                                  ?bool $enabled = true,
                                  ?int $color = null): self
    {
        $instance = new self();
        $instance->contactForm['owner_id'] = $ownerId;
        $instance->contactForm['form_title'] = $formTitle;
        $instance->contactForm['confirmation_message'] = $confirmationMessage;
        $instance->contactForm['form_hash'] = null;
        $instance->contactForm['copy_from_channel_id'] = $copyFromChannelId;
        $instance->contactForm['enable_overlay'] = $enableOverlay;
        $instance->contactForm['color'] = $color;
        $instance->contactForm['enabled'] = $enabled;
        $instance->contactForm['created_on'] = null;
        $instance->contactForm['updated_on'] = null;
        return $instance;
    }

    public static function withDBData(array $record): self
    {
        $instance = new self();
        $instance->loadWithDBData($record);
        return $instance;
    }

    public function loadWithDBData(array $record) : void
    {
        $this->contactForm['id'] = Convert::toIntNull($record['id']);
        $this->contactForm['owner_id'] = Convert::toIntNull($record['owner_id']);
        $this->contactForm['form_title'] = Convert::toStrNull($record['form_title']);
        $this->contactForm['confirmation_message'] = Convert::toStrNull($record['confirmation_message']);
        $this->contactForm['form_hash'] = Convert::toStr($record['form_hash']);
        $this->contactForm['copy_from_channel_id'] = Convert::toIntNull($record['copy_from_channel_id']);
        $this->contactForm['enable_overlay'] = Convert::toBool($record['enable_overlay']);
        $this->contactForm['color'] = Convert::toStrNull($record['color']);
        $this->contactForm['enabled'] = Convert::toBool($record['enabled']);
        $this->contactForm['created_on'] = Convert::toStrNull($record['created_on']);
        $this->contactForm['updated_on'] = Convert::toStrNull($record['updated_on']);
    }

    public function setId(string $id): void
    {
        $this->contactForm['id'] = $id;
    }

    public function getId(): int
    {
        return (int) $this->contactForm['id'];
    }

    public function getOwnerId(): int
    {
        return (int) $this->contactForm['owner_id'];
    }

    public function getTitle(): string
    {
        return $this->contactForm['form_title'];
    }

    public function getConfirmationMessage(): string
    {
        return $this->contactForm['confirmation_message'];
    }

    public function setTitle(string $title): void
    {
        $this->contactForm['form_title'] = $title;
    }

    public function setConfirmationMessage(string $message): void
    {
        $this->contactForm['confirmation_message'] = $message;
    }

    public function setEnableOverlay(bool $enableOverlay): void
    {
        $this->contactForm['enable_overlay'] = $enableOverlay;
    }

    public function setCopyFromChannelId(?int $copyFromChannelId): void
    {
        $this->contactForm['copy_from_channel_id'] = $copyFromChannelId;
    }

    public function getCopyFromChannelId(): ?int
    {
        return $this->contactForm['copy_from_channel_id'];
    }

    public function enable(): void
    {
        $this->contactForm['enabled'] = true;
    }

    public function disable(): void
    {
        $this->contactForm['enabled'] = false;
    }

    public function getArray(): array
    {
        return $this->contactForm;
    }

    /**
     * Generates the html code for the form, to be embedded on the customer pages.
     */
    public function generateHtmlForm()
    {

    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return $this->contactForm;
    }

    public function setFormHash(string $hash): void
    {
        $this->contactForm['form_hash'] = $hash;
    }

    public function setColor(?int $color): void
    {
        $this->contactForm['color'] = $color;
    }
}
