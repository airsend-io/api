<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use WhichBrowser\Parser;

class DeviceDimensionParser
{

    /**
     * @var array
     */
    protected $headers;

    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Parses and save the dimension data to the database.
     * Returns the id of the inserted/found record
     *
     * @return array
     */
    public function parse(): array
    {
        return array_merge($this->parseClientHeaders(), $this->parseBrowser(), $this->parseMobileApp());
    }

    protected function parseClientHeaders(): array
    {
        $data = [];
        $data['client'] = $this->headers['X-Airsend-Client-Type'] ?? null;
        $data['client_version'] = $this->headers['X-Airsend-Client-Version'] ?? null;
        return $data;

    }

    protected function parseBrowser(): array
    {
        $browserInfo = new Parser($this->headers);

        $data = [];
        $data['os'] = $browserInfo->os->getName();
        $data['os_version'] = $browserInfo->os->getVersion();
        $data['browser'] = $browserInfo->browser->getName();
        $data['browser_version'] = $browserInfo->browser->getVersion();
        return $data;
    }

    protected function parseMobileApp(): array
    {
        $userAgent = $this->headers['User-Agent'];

        $data = [];

        if (preg_match('/^(android|ios)\s+([0-9.]+)$/i', trim($userAgent), $matches)) {
            $data['os'] = $matches[1];
            $data['os_version'] = $matches[2];
        }

        return $data;
    }

}