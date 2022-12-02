<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Mailer\Drivers;

use CodeLathe\Service\Mailer\RecipientInterface;

trait ReduceRecipientsTrait
{
    /**
     * @param RecipientInterface[] $recipients
     * @return mixed
     */
    protected function reduceRecipients(array $recipients)
    {
        return array_reduce($recipients, function ($carry, RecipientInterface $current) {
            return $carry . $current->getAddress();
        }, '');
    }
}