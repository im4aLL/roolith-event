<?php
namespace Roolith\Event;

use Roolith\Event\Exceptions\Exception;
use Roolith\Event\Exceptions\InvalidArgumentException;
use Roolith\Event\Interfaces\EventInterface;

class Event implements EventInterface
{
    private static $events = [];
    protected static $errorMessage = [
        'name' => 'Name characters should contain alphanumeric with ., * and _',
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

        if (self::isWildcardName($name)) {
            $name = str_replace('*', 'wildcard', $name);
        }

        self::$events[$name][] = $callback;

        return true;
    }

    /**
     * @inheritDoc
     */
    public static function listeners($names, $callback)
    {
        $result = true;

        if (!is_array($names)) {
            throw new InvalidArgumentException(self::$errorMessage['array']);
        }

        foreach ($names as $name) {
            $result = self::listen($name, $callback);
        }

        return $result;
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

        try {
            self::triggerWildCard($name, $argument);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        } catch (Exception $e) {
            throw new Exception(self::$errorMessage['listener']);
        }

        return true;
    }

    /**
     * Trigger wild card event
     *
     * @param $name
     * @param $argument
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private static function triggerWildCard($name, $argument)
    {
        if (strstr($name, '.')) {
            $nameArray = explode('.', $name);
            $wildcardListenerName = $nameArray[0].'.wildcard';

            if (isset(self::$events[$wildcardListenerName]) && $nameArray[1] !== 'wildcard') {
                try {
                    return self::trigger($wildcardListenerName, $argument);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException($e->getMessage());
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
        }

        return false;
    }

    /**
     * Is valid name
     *
     * @param $name
     * @return bool
     */
    protected static function isValidName($name)
    {
        return (bool) preg_match('/^[a-zA-Z0-9.*_]+$/', $name);
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
     * @return bool
     */
    public static function setErrorMessage($errorMessageArray)
    {
        self::$errorMessage = $errorMessageArray;

        return true;
    }

    /**
     * Is wildcard name
     *
     * @param $name
     * @return bool
     */
    protected static function isWildcardName($name)
    {
        return (bool) strstr($name, '.*');
    }

    /**
     * Reset all events
     *
     * @return bool
     */
    public static function reset()
    {
        self::$events = [];

        return true;
    }
}