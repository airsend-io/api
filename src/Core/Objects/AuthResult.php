<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Objects;

/**
 * Class AuthResult - Value object used to return the authentication result
 * @package CodeLathe\Core\Objects
 */
class AuthResult
{

    /**
     * @var bool
     */
    protected $success;

    /**
     * @var string
     */
    protected $message;

    public function __construct(bool $success, ?string $message = '')
    {
        $this->success = $success;
        $this->message = $message;
    }

    public function isValid(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}