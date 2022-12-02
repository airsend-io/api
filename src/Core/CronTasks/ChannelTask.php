<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\CronTasks;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Service\Cron\AbstractCronTask;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;

/**
 * Describes a Service instance.
 */
class ChannelTask extends AbstractCronTask
{

    /**
     * @var LoggerService
     */
    protected $logger;

    protected $operations;

    public function __construct(LoggerService $logger, ChannelOperations $operations)
    {
        $this->logger = $logger;
        $this->operations = $operations;
    }

    /**
     * Entrypoint for the execution logic of the cron job
     *
     * @return mixed
     */
    public function run(): void
    {
        $this->logger->debug("DAILY CHANNEL CRON");
        $this->operations->onCron();
    }

    /**
     * Returns a name that identifies the task
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Channel Cron Task';
    }
}