<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;


use CodeLathe\Application\ConfigRegistry;

class NumVerify
{
    private $config;

    public function __construct(ConfigRegistry $config)
    {
        $this->config = $config;
    }

    /**
     * Validate the number with Numverify
     *
     * @param string $phone
     * @return array|null
     */
    public function validate(string $phone) : ?array
    {
        $url = $this->config->get('/num_verify/url');
        $accessKey = $this->config->get('/num_verify/key');

        // Initialize CURL:
        $ch = curl_init($url . "?access_key=" . $accessKey . '&number=' .$phone.'');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Store the data:
        $json = curl_exec($ch);
        curl_close($ch);

        // Decode JSON response:
        return json_decode($json, true);
    }

    /**
     * Check for phone validity and format the phone number
     *
     * @param string $input
     * @return array
     */
    public function checkAndFormatPhone(string $input) : array
    {
        if (empty($input) || preg_match('/^[0-9\-\(\)\/\+\s]*$/', $input, $matches) == false) {
            return  array('valid' => false, 'phone' => '', 'msg' => "Phone number is not valid");
        }

        $validation = $this->validate($input);
        if (!empty($validation) && is_array($validation) && isset($validation['valid'])) {
            if ($validation['valid']) {
                return array('valid' => true, 'phone' =>  $validation['international_format'], 'msg' => '');
            }
            else {
                return array('valid' => false, 'phone' => '', 'msg' => 'Phone number is not valid');
            }
        }
        else {
            return array('valid' => false, 'phone' => '', 'msg' => 'Unable to validate phone number');
        }

    }
}