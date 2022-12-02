<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Wiki;

use CodeLathe\Core\Utility\StringUtility;
use ParsedownMath;

require_once 'ParsedownMath.php';

class ASParsedown  extends ParsedownMath
{
    private $baseImagePath = '';
    private $authHeader = '';

    /**
     * FCParsedown constructor.
     * @param string $options
     */
    public function __construct($options = '')
    {
        parent::__construct($options);
    }

    public function setAuthHeader(string $authHeader)
    {
        $this->authHeader = $authHeader;
    }

    protected function urlIsLocal($url)
    {
        $parsed = parse_url($url);

        if (isset($parsed["scheme"]) || isset($parsed["host"]) || isset($parsed["query"])  || isset($parsed["fragment"]) ) {
            return false;
        }

        if (isset($parsed["scheme"]) && (strlen($parsed["host"]) > 0)) {
            return false;
        }

        if (StringUtility::startsWith($url, '/' ))
            return false;


        // @TODO check the host

        return true;
    }

    protected function inlineImage($excerpt)
    {
        $image = parent::inlineImage($excerpt);

        if ( ! isset($image))
        {
            return null;
        }

        $image['element']['attributes']['src'] = $this->baseImagePath . $image['element']['attributes']['src'];

        return $image;
    }

    protected function inlineLink($excerpt)
    {
        $link = parent::inlineLink($excerpt);

        if ( ! isset($link))
        {
            return null;
        }

        // ... Relative URLs and other absolute URLs will not get the tokens
        if ($this->urlIsLocal($link['element']['attributes']['href']))
        {
            $link['element']['attributes']['href'] = $this->baseImagePath . $link['element']['attributes']['href'].'?token='.$this->authHeader;
        }

        return $link;
    }

}