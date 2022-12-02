<?php

use CodeLathe\Core\Utility\Directories;

//$rawList = file_get_contents('https://www.unicode.org/Public/UCD/latest/ucd/emoji/emoji-data.txt');
$rawList = file_get_contents('https://www.unicode.org/Public/emoji/latest/emoji-sequences.txt');

$lines = explode("\n", $rawList);

$ranges = [];
foreach ($lines as $line) {
    $line = trim($line);

    // ignore comment lines
    if (substr($line, 0, 1) === '#') {
        continue;
    }

    // ignore empty lines
    if (empty($line)) {
        continue;
    }

    // remove comments
    $line = trim(preg_replace('/#.*$/', '', $line));

    // ignore lines that don't match the pattern
    if (!preg_match('/^([0-9A-F]+)(?:\.{2}([0-9A-F]+))?\s+;\s+Basic_Emoji/', $line, $matches)) {
        continue;
    }

    $from = (int) hexdec($matches[1]);
    $to = (int) hexdec($matches[2] ?? $matches[1]);

    // skip if to is bigger than from (should never happen)
    if ($from > $to) {
        continue;
    }

    // define a low threshold
    if ($from < 0x2000) {
        continue;
    }

    $ranges[] = compact('from', 'to');

}

$json = json_encode($ranges, JSON_PRETTY_PRINT);

file_put_contents(__DIR__ . '/emoji_ranges.json', $json);