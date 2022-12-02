<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer;

interface RecipientInterface
{

    /**
     * Name of the recipient. The name is optional, so it can return null.
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Recipient raw email address. Pattern: jonsnow@winterfel.com
     * @return string
     */
    public function getEmail(): string;

    /**
     * Complete email address. Pattern: Jon Snow <jonsnow@winterfel.com>
     * If the name is null, this method will just return the raw email address.
     * @return string
     */
    public function getAddress(): string;
}