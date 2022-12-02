<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Cron\Exceptions;

use CodeLathe\Service\ServiceException;
use Exception;

/**
 * Class ScheduleParser
 *
 * This class converts a cron style schedule on a schedule object
 *
 * @package CodeLathe\Service\Cron
 */
class InvalidSchedule extends ServiceException
{

}