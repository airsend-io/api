<?php declare(strict_types=1);

/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

/**
 * This file lists all the scheduled tasks, and the frequency of each of them.
 */

use CodeLathe\Core\CronTasks\ActionTask;
use CodeLathe\Core\CronTasks\ChannelTask;
use CodeLathe\Core\CronTasks\DailyReportTask;
use CodeLathe\Core\CronTasks\ExternalNotificationTask;
use CodeLathe\Core\CronTasks\NodeCronTask;
use CodeLathe\Core\CronTasks\RtmCronTask;
use CodeLathe\Core\Utility\ContainerFacade;

return [
    [
        'schedule' => '*/60 * * * *', // ... Every hour
        'task' => ContainerFacade::get(DailyReportTask::class)
    ],
    [
        'schedule' => '0 22 * * *', // ... Every day at 10 PM
        'task' => ContainerFacade::get(NodeCronTask::class)
    ],
    [
        'schedule' => '0 1 * * *', // ... Every day at 1 AM
        'task' => ContainerFacade::get(ChannelTask::class)
    ],
    [
        'schedule' => getenv('ACTION_CRON_FREQUENCY') ?: '*/5 * * * *', // ... Every 5 minutes
        'task' => ContainerFacade::get(ActionTask::class)
    ],
    [
        'schedule' => getenv('RTM_CRON_FREQUENCY') ?: '*/10 * * * *', // ... Every 10 minutes
        'task' => ContainerFacade::get(RtmCronTask::class)
    ],
    [
        'schedule' => getenv('EXTERNAL_DIGEST_FREQUENCY') ?: '* * * * *',
        'task' => ContainerFacade::get(ExternalNotificationTask::class)
    ],

];