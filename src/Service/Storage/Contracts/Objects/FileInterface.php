<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Contracts\Objects;

interface FileInterface extends FSEntryInterface
{

    public function getExtension(): string;

    public function getFileSize(): int;

    public function getFileId(): string;

    public function isComplete(): bool;

    public function getStoragePath(): string;

    public function getStorageZoneId(): string;

    public function getBackstoreData(): string;

    public function getOwner(): ?string;

    public function getVersionedDate(): ?string;

}