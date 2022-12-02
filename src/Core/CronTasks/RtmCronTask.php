<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\CronTasks;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\Realtime\RtmOperations;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\Message;
use CodeLathe\Service\Cron\AbstractCronTask;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;

/**
 * Describes a Service instance.
 */
class RtmCronTask extends AbstractCronTask
{

    /**
     * @var LoggerService
     */
    protected $logger;
    /**
     * @var RtmOperations
     */
    private $operations;


    /**
     * RtmCronTask constructor.
     * @param LoggerService $logger
     * @param RtmOperations $operations
     */
    public function __construct(LoggerService $logger, RtmOperations $operations)
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
        $this->logger->debug("DAILY NODE CRON");
        $this->operations->onCron();
    }

    /**
     * Returns a name that identifies the task
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Node Cron Task';
    }
}