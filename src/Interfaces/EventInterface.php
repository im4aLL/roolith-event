<?php
namespace Roolith\Event\Interfaces;


use Roolith\Event\Exceptions\Exception;
use Roolith\Event\Exceptions\InvalidArgumentException;

interface EventInterface
{
    /**
     * Listen
     *
     * @param $name string
     * @param $callback callable
     * @return bool
     * @throws InvalidArgumentException
     */
    public static function listen(string $name, $callback): bool;

    /**
     * Listeners
     *
     * @throws InvalidArgumentException
     */
    public static function listeners(string|array $names, $callback): bool;

    /**
     * Trigger event
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function trigger(string $name, $argument = null): bool;

    /**
     * Remove event
     */
    public static function unregister(string|array $name): bool;
}