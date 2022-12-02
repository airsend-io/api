<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\CronTasks;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\UserDataStore;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Convert;
use CodeLathe\Service\Cron\AbstractCronTask;
use CodeLathe\Service\Logger\LoggerService;
use CodeLathe\Service\Mailer\Exceptions\InvalidEmailAddressException;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;
use CodeLathe\Service\Storage\DB\StorageServiceDB;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Describes a Service instance.
 */
class DailyReportTask extends AbstractCronTask
{

    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @var ServiceRegistryInterface
     */
    protected $config;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var StorageServiceDB
     */
    protected $storageDB;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * DailyReportTask constructor.
     * @param LoggerService $logger
     * @param ServiceRegistryInterface $config
     * @param MailerServiceInterface $mailer
     * @param DataController $dataController
     * @param StorageServiceDB $storageDB
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(LoggerService $logger,
                                ServiceRegistryInterface $config,
                                MailerServiceInterface $mailer,
                                DataController $dataController,
                                StorageServiceDB $storageDB,
                                CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->mailer = $mailer;
        $this->dataController = $dataController;
        $this->storageDB = $storageDB;
        $this->cache = $cache;
    }

    /**
     * Entrypoint for the execution logic of the cron job
     *
     * @return mixed
     * @throws InvalidEmailAddressException
     * @throws \CodeLathe\Core\Exception\DatabaseException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function run(): void
    {
        $this->logger->info("EXECUTING DAILY REPORT...");

        $emailAddress = $this->config->get('/app/admin/stats_email');
        if (!$emailAddress) {
            $this->logger->info('NO EMAIL ADDRESS DEFINED TO RECEIVE THE REPORT. SKIPPING...');
            return;
        }


        // Check if we have already sent the report today. If so, skip
        $today = date("Y-m-d");
        $dailyReportKey = 'cron.last_daily_report';
        $cacheItemDR = $this->cache->getItem($dailyReportKey);
        if ($cacheItemDR->isHit()) {
            $lastReport = $cacheItemDR->get();
            if ($today == $lastReport) {
                return;
            }
        }

        // Save today and continue processing
        $cacheItemDR->set($today);
        $this->cache->save($cacheItemDR);

        // ---------------------------

        $this->logger->info("PREPARING THE REPORT...");

        $subject = 'Daily Report - ' . date('M jS, Y');

        // get users and messages count using the dashboard method
        $dashboardStats = $this->dataController->getDashboardStats();

        $interval = 60 * 60 * 24; // last 24 hours
        $newMessagesCount = $this->dataController->countNewMessages($interval);
        $newAccounts = $this->dataController->getNewUsers($interval);
        $newAccounts = array_map(function ($item) {
            $status = 'NOTACTIVE';
            if ($item['account_status'] == User::ACCOUNT_STATUS_ACTIVE)
                $status = 'ACTIVE';
            else if ($item['account_status'] == User::ACCOUNT_STATUS_PENDING_FINALIZE)
                $status = 'PENDING FINAL';
            else if ($item['account_status'] == User::ACCOUNT_STATUS_PENDING_VERIFICATION)
                $status = 'PENDING VERIFICATION';
            return "{$item['email']} ($status)";
        }, $newAccounts);

        $newAccountsWithChannels = $this->dataController->getNewUsersWithChannels($interval);
        $newAccountsWithChannels = array_map(function ($item) {
            return "{$item['email']} ({$item['channels_count']} channels)";
        }, $newAccountsWithChannels);

        $newChannels = $this->dataController->getNewChannels($interval);
        $newChannels = array_map(function($item) {
            return $item['channel_name'];
        }, $newChannels);

        $filesInfo = $this->storageDB->getDashBoardStats($interval);

        $engagedUserCount = $dashboardStats['engaged_users'];

        $deletedAccountsFeedbacks = [];
        $cacheKey = 'selfdelete.feedbacks';
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $deletedAccountsFeedbacks = json_decode($cacheItem->get());
            $this->cache->deleteItem($cacheKey);
        }

        // ----------------------

        $this->logger->info("SENDING THE REPORT...");
        $message = $this->mailer->createMessage($emailAddress)
            ->subject("[AirSend] $subject")
            ->from("noreply", "AirSend")
            ->body('daily_report_template', [
                'subject' => $subject,
                'header' => $subject,
                'totalAccountsCount' => $dashboardStats['users'],
                'totalChannelsCount' => $dashboardStats['channels'],
                'totalMessagesCount' => $dashboardStats['messages'],
                'totalFilesCount' => $filesInfo['total_count'],
                'totalFilesSize' => Convert::toSizeUnit((int) $filesInfo['total_size']),
                'newMessagesCount' => $newMessagesCount,
                'newFilesCount' => $filesInfo['new_count'] . ' (' . Convert::toSizeUnit((int) $filesInfo['new_size']) . ')',
                'engagedUserCount' => $engagedUserCount,
                'newAccountsCount' => count($newAccounts),
                'newAccounts' => $newAccounts,
                'newAccountsWithChannels' => $newAccountsWithChannels,
                'newAccountsWithChannelsCount' => count($newAccountsWithChannels),
                'newChannelsCount' => count($newChannels),
                'newChannels' => $newChannels,
                'deletedAccounts' => count($deletedAccountsFeedbacks),
                'deletedAccountsFeedbacks' => $deletedAccountsFeedbacks,
            ]);

        $this->mailer->send($message);
    }

    /**
     * Returns a name that identifies the task
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Daily Email Report Task';
    }

}