<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Cron;

use DateTime;
use Throwable;

/**
 * Class Schedule
 *
 * Represents a time schedule
 *
 * @package CodeLathe\Service\Cron
 */
class Schedule
{

    /**
     * @var int[]
     */
    protected $minutes = [];

    /**
     * @var int[]
     */
    protected $hours = [];

    /**
     * @var int[]
     */
    protected $daysOfMonth = [];

    /**
     * @var int[]
     */
    protected $months = [];

    /**
     * @var int[]
     */
    protected $daysOfWeek = [];

    /**
     * Schedule constructor.
     * @param int[] $minutes
     * @param int[] $hours
     * @param int[] $daysOfMonth
     * @param int[] $months
     * @param int[] $daysOfWeek
     */
    public function __construct(array $minutes, array $hours, array $daysOfMonth, array $months, array $daysOfWeek)
    {
        $this->minutes = $minutes;
        $this->hours = $hours;
        $this->daysOfMonth = $daysOfMonth;
        $this->months = $months;
        $this->daysOfWeek = $daysOfWeek;
    }

    /**
     * Returns if a timestamp matches the schedule.
     *
     * @param int $timestamp
     * @return bool
     */
    public function checkSchedule(int $timestamp): bool
    {
        try {
            $date = new DateTime("@$timestamp");
        } catch (Throwable $e) {
            return false;
        }

        // minutes
        if (array_search((int) $date->format('i'), $this->minutes) === false) {
            return false;
        }

        // hours
        if (array_search((int) $date->format('G'), $this->hours) === false) {
            return false;
        }

        // days of month
        if (array_search((int) $date->format('j'), $this->daysOfMonth) === false) {
            return false;
        }

        // months
        if (array_search((int) $date->format('n'), $this->months) === false) {
            return false;
        }

        // days of week
        if (array_search((int) $date->format('w'), $this->daysOfWeek) === false) {
            return false;
        }

        return true;
    }
}