<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

use Carbon\CarbonImmutable;

class TimeDimensionParser
{

    /**
     * @var CarbonImmutable
     */
    protected $serverTime;

    /**
     * @var CarbonImmutable|null
     */
    protected $localTime;

    public function __construct(CarbonImmutable $date, ?string $timezone)
    {
        $this->serverTime = $date;
        $this->localTime = $timezone !== null ? $date->setTimezone($timezone) : null;
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

        $data['server_hour'] = $this->serverTime->format('ga');
        $data['server_period'] = $this->findPeriod($this->serverTime->hour);

        if ($this->localTime instanceof CarbonImmutable) {
            $data['local_hour'] = $this->localTime->format('ga');
            $data['local_period'] = $this->findPeriod($this->localTime->hour);
        }

        return $data;
    }

    protected function findPeriod(int $hour): string
    {

        if ($hour < 6) {
            return 'Night (12-5am)';
        } elseif ($hour < 12) {
            return 'Morning (6-11am)';
        } elseif ($hour < 18) {
            return 'Afternoon (12-5pm)';
        } else {
            return 'Evening (6-11pm)';
        }

    }

}