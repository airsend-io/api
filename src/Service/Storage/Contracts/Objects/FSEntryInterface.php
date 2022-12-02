<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Contracts\Objects;

use Carbon\Carbon;

interface FSEntryInterface
{
    public function getId(): int;

    public function getPath(): string;

    public function getName(): string;

    public function getParentPath(): string;

    public function getParentId(): ?int;

    public function getCreatedOn(): Carbon;

    public function getModifiedOn(): ?Carbon;

    public function getLastAccessOn(): ?Carbon;
}