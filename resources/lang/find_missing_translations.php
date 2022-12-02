<?php

const BASE_LANG = 'en_US';

// find base lang files
$baseLangDir = __DIR__ . '/' . BASE_LANG;
$baseFiles = array_map(function($item) use ($baseLangDir) {
    $segments = explode('/', $item);
    return array_pop($segments);
}, glob("$baseLangDir/*.php"));


foreach (glob(__DIR__ . '/*') as $entry) {

    // skip files
    if (!is_dir($entry)) {
        continue;
    }

    $segments = explode('/', $entry);
    $dir = array_pop($segments);

    // skip base lang dir
    if ($dir === BASE_LANG) {
        continue;
    }

    // find the files that don't exists on the current language dir
    $presentFiles = array_map(function($item) {
        $segments = explode('/', $item);
        return array_pop($segments);
    }, glob("$entry/*.php"));

    $missingFiles = array_diff($baseFiles, $presentFiles);

    // create missing files
    createMissingFiles($entry, $missingFiles);

    // check
    foreach ($baseFiles as $file) {
        fillDiffs($file, $baseLangDir, $entry);
    }

}

function createMissingFiles(string $dir, array $missingFiles)
{
    foreach ($missingFiles as $file) {
        $filePath = "$dir/$file";
        $content = <<<php
<?php
return [

];
php;

        file_put_contents($filePath, $content);
    }
}

function fillDiffs($file, $baseDir, $targetDir)
{
    $baseFile = "$baseDir/$file";
    $baseArray = require $baseFile;
    $targetFile = "$targetDir/$file";
    $targetArray = require $targetFile;
    $missingKeys = array_diff_key($baseArray, $targetArray);

    // get the current target contents
    $targetContents = trim(file_get_contents($targetFile));

    // remove the array close `];` and any space that prepends it
    $targetContents = preg_replace('/\s*\]\s*;?$/', '', $targetContents);

    // put a comma in the end of the contents if it don't exists
    $lastChar = substr($targetContents, -1);
    if ($lastChar !== ',' && $lastChar !== '[') {
        $targetContents .= ',';
    }

    // add the missing keys
    foreach ($missingKeys as $key => $value) {
        $targetContents .= "\n    '$key' => \"\", // MISSING: \"$value\"";
    }

    // put the `];` back in the end
    $targetContents .= "\n];\n";

    file_put_contents($targetFile, $targetContents);

}
