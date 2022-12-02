<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

/**
 * Class ResourcePermission
 * @package CodeLathe\Core\Objects
 */
class FSPermission
{

    /**
     * @var bool
     */
    protected $read;

    /**
     * @var bool
     */
    protected $write;

    /**
     * @var bool
     */
    protected $delete;

    public function __construct(bool $read = false, bool $write = false, bool $delete = false)
    {
        $this->read = $read;
        $this->write = $write;
        $this->delete = $delete;
    }

    public function allowRead(): bool
    {
        return $this->read;
    }

    public function allowWrite(): bool
    {
        return $this->write;
    }

    public function allowDelete(): bool
    {
        return $this->delete;
    }

}