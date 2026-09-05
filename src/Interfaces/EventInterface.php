<?php

declare(strict_types=1);
namespace Roolith\Event\Interfaces;

use Roolith\Event\Exceptions\InvalidArgumentException;

interface EventInterface
{
    /**
     * Register a listener for an event.
     *
     * @param string $name Event name.
     * @param callable $callback Listener callback.
     * @return bool True on success.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function listen(string $name, callable $callback): bool;

    /**
     * Register one listener for multiple events atomically.
     *
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success, false when given an empty list.
     * @throws InvalidArgumentException When any event name is invalid or not a string.
     */
    public static function listeners(array $names, callable $callback): bool;

    /**
     * Trigger an event.
     *
     * @param string $name Event name to trigger.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return array<int, mixed> Ordered listener return values, empty when no listener matched.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function trigger(string $name, mixed $argument = null): array;

    /**
     * Remove listeners.
     *
     * @param string|array<int, string> $name Event name or list of event names.
     * @param callable|null $callback When given, only that callback is removed.
     * @return bool True only when every given name removed something, false otherwise.
     * @throws InvalidArgumentException When any event name is invalid.
     */
    public static function unregister(string|array $name, ?callable $callback = null): bool;

    /**
     * Check whether triggering a name would invoke any listener.
     *
     * @param string $name Event name to check.
     * @return bool True when an exact or wildcard listener matches.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function has(string $name): bool;

    /**
     * Get registered listeners.
     *
     * @param string|null $name Null for all listeners keyed by name, otherwise listeners firing for that name.
     * @return array<string, array<int, callable>>|array<int, callable>
     * @throws InvalidArgumentException When the given name is invalid.
     */
    public static function getListeners(?string $name = null): array;

    /**
     * Remove all listeners from the shared dispatcher.
     *
     * @return bool True on success.
     */
    public static function reset(): bool;

    /**
     * Merge custom error messages over defaults on the shared dispatcher.
     *
     * Only the `name` key is currently thrown. `callback`, `array`, and
     * `listener` are accepted for backwards compatibility but reserved.
     *
     * @param array<string, string> $errorMessageArray Custom messages keyed by name, callback, array, listener.
     * @return bool True on success.
     * @throws InvalidArgumentException When a key is unknown or a message is not a non-empty string.
     */
    public static function setErrorMessage(array $errorMessageArray): bool;
}
