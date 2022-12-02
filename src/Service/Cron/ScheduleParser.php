<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Cron;

use CodeLathe\Service\Cron\Exceptions\InvalidSchedule;
use Exception;

/**
 * Class ScheduleParser
 *
 * This class converts a cron style schedule on a schedule object
 *
 * @package CodeLathe\Service\Cron
 */
class ScheduleParser
{

    /**
     * @param string $scheduleString
     * @return Schedule
     * @throws InvalidSchedule
     */
    public function parseSchedule(string $scheduleString): Schedule
    {
        // trim the schedule to avoid problems
        $scheduleString = trim($scheduleString);

        // check the basic format (5 sequences of chars separated by spaces)
        if (!preg_match('/^(?:[^\s]+\s+){4}[^\s]+$/', $scheduleString)) {
            throw new InvalidSchedule("Invalid schedule string $scheduleString");
        }

        // split the schedule
        [$minutes, $hours, $daysOfMonth, $months, $daysOfWeek] = preg_split('/\s+/', $scheduleString);

        return new Schedule(
            $this->parseScheduleSegment($minutes, 0, 59),
            $this->parseScheduleSegment($hours, 0, 23),
            $this->parseScheduleSegment($daysOfMonth, 1, 31),
            $this->parseScheduleSegment($months, 1, 12),
            $this->parseScheduleSegment($daysOfWeek, 0, 6)
        );
    }

    /**
     * @param string $schedule
     * @param int $first
     * @param int $last
     * @return int[]
     * @throws InvalidSchedule
     */
    protected function parseScheduleSegment(string $schedule, int $first, int $last): array
    {

        // remove any whitespace
        $schedule = preg_replace('/\s/', '', $schedule);

        // explode any possible *
        $schedule = preg_replace_callback('/\*/', function() use ($first, $last) {
            return implode(',', range($first, $last));
        }, $schedule);

        // explode ranges (defined with -)
        $schedule = preg_replace_callback('/(\d+)-(\d+)/', function($matches) {
            return implode(',', range($matches[1], $matches[2]));
        }, $schedule);

        // check if there is a slash, and parse the divisor
        if (preg_match('#^([\d,]+)/(\d+)$#', $schedule, $matches)) {
            $times = explode(',', $matches[1]);
            $divisor = $matches[2];
            $output = array_filter($times, function ($item) use ($divisor) {
                return !($item % $divisor);
            });
        } else {
            $output = explode(',', $schedule);
        }

        // cast all times to int
        $output = array_map(function($item) {
            return (int) $item;
        }, $output);

        // make them unique
        $output = array_unique($output);

        // put everything in order
        sort($output);

        // check the range
        if (count($diff = array_diff($output, range($first, $last)))) {
            $diff = implode(',', $diff);
            throw new InvalidSchedule("Schedule values ($diff) out of the supported range ($first - $last)");
        }

        // return
        return $output;
    }

}