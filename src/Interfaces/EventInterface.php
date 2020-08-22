<?php
namespace Roolith\Interfaces;


use Roolith\Exceptions\Exception;
use Roolith\Exceptions\InvalidArgumentException;

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
    public static function listen($name, $callback);

    /**
     * Listeners
     *
     * @param array $names
     * @param $callback callable
     * @return bool
     * @throws InvalidArgumentException
     */
    public static function listeners($names, $callback);

    /**
     * Trigger event
     *
     * @param $name
     * @param null $argument
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function trigger($name, $argument = null);

    /**
     * Remove event
     *
     * @param $name string|array
     * @return bool
     */
    public static function unregister($name);
}