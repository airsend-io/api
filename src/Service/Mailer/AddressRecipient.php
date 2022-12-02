<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

use CodeLathe\Core\Utility\LoggerFacade;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;

class AddressRecipient implements RecipientInterface
{

    const MAIL_REGEX = '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $address;

    /**
     * AddressRecipient constructor.
     *
     * @param string $address - Can be a full email address like "Jon Snow <jonsnow@winterfel.north>" or raw email
     * address like "jonsnow@winterfel.north"
     * @throws InvalidEmailAddressException
     */
    public function __construct(string $address)
    {
        [$this->email, $this->name, $this->address] = $this->splitAddress($address);
    }

    /**
     *
     * @param string $address
     * @return array
     * @throws InvalidEmailAddressException
     */
    protected function splitAddress(string $address): array
    {

        $address = trim($address);

        try {
            // split
            if (preg_match('/^([^<]+)\<([^>]+)\>$/', $address, $matches)) {
                $email = trim($matches[2]);
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name); // remove double spaces
            } else {
                $email = $address;
                $name = null;
            }
        }
        catch (\Exception $e) {
            LoggerFacade::error($address . " is not a valid email address");
            throw new InvalidEmailAddressException();
        }

        if (!preg_match(static::MAIL_REGEX, $email)) {
            throw new InvalidEmailAddressException();
        }

        return [
            $email,
            "\"$name\"",
            isset($name) ? '"' . utf8_encode($name) . "\" <$email>" : $email];

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
}