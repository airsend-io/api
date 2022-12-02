<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

abstract class Database
{

    /**
     * Sometimes you need to make a DataStore class build an object, from a database resultset that have data from
     * multiple tables. I.e. let's say that your DataStore class brings all notifications, joined with their channels.
     * You can prefix the channel columns, with `channel.`, and use this method to normalize the record to build a
     * Channel object.
     * @param string $prefix
     * @param array $record
     * @return array
     */
    public static function normalizeRecordForDataStore(string $prefix, array $record): array
    {
        $prefix = preg_quote($prefix, '/');
        if (isset($record["$prefix.id"])) {
            $original = $record;
            $record = [];
            foreach ($original as $key => $value) {
                if (preg_match("/^{$prefix}\.(.+)$/", $key, $matches)) {
                    $record[$matches[1]] = $value;
                }
            }
        }
        return $record;
    }
}