<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Cron;

use CodeLathe\Service\Cron\Exceptions\InvalidSchedule;

/**
 * Describes a Service instance.
 */
abstract class AbstractCronTask
{

    /**
     * @var Schedule
     */
    protected $schedule;

    /**
     * @var string
     */
    protected $scheduleString;

    /**
     * @param string $schedule
     * @throws InvalidSchedule
     */
    public function setSchedule(string $schedule)
    {
        $this->scheduleString = $schedule;
        $scheduleParser = new ScheduleParser();
        $this->schedule = $scheduleParser->parseSchedule($schedule);
    }

    /**
     * @return string
     */
    public function getScheduleString(): string
    {
        return $this->scheduleString;
    }

    /**
     * Check if the task should run now (based on the schedule, and runs it)
     * It returns true if the task was run, and false if not
     *
     * @param int $startTS
     * @return bool
     */
    public function runScheduled(int $startTS): bool
    {
        if ($this->schedule->checkSchedule($startTS)) {
           $this->run();
           return true;
        }
        return false;
    }

    /**
     * Returns a name that identifies the task (for logging purposes)
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Entrypoint for the execution logic of the cron job
     *
     * @return mixed
     */
    abstract public function run();

}