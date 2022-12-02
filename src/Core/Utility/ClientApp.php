<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

abstract class ClientApp
{
    public static function identifyFromUserAgent(string $userAgent): ?string
    {

        // maps the regex that identify user agents to the app identifier (android or ios)
        // in the future we can add additional regex/apps if necessary
        $map = [
            '/^airsend\/[0-9.]+\s\(com\.codelathe\.airsend;\sbuild:[0-9]+;\sios\s[0-9.]+\)/i' => 'ios',
            '/^android$/i' => 'android',
            '/Electron\/\d\.\d/' => 'desktop',
        ];

        foreach ($map as $regex => $app) {
            if (preg_match($regex, $userAgent)) {
                return $app;
            }
        }
        return null;
    }
}