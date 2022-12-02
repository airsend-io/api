<?php

namespace Tests\Unit\Dummy;

class DummyRecipient implements \CodeLathe\Service\Mailer\RecipientInterface
{

    protected $name;
    protected $email;
    protected $address;

    public function __construct($name, $email, $address)
    {
        $this->name = $name;
        $this->email = $email;
        $this->address = $address;
    }

    /**
     * Name of the recipient. The name is optional, so it can return null.
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Recipient raw email address. Pattern: jonsnow@winterfel.com
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Complete email address. Pattern: Jon Snow <jonsnow@winterfel.com>
     * If the name is null, this method will just return the raw email address.
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }
}