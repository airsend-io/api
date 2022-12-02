<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

abstract class Markdown
{
    public static function parseMessageForEmail(string $messageText): string
    {
        // replace mentions
        $messageText = preg_replace_callback('/\[([^]]+)]\(user:\/\/[^)]+\)/i', function($match) {

            // return the replacement with the highlight
            $output = '<font color="#0097C0"><a href="#" bgcolor="#0097C0" style="background:#0097C0; color: #fff;padding: 3px 5px; font-weight: bold; text-decoration: none; border-radius: 3px">';
            $output.= $match[1];
            $output.= '</a></font>';
            return $output;
        }, $messageText);

        // other actions...

        return $messageText;
    }
}