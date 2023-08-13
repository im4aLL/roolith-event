<?php
namespace Roolith\Event;

use Closure;
use Roolith\Event\Exceptions\Exception;
use Roolith\Event\Exceptions\InvalidArgumentException;
use Roolith\Event\Interfaces\EventInterface;

class Event implements EventInterface
{
    private static array $events = [];
    protected static array $errorMessage = [
        'name' => 'Name characters should contain alphanumeric with ., * and _',
        'callback' => 'Invalid callback',
        'array' => 'Array required',
        'listener' => 'Listener not defined',
    ];

    /**
     * @inheritDoc
     */
    public static function listen(string $name, $callback): bool
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        }

        if (!is_callable($callback) && !is_string($callback) && !is_array($callback)) {
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
    public static function listeners(string|array $names, $callback): bool
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
    public static function trigger(string $name, $argument = null): bool
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        }

        if (!isset(self::$events[$name])) {
            throw new Exception(self::$errorMessage['listener']);
        }

        foreach (self::$events[$name] as $event => $callback) {
            self::executeCallback($callback, $argument);
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
    private static function triggerWildCard(string $name, $argument): bool
    {
        if (str_contains($name, '.')) {
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
     * @link https://github.com/eliseekn/tinymvc/blob/2.0/core/Routing/Router.php
     */
    protected static function executeCallback($callback, $argument = null): mixed
    {
        if (is_null($argument)) $argument = [];
        if (is_string($argument))  $argument = [$argument];

        if ($callback instanceof Closure) {
            return (new DependencyInjection())->resolveClosure($callback, $argument);
        }

        if (is_array($callback)) {
            list($controller, $action) = $callback;

            if (class_exists($controller) && method_exists($controller, $action)) {
                return (new DependencyInjection())->resolve($controller, $action, $argument);
            }
        }

        if (is_string($callback)) {
            if (class_exists($callback)) {
                return (new DependencyInjection())->resolve($callback, '__invoke', $argument);
            }
        }
    }

    /**
     * Is valid name
     *
     * @param $name
     * @return bool
     */
    protected static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9.*_]+$/', $name);
    }

    /**
     * @inheritDoc
     */
    public static function unregister(string|array $name): bool
    {
        if (is_array($name)) {
            foreach ($name as $n) {
                $result = self::unregister($n);

                if (!$result) {
                    return false;
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
    public static function setErrorMessage(array $errorMessageArray): bool
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
    protected static function isWildcardName(string $name): bool
    {
        return (bool) strstr($name, '.*');
    }

    /**
     * Reset all events
     *
     * @return bool
     */
    public static function reset(): bool
    {
        self::$events = [];

        return true;
    }
}