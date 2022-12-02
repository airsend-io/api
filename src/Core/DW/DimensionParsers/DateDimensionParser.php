<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\DW\DimensionParsers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class DateDimensionParser
{

    /**
     * @var CarbonImmutable
     */
    protected $date;

    /**
     * @var string|null
     */
    protected $hemisphere;

    public function __construct(CarbonImmutable $date, ?string $hemisphere = null)
    {
        $this->date = $date;
        $this->hemisphere = $hemisphere;
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

        $data['date'] = $this->date->format('Y-m-d');
        $data['year'] = (int)$this->date->format('Y');
        $data['year_quarter'] = (int)$this->date->quarter;

        // season handling
        $marchEquinox = 80; // day of the year
        $julySolstice = 172; // day of the year
        $septemberEquinox = 266; // day of the year
        $decemberSolstice = 355; // day of the year

        if ($this->date->dayOfYear < $marchEquinox || $this->date->dayOfYear >= $decemberSolstice) {
            $data['server_season'] = 'winter';
            if ($this->hemisphere !== null) {
                $data['local_season'] = $this->hemisphere === 'north' ? 'winter' : 'summer';
            }
        } elseif ($this->date->dayOfYear >= $marchEquinox || $this->date->dayOfYear < $julySolstice) {
            $data['server_season'] = 'spring';
            if ($this->hemisphere !== null) {
                $data['local_season'] = $this->hemisphere === 'north' ? 'spring' : 'fall';
            }
        } elseif ($this->date->dayOfYear >= $julySolstice || $this->date->dayOfYear < $septemberEquinox) {
            $data['server_season'] = 'summer';
            if ($this->hemisphere !== null) {
                $data['local_season'] = $this->hemisphere === 'north' ? 'summer' : 'winter';
            }
        } elseif ($this->date->dayOfYear >= $septemberEquinox || $this->date->dayOfYear < $decemberSolstice) {
            $data['server_season'] = 'fall';
            if ($this->hemisphere !== null) {
                $data['local_season'] = $this->hemisphere === 'north' ? 'fall' : 'spring';
            }
        }

        $data['month'] = (int)$this->date->month;
        $data['year_week'] = (int)$this->date->week;

        $weekDays = [
            1 => 'Sun',
            2 => 'Mon',
            3 => 'Tue',
            4 => 'Wed',
            5 => 'Thu',
            6 => 'Fri',
            7 => 'Sat',
        ];
        $data['week_day'] = $weekDays[(int)$this->date->weekday()] ?? null;

        $data['month_week'] = (int) ceil(($this->date->day + $this->date->firstOfMonth()->dayOfWeek)/7);

        $data['week_monday'] = $this->date->startOfWeek(1)->format('Y-m-d');
        $data['week_friday'] = $this->date->endOfWeek(5)->format('Y-m-d');
        $data['month_day'] = $this->date->day;

        return $data;
    }

}