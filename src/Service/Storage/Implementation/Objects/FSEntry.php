<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Storage\Implementation\Objects;

use Carbon\Carbon;
use CodeLathe\Service\Storage\Contracts\Objects\FSEntryInterface;

abstract class FSEntry implements FSEntryInterface
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var Carbon
     */
    protected $createdOn;

    /**
     * @var Carbon|null
     */
    protected $modifiedOn;

    /**
     * @var Carbon|null
     */
    protected $lastAccessOn;

    /**
     * @var int|null
     */
    protected $parentId;

    public function __construct(int $id, string $path, ?int $parentId, string $createdOn, ?string $modifiedOn, ?string $lastAccessOn)
    {
        $this->path = $path;
        $this->id = $id;
        $this->parentId = $parentId;
        $this->createdOn = Carbon::createFromFormat('Y-m-d H:i:s', $createdOn);
        $this->modifiedOn = $modifiedOn === null ?  null : Carbon::createFromFormat('Y-m-d H:i:s', $createdOn);
        $this->lastAccessOn = $lastAccessOn === null ? null : Carbon::createFromFormat('Y-m-d H:i:s', $lastAccessOn);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        $pieces = explode('/', $this->path);
        return array_pop($pieces);
    }

    public function getParentPath(): string
    {
        $pieces = explode('/', $this->path);
        array_pop($pieces);
        return implode('/', $pieces) ?? '';
    }

    public function getCreatedOn(): Carbon
    {
        return $this->createdOn;
    }

    public function getModifiedOn(): ?Carbon
    {
        return $this->modifiedOn;
    }

    public function getLastAccessOn(): ?Carbon
    {
        return $this->lastAccessOn;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }
}