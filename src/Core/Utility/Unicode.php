<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

abstract class Unicode
{
    public static function isEmoji(string $emojiValue, ?array $whitelist = null): bool
    {

        // if a whitelist is provided, consider it
        if (!empty($whitelist)) {
            return in_array($emojiValue, $whitelist);
        }

        $rangesFile = Directories::resources('emoji/emoji_ranges.json');

        // if the file don't exists, return false
        if (!file_exists($rangesFile)) {
            return false;
        }

        // grab the acceptable ranges
        $ranges = json_decode(file_get_contents($rangesFile), true);

        foreach ($ranges as $range) {

            // should never happen
            if (!isset($range['from']) || !isset($range['to'])) {
                continue;
            }

            // check if the emoji code is in the range
            $emojiCode = mb_ord($emojiValue, 'utf-8');
            if ($emojiCode >= $range['from'] && $emojiCode <= $range['to']) {

                // accept it
                return true;
            }
        }

        // value don't fit to any of the acceptable ranges, return false
        return false;
    }
}