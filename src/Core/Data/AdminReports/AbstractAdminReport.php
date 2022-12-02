<?php
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Data\AdminReports;

use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\AdminReportRequestedEvent;
use CodeLathe\Service\Database\RootDatabaseService;
use Psr\Log\LoggerInterface;

abstract class AbstractAdminReport
{
    /**
     * Declare Database Service
     *
     * @var RootDatabaseService
     */
    private $dbs;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * UserDataStore constructor.
     *
     * @param RootDatabaseService $dbs
     * @param LoggerInterface $logger
     * @param EventManager $eventManager
     */
    public function __construct(RootDatabaseService $dbs, LoggerInterface $logger, EventManager $eventManager)
    {
        $this->dbs = $dbs;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @return array
     */
    final public static function listReports(): array
    {
        $classes = array_map(function ($item) {
            if (preg_match('/\/([^.\/]+)\.php$/', trim($item), $matches)) {
                return '\\' . __NAMESPACE__ . '\\' . $matches[1];
            }
            return '';
        }, glob(__DIR__ . '/*Report.php'));

        $classes = array_filter($classes, function ($class) {
            return is_subclass_of($class, static::class);
        });

        return array_map(function ($class) {
            preg_match('/[^\\\\]+$/', $class, $matches);
            return [
                'id' => $matches[0],
                'name' => $class::name(),
                'desc' => $class::description(),
                'paginated' => $class::allowPaginatedAccess(),
            ];
        }, array_values($classes));
    }

    abstract public static function name(): string;

    abstract public static function description(): string;

    abstract public static function allowPaginatedAccess(): bool;

    public function countTotal(): int
    {
        // grab sql count code
        $sql = trim($this->countSql());

        // execute the query
        $result = $this->dbs->selectOne($sql);

        return $result !== null ? (int) array_shift($result) : 0;
    }

    public function execute(int $limit = 30, int $offset = 0, ?string $orderColumn = null, ?string $orderDirection = null): array
    {
        // grab sql data code
        $sql = trim($this->mainSql());

        // replace the order
        if ($orderColumn !== null && preg_match('/^[a-z0-9_]+$/i', $orderColumn)) {
            $sql = preg_replace('/ORDER\s+BY\s+[a-z0-9_]+\s+((?:A|DE)SC)/i', "ORDER BY $orderColumn $1", $sql);
        }
        if ($orderDirection !== null && preg_match('/^asc|desc$/i', $orderDirection)) {
            $sql = preg_replace('/ORDER\s+BY\s+([a-z0-9_]+)\s+(?:A|DE)SC/i', "ORDER BY $1 $orderDirection", $sql);
        }

        // remove ; if present
        $sql = preg_replace('/;\s*$/', '', $sql);

        // set pagination
        $sql .= PHP_EOL . " LIMIT :limit OFFSET :offset;";

        // execute the query and return
        return $this->dbs->select($sql, compact('limit', 'offset')) ?? [];
    }

    public function executeInBackground(string $email): void
    {
        // grab sql data code
        $sql = trim($this->mainSql());

        $event = new AdminReportRequestedEvent($sql, $email, static::name());
        $this->eventManager->publishEvent($event);
    }

    abstract protected function mainSql(): string;

    abstract protected function countSql(): string;
}