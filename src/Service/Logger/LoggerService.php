<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Service\Logger;

use CodeLathe\Core\Managers\MqConsumerManager;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Service\ServiceInterface;
use CodeLathe\Service\ServiceRegistryInterface;

use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

/**
 * Describes a Service instance.
 */
class LoggerService implements ServiceInterface, LoggerInterface
{
    protected $logger;

    protected $registry;

    protected $requestId = null;

    /**
     * @var bool
     */
    protected $debugMode;

    public function __construct(ServiceRegistryInterface $registry)
    {
        $this->registry = $registry;

        $dateFormat = "Y-m-d G:i:s.u";

        $this->debugMode = $this->registry->get('/app/mode') === 'dev';

        if (defined('REQUEST_DEBUG_ID')) {
            $this->requestId = REQUEST_DEBUG_ID;
        }

        $format = "%datetime% %level_name%: [%context.request_id% | %context.debug_timer% s] [%context.EXT%] %message%\n";

        $formatter = new LineFormatter($format, $dateFormat);

        $this->logger = new Logger($registry->get('/logger/name'));

        $handler = new RotatingFileHandler(CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log',
            $registry->get('/logger/level'));
        // ... In the future, we can do more fancy rotation
        //'/' in date format is treated like '/' in directory path
        // so Y/m/d-filename will create path: eg. 2017/07/21-filename.log
        //$handler->setFilenameFormat('{date}-{filename}', 'Y/m/d');

        $handler->setFormatter($formatter);
        Logger::setTimezone(new \DateTimeZone('-5'));

        $this->logger->pushHandler($handler);
    }

    /**
     * return name of the servcie
     *
     * @return string name of the service
     */
    public function name(): string
    {
        return LoggerInterface::class;
    }

    /**
     * return description of the service
     *
     * @return string description of the service
     */
    public function description(): string
    {
        return "Logger Service provides a logging interface";
    }

    private function processLog($context): bool
    {
        if (!isset($context['EXT']))
            return true;

        $ext = $context['EXT'];
        if (isset($this->registry['/logger/extended'])) {
            if (in_array($ext, $this->registry['/logger/extended'])) {
                return true;
            }
        }
        return false;
    }


    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency($message, array $context = array())
    {
        if ($this->processLog($context)) {
            $this->log('emergency', $message, $context);
        }
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert($message, array $context = array())
    {
        if ($this->processLog($context)) {
            $this->log('alert', $message, $context);
        }
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical($message, array $context = array())
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = array())
    {
        if ($this->processLog($context)) {
            $this->log('error', $message, $context);
        }
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = array())
    {
        if ($this->processLog($context)) {
            $this->log('warning', $message, $context);
        }
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice($message, array $context = array())
    {
        if ($this->processLog($context)) {
            $this->log('notice', $message, $context);
        }
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = array())
    {
        if ($this->processLog($context)) {
            $this->log('info', $message, $context);
        }
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        if ($this->processLog($context)) {
            $this->log('debug', $message, $context);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {

        // handle request id
        $context['request_id'] = $this->requestId ?? MqConsumerManager::$eventDebugId ?? '';

        // handle request time
        $startTime = null;
        if (defined('REQUEST_DEBUG_TIMER')) {
            $startTime = REQUEST_DEBUG_TIMER;
        }
        $startTime = $startTime ?? MqConsumerManager::$eventDebugStart ?? null;
        if (is_float($startTime)) {
            $context['debug_timer'] = round(microtime(true) - $startTime, 3);
        }

        if ($this->processLog($context) || $level === 'critical') {
            $this->logger->log($level, $message, $context);
        }
    }

}
