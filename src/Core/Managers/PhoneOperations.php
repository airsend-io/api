<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Objects\Phone;
use CodeLathe\Core\Utility\NumVerify;
use Psr\Log\LoggerInterface;

class PhoneOperations
{

    private $logger;

    private $dataController;

    protected $numVerify;

    /**
     * AuthManager constructor.
     * @param LoggerInterface $logger
     * @param DataController $dataController
     */
    public function __construct(
        LoggerInterface $logger,
        DataController $dataController,
        NumVerify $numVerify
    )
    {
        $this->logger = $logger;
        $this->dataController = $dataController;
        $this->numVerify = $numVerify;
    }


    public function getValidatedPhone(string $number) : ?Phone
    {
        if (empty($number)) {
            return null;
        }
        // only take numbers
        $number = preg_replace('/[^0-9]/', '', $number);
        $phone = $this->dataController->getPhone($number);
        if (empty($phone))
        {
            $data = $this->numVerify->validate($number);
            if (empty($data['number'])) {
                $data['number'] = $number;
                $data['valid'] = true; // always keep it valid
            }
            $phone = Phone::create($data);
            $this->dataController->createPhone($phone);
        }
        return ($phone->isValid() ? $phone : null);
    }


}