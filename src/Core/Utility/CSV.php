<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

abstract class CSV
{
    public static function array2csv(array $data, bool $includeHeaders = true, $commaSeparator = ',', $lineSeparator = "\r\n"): string
    {

        if (empty($data)) {
            return '';
        }

        $header = '';
        $lines = [];
        foreach ($data as $row) {
            if (empty($header) && $includeHeaders) {
                $header = implode($commaSeparator, array_keys($row));
            }

            $row = array_map(function ($item) {
                return "\"$item\"";
            }, $row);

            $lines[] = implode($commaSeparator, $row);
        }

        $header .= !empty($header) ? $lineSeparator : '';
        return $header . implode($lineSeparator, $lines);

    }
}