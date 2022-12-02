<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class LoggerFacade
 * @package CodeLathe\Core\Utility
 * @method static log($level, $message, array $context = [])
 * @method static emergency($message, array $context = [])
 * @method static alert($message, array $context = [])
 * @method static critical($message, array $context = [])
 * @method static error($message, array $context = [])
 * @method static warning($message, array $context = [])
 * @method static notice($message, array $context = [])
 * @method static info($message, array $context = [])
 * @method static debug($message, array $context = [])
 */
abstract class LoggerFacade
{

    /**
     * @var ContainerInterface
     */
    protected static $container;

    /**
     * @var LoggerInterface
     */
    protected static $logger = null;

    /**
     * @param ContainerInterface $container
     */
    public static function setUp(ContainerInterface $container)
    {
        static::$container = $container;
    }

    protected static function loadLogger(): void
    {
        if (static::$logger === null) {
            static::$logger = static::$container->get(LoggerInterface::class);
        }
    }

    public static function __callStatic($name, $arguments)
    {
        static::loadLogger();
        static::$logger->$name(...$arguments);
    }
}