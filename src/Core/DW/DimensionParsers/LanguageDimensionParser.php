<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

class LanguageDimensionParser
{

    /**
     * @var string|null
     */
    protected $langHeader;

    public function __construct(array $headers)
    {
        $this->langHeader = $headers['Accept-Language'] ?? null;
    }

    /**
     * Parses and save the dimension data to the database.
     * Returns the id of the inserted/found record
     *
     * @return array
     */
    public function parse(): array
    {

        // find all the languages on the header
        $langs = explode(',', $this->langHeader ?? '');
        $langs = array_map('trim', $langs);

        // split the languages and the weights (also normalize the languages)
        $langs = array_map(function ($item) {
            $arr = explode(';', $item);

            $lang = str_replace('_', '-', strtolower(trim($arr[0])));

            if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $lang)) {
                return null;
            }

            $output = [
                'lang' => $lang,
                'weight' => 9999999
            ];
            if (preg_match('/^q=([0-9]+(?:\.[0-9]+)?)$/', $arr[1] ?? '', $matches)) {
                $output['weight'] = (float) $matches[1];
            }

            return $output;
        }, $langs);

        // remove invalid languages
        $langs = array_filter($langs, function ($item) {
            return $item !== null;
        });

        // sort langs by weight (desc)
        usort($langs, function (array $a, array $b) {
            if ($a['weight'] == $b['weight']) {
                return 0;
            }
            return ($a['weight'] < $b['weight']) ? 1 : -1;
        });

        // get the first element (highest weight)
        $lang = array_shift($langs);
        $lang = $lang['lang'] ?? null;

        if ($lang === null) {
            return [];
        }

        $output = [
            'complete_lang' => $lang,
        ];

        if (preg_match('/^[a-z]{2}/', $lang, $matches)) {
            $output['lang_code'] = $matches[0] ?? '';
        }

        return $output;

    }

}