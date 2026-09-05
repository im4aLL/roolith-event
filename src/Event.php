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

        self::$events[$name][] = $callback;

        return true;
    }

    /**
     * Register a listener for multiple events atomically.
     *
     * Empty list returns false and registers nothing. All names are
     * validated before any listener is registered, so a failure
     * leaves existing state untouched. Non-string elements throw
     * `InvalidArgumentException` (note: `listen()` coerces scalar
     * names via its `string` type-hint instead).
     *
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success, false when given an empty list.
     * @throws InvalidArgumentException When any event name is invalid or not a string.
     */
    public static function listeners(array $names, callable $callback): bool
    {
        if ($names === []) {
            return false;
        }

        foreach ($names as $name) {
            if (!is_string($name) || !self::isValidName($name)) {
                throw new InvalidArgumentException(self::$errorMessage['name']);
            }
        }

        foreach ($names as $name) {
            self::$events[$name][] = $callback;
        }

        return true;
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
                if ($argument !== null && is_array($argument)) {
                    call_user_func_array($callback, $argument);
                }
                elseif ($argument !== null && !is_array($argument)) {
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
     * `event.login` matches `event.*` stored as `event.*`.
     * `a.b.c` matches `a.b.*`. Matching is single-level: only the
     * immediate parent prefix is considered (depth must align).
     * Self-recursion is guarded in `triggerWildCard()`, so names
     * containing `*` (e.g. `event.*`, `event.login.*`) still resolve
     * to their prefix wildcard here. When the trigger itself is a
     * wildcard form with no exact listener (e.g. `event.login.*`),
     * walk up to the closest ancestor wildcard (`event.*`).
     *
     * @param string $name Event name to match.
     * @return string|null Wildcard storage key or null when none matches.
     */
    private static function getWildcardListenerName(string $name): ?string
    {
        if (!str_contains($name, '.')) {
            return null;
        }

        $parts = explode('.', $name);
        $parentParts = array_slice($parts, 0, -1);

        if ($parentParts === []) {
            return null;
        }

        $candidate = implode('.', $parentParts) . '.*';

        if (isset(self::$events[$candidate])) {
            return $candidate;
        }

        if ($candidate === $name) {
            for ($i = count($parts) - 2; $i >= 1; $i--) {
                $ancestor = implode('.', array_slice($parts, 0, $i)) . '.*';

                if ($ancestor !== $name && isset(self::$events[$ancestor])) {
                    return $ancestor;
                }
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

        if ($wildcardListenerName !== null && $wildcardListenerName !== $name) {
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
            if (isset(self::$events[$name])) {
                unset(self::$events[$name]);

                return true;
            }
        }

        return false;
    }

    /**
     * Set error messages by merging over defaults.
     *
     * Unknown keys or non-string/empty values are rejected so a
     * partial update can never leave other keys undefined.
     *
     * @param array<string, string> $errorMessageArray Custom error messages keyed by `name`, `callback`, `array`, `listener`.
     * @return bool True on success.
     * @throws InvalidArgumentException When a key is unknown or a message is not a non-empty string.
     */
    public static function setErrorMessage(array $errorMessageArray): bool
    {
        $allowed = ['name', 'callback', 'array', 'listener'];

        foreach ($errorMessageArray as $key => $message) {
            if (!in_array($key, $allowed, true) || !is_string($message) || $message === '') {
                throw new InvalidArgumentException('Invalid error message key or value');
            }
        }

        self::$errorMessage = array_merge(self::$errorMessage, $errorMessageArray);

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
