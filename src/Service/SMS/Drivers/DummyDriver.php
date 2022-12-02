<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\SMS\Drivers;

use CodeLathe\Service\SMS\SMSDriverInterface;

/**
 * This interface represents the sms delivery sender driver used to send the sms.
 */
class DummyDriver implements SMSDriverInterface
{

    /**
     * @param string $destinationNumber
     * @param string $message
     */
    public function send(string $destinationNumber, string $message)
    {
        $fileName = 'sms_' . preg_replace('/[^0-9]/', '', $destinationNumber) . '_' . date('YmdHis') . '_' . uniqid() . '.txt';
        $dir = dirname(__DIR__, 4) . '/scratch/sentsms';

        file_put_contents("$dir/$fileName", $message);
    }
}