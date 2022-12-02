<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS;

/**
 * This interface represents the sms delivery sender driver used to send the sms.
 */
interface SMSDriverInterface
{
    public function send(string $destinationNumber, string $message);
}