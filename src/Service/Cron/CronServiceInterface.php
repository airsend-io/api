<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Cron;

/**
 * Describes a Service instance.
 */
interface CronServiceInterface
{
    /**
     * Dispatch the cron jobs, based on the current time an the schedules
     *
     * @return string[]
     */
    public function dispatch(): array;

}