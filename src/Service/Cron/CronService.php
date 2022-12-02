<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Cron;

use CodeLathe\Service\Cron\Exceptions\InvalidCronTask;
use CodeLathe\Service\Cron\Exceptions\InvalidSchedule;
use CodeLathe\Service\ServiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Describes a Service instance.
 */
class CronService implements ServiceInterface, CronServiceInterface
{

    /**
     * @var AbstractCronTask[]
     */
    protected $tasks;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CronService constructor.
     * @param array $tasks
     * @param LoggerInterface $logger
     * @throws InvalidCronTask
     * @throws InvalidSchedule
     */
    public function __construct(array $tasks, LoggerInterface $logger)
    {

        $this->logger = $logger;

        foreach ($tasks as $taskConfig) {

            if (!isset($taskConfig['task'])) {
                throw new InvalidCronTask("Invalid config for the cron tasks.");
            }

            $task = $taskConfig['task'];
            $schedule = $taskConfig['schedule'] ?? '* * * * *';

            if (!($task instanceof AbstractCronTask)) {
                throw new InvalidCronTask("Invalid cron task `" . get_class($task) . "`. It must extend " . AbstractCronTask::class);
            }
            $task->setSchedule($schedule);
            $this->tasks[] = $task;
        }
    }

    /**
     * return name of the service
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return static::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return 'Service to handle scheduled tasks';
    }

    /**
     * Dispatch the cron jobs, based on the current time an the schedules
     *
     * @return string[]
     */
    public function dispatch(): array
    {
        $this->logger->debug('Dispatching cron jobs...');
        $executedTasks = [];

        // freezes the starting timestamp of the cron job
        $startTS = time();

        foreach ($this->tasks as $task) {
            if ($task->runScheduled($startTS)) {
                $taskName = $task->getName();
                $now = date("Y-m-d H:i:s");
                $this->logger->debug("Executed task {$taskName} ({$task->getScheduleString()}/$now)");
                $executedTasks[] = $taskName;
            } else {
                $this->logger->debug("Task {$task->getName()} ignored ({$task->getScheduleString()})");
            }
        }
        return $executedTasks;
    }
}