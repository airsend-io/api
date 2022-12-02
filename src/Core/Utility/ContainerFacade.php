<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use Psr\Container\ContainerInterface;

abstract class ContainerFacade
{

    /**
     * @var ContainerInterface
     */
    protected static $container;

    /**
     * @param ContainerInterface $container
     */
    public static function setUp(ContainerInterface $container)
    {
        static::$container = $container;
    }

    /**
     * @param string $accessor
     * @return mixed
     */
    public static function get(string $accessor)
    {
        return static::$container->get($accessor);
    }
}