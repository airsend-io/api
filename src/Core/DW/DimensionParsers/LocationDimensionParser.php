<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CodeLathe\Core\Utility\Directories;
use MaxMind\Db\Reader;

class LocationDimensionParser
{

    /**
     * @var string
     */
    protected $ip;

    public function __construct(string $ip)
    {
        $this->ip = $ip;
    }

    /**
     * Parses and save the dimension data to the database.
     * Returns the id of the inserted/found record
     *
     * @return array
     */
    public function parse(): array
    {

        $data = [];

        $geoipReader = new Reader(Directories::resources('geoip/database.mmdb'));

        $geoipData = $geoipReader->get($this->ip);

        $data['continent'] = $geoipData['continent']['names']['en'] ?? null;
        $data['country'] = $geoipData['country']['names']['en'] ?? null;
        $data['country_code'] = $geoipData['country']['iso_code'] ?? null;
        $data['country_area'] = $geoipData['subdivisions'][0]['names']['en'] ?? null;
        $data['city'] = $geoipData['city']['names']['en'] ?? null;

        if (!empty($geoipData['location']['latitude']) && !empty($geoipData['location']['longitude'])) {
            $data['location'] = [
                'lat' => (int)$geoipData['location']['latitude'],
                'lon' => (int)$geoipData['location']['longitude'],
            ];
        }

        $data['timezone'] = $geoipData['location']['time_zone'] ?? null;
        if (isset($geoipData['location']['latitude'])) {
            $data['hemisphere'] = $geoipData['location']['latitude'] < 0 ? 'south' : 'north';
            $absLongitude = abs($geoipData['location']['longitude']);
            if ($absLongitude < 23.45) {
                $data['climate_zone'] = 'tropical';
            } elseif($absLongitude < 66.55) {
                $data['climate_zone'] = 'subtropical';
            } else {
                $data['climate_zone'] = 'polar';
            }
        }

        $geoipReader->close();

        return $data;
    }

}