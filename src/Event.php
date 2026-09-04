<?php
namespace Roolith\Event;

use Roolith\Event\Exceptions\Exception;
use Roolith\Event\Exceptions\InvalidArgumentException;
use Roolith\Event\Interfaces\EventInterface;

class Event implements EventInterface
{
    /**
     * Registered event listeners keyed by event name.
     *
     * @var array<string, array<int, callable>>
     */
    private static array $events = [];

    /**
     * Error messages used for exceptions.
     *
     * @var array<string, string>
     */
    protected static array $errorMessage = [
        'name' => 'Name characters should contain alphanumeric with ., * and _',
        'callback' => 'Invalid callback',
        'array' => 'Array required',
        'listener' => 'Listener not defined',
    ];

    /**
     * Register a listener for an event.
     *
     * @param string $name Event name (alphanumeric with ., * and _).
     * @param callable $callback Listener callback to invoke when the event is triggered.
     * @return bool True on success.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function listen(string $name, callable $callback): bool
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        }

        if (self::isWildcardName($name)) {
            $name = str_replace('*', 'wildcard', $name);
        }

        self::$events[$name][] = $callback;

        return true;
    }

    /**
     * Register a listener for multiple events.
     *
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success.
     * @throws InvalidArgumentException When any event name is invalid.
     */
    public static function listeners(array $names, callable $callback): bool
    {
        $result = true;

        foreach ($names as $name) {
            $result = self::listen($name, $callback);
        }

        return $result;
    }

    /**
     * Trigger an event.
     *
     * @param string $name Event name to trigger.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return bool True on success.
     * @throws Exception When no listener is defined for the event.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function trigger(string $name, mixed $argument = null): bool
    {
        if (!self::isValidName($name)) {
            throw new InvalidArgumentException(self::$errorMessage['name']);
        }

        if (!isset(self::$events[$name]) && !self::hasWildcardListener($name)) {
            throw new Exception(self::$errorMessage['listener']);
        }

        if (isset(self::$events[$name])) {
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
     * Get matching single-level wildcard listener storage key.
     *
     * `event.login` matches `event.*` stored as `event.wildcard`.
     *
     * @param string $name Event name to match.
     * @return string|null Wildcard storage key or null when none matches.
     */
    private static function getWildcardListenerName(string $name): ?string
    {
        if (strstr($name, '.')) {
            $nameArray = explode('.', $name);

            if ($nameArray[1] !== 'wildcard' && isset(self::$events[$nameArray[0] . '.wildcard'])) {
                return $nameArray[0] . '.wildcard';
            }
        }

        return null;
    }

    /**
     * Check for a matching single-level wildcard listener.
     *
     * @param string $name Event name to check.
     * @return bool True when a wildcard listener matches.
     */
    private static function hasWildcardListener(string $name): bool
    {
        return self::getWildcardListenerName($name) !== null;
    }

    /**
     * Trigger wild card event.
     *
     * @param string $name Event name to match against wildcard listeners.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return bool True when a wildcard listener was triggered, false otherwise.
     * @throws Exception When wildcard dispatch fails.
     * @throws InvalidArgumentException When the resolved wildcard name is invalid.
     */
    private static function triggerWildCard(string $name, mixed $argument): bool
    {
        $wildcardListenerName = self::getWildcardListenerName($name);

        if ($wildcardListenerName !== null) {
            try {
                return self::trigger($wildcardListenerName, $argument);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage());
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        return false;
    }

    /**
     * Is valid name.
     *
     * @param string $name Event name to validate.
     * @return bool True when the name contains only allowed characters.
     */
    protected static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9.*_]+$/', $name);
    }

    /**
     * Remove event listener(s).
     *
     * @param string|array<int, string> $name Event name or list of event names to remove.
     * @return bool True if removed, false when nothing was registered.
     */
    public static function unregister(string|array $name): bool
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
            if (self::isWildcardName($name)) {
                $name = str_replace('*', 'wildcard', $name);
            }

            if (isset(self::$events[$name])) {
                unset(self::$events[$name]);

                return true;
            }
        }

        return false;
    }

    /**
     * Set error messages.
     *
     * @param array<string, string> $errorMessageArray Custom error messages keyed by `name`, `callback`, `array`, `listener`.
     * @return bool True on success.
     */
    public static function setErrorMessage(array $errorMessageArray): bool
    {
        self::$errorMessage = $errorMessageArray;

        return true;
    }

    /**
     * Is wildcard name.
     *
     * @param string $name Event name to check.
     * @return bool True when the name contains a wildcard (`.*`).
     */
    protected static function isWildcardName(string $name): bool
    {
        return (bool) strstr($name, '.*');
    }

    /**
     * Reset all events.
     *
     * @return bool True on success.
     */
    public static function reset(): bool
    {
        self::$events = [];

        return true;
    }
}
