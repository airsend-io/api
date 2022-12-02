<?php

use Carbon\Carbon;
use Carbon\CarbonInterval;
use CodeLathe\Core\Utility\ContainerFacade;
use Psr\Log\LoggerInterface;

/**
 * @param string $message
 * @param Throwable $e
 */
function logError(string $message, Throwable $e)
{
    /** @var LoggerInterface $logger */
    $logger = ContainerFacade::get(LoggerInterface::class);
    $logger->error("INDEXING ERROR | $message | " . get_class($e) . " | {$e->getFile()}:{$e->getLine()} | {$e->getMessage()}");
}


/**
 * Parses the lookback arg if present, returning the since limit for the indexing
 * @param array $argv
 * @return string
 * @throws Exception
 */
function parseSinceOption(array $argv): string
{
    $interval = array_reduce($argv, function ($carry, $item) {
        if ($carry === null && preg_match('/^--lookback=(.+)$/', $item, $matches)) {
            return trim($matches[1]);
        }
        return $carry;
    }, null);

    if ($interval === null) {
        return '';
    }

    $interval = CarbonInterval::fromString($interval);
    if ($interval->totalSeconds === 0) {
        throw new Exception('Invalid look back interval');
    } else {
        return Carbon::now()->sub($interval)->format('Y-m-d H:i:s');
    }
}

function parseForceReindexOption(array $argv): bool
{
    return array_reduce($argv, function ($carry, $item) {
        if (preg_match('/^--force-reindex$/', $item)) {
            return true;
        }
        return $carry;
    }, false);
}

function parseFlushOption(array $argv): bool
{
    return array_reduce($argv, function ($carry, $item) {
        if (preg_match('/^--flush$/', $item)) {
            return true;
        }
        return $carry;
    }, false);
}

/**
 * @param array $argv
 * @return int[]
 */
function parseIdsOption(array $argv): array
{
    return array_reduce($argv, function ($carry, $item) {
        if (preg_match('/^--id=([0-9]+)$/', $item, $matches)) {
            $carry[] = (int)$matches[1];
        }
        return $carry;
    }, []);
}

function parseIndexName(array $argv): ?string
{
    return array_reduce($argv, function ($carry, $item) {
        if (preg_match('/^--index_name=([a-z0-9_]+)$/', $item, $matches)) {
            $carry = trim($matches[1]);
        }
        return $carry;
    }, null);
}

function parseSkipContent(array $argv): bool
{
    return array_reduce($argv, function ($carry, $item) {
        if (preg_match('/^--skip_content$/', $item, $matches)) {
            return true;
        }
        return $carry;
    }, false);
}

function parseVerbose(array $argv): bool
{
    return array_reduce($argv, function ($carry, $item) {
        if (preg_match('/^--verbose$/', $item, $matches)) {
            return true;
        }
        return $carry;
    }, false);
}

function parseOptions(array $argv): array
{

    $options = [];

    // flush
    $options['flush'] = parseFlushOption($argv);

    // force-reindex
    $options['force-reindex'] = parseForceReindexOption($argv);

    // ids
    $options['ids'] = parseIdsOption($argv);

    // since
    $options['since'] = parseSinceOption($argv);

    // physical index name
    $options['index_name'] = parseIndexName($argv);

    // skip file content indexing
    $options['skip_content'] = parseSkipContent($argv);

    // verbose option
    $options['verbose'] = parseVerbose($argv);

    return $options;

}

function output(string $message, ?bool $verbose = null): void
{
    if ($verbose === null) {
        global $verbose;
    }
    $verbose = $verbose ?? true;

    if ($verbose) {
        echo $message;
    }
}
