<?php
namespace Roolith;

use Roolith\Exceptions\Exception;
use Roolith\Exceptions\InvalidArgumentException;
use Roolith\Interfaces\EventInterface;

class Event implements EventInterface
{
    private static $events = [];
    protected static $errorMessage = [
        'name' => 'Allows only [a-zA-Z]+[a-zA-Z0-9._]',
        'callback' => 'Invalid callback',
        'array' => 'Array required',
        'listener' => 'Listener not defined',
    ];

    /**
     * @inheritDoc
     */
    public static function listen($name, $callback)
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException(self::$errorMessage['callback']);
        }

        self::$events[$name][] = $callback;
    }

    /**
     * @inheritDoc
     */
    public static function listeners($names, $callback)
    {
        if (!is_array($names)) {
            throw new InvalidArgumentException(self::$errorMessage['array']);
        }

        foreach ($names as $name) {
            self::listen($name, $callback);
        }
    }

    /**
     * @inheritDoc
     */
    public static function trigger($name, $argument = null)
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        }

        if (!isset(self::$events[$name])) {
            throw new Exception(self::$errorMessage['listener']);
        }

        foreach (self::$events[$name] as $event => $callback) {
            if($argument && is_array($argument)) {
                call_user_func_array($callback, $argument);
            }
            elseif ($argument && !is_array($argument)) {
                call_user_func($callback, $argument);
            }
            else {
                call_user_func($callback);
            }
        }
    }

    /**
     * Is valid name
     *
     * @param $name
     * @return bool
     */
    protected static function isValidName($name)
    {
        return (bool) preg_match('/^[a-zA-Z]+[a-zA-Z0-9._]+$/', $name);
    }

    /**
     * @inheritDoc
     */
    public static function unregister($name)
    {
        if (is_array($name)) {
            foreach ($name as $n) {
                $result = self::unregister($n);

                if (!$result) {
                    return $result;
                }
            }

            return true;
        } else {
            if (isset(self::$events[$name])) {
                unset(self::$events[$name]);

                return true;
            }
        }

        return false;
    }

    /**
     * Set error messages
     *
     * @param $errorMessageArray array
     */
    public static function setErrorMessage($errorMessageArray)
    {
        self::$errorMessage = $errorMessageArray;
    }
}