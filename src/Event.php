<?php

declare(strict_types=1);
namespace Roolith\Event;

use Roolith\Event\Exceptions\InvalidArgumentException;
use Roolith\Event\Interfaces\DispatcherInterface;
use Roolith\Event\Interfaces\EventInterface;

/**
 * Static facade over a shared Dispatcher.
 *
 * Kept for backwards compatibility. Prefer `new Dispatcher()` with
 * dependency injection for isolated state in apps and tests:
 *
 *   $events = new Dispatcher();
 *
 * Shared global state is explicit here: every static call proxies to
 * `Event::shared()`. Tests using the facade should call `Event::reset()`
 * in tearDown or `Event::setSharedDispatcher(new Dispatcher())` for isolation.
 */
class Event implements EventInterface
{
    private static ?DispatcherInterface $shared = null;

    /**
     * Get the shared dispatcher, creating it on first use.
     *
     * @return DispatcherInterface Shared instance.
     */
    public static function shared(): DispatcherInterface
    {
        if (self::$shared === null) {
            self::$shared = new Dispatcher();
        }

        return self::$shared;
    }

    /**
     * Replace the shared dispatcher (useful for test isolation).
     *
     * @param DispatcherInterface $dispatcher Replacement instance.
     * @return void
     */
    public static function setSharedDispatcher(DispatcherInterface $dispatcher): void
    {
        self::$shared = $dispatcher;
    }

    /**
     * Register a listener for an event.
     *
     * @param string $name Event name.
     * @param callable $callback Listener callback.
     * @return bool True on success.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function listen(string $name, callable $callback): bool
    {
        return self::shared()->listen($name, $callback);
    }

    /**
     * Register one listener for multiple events atomically.
     *
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success, false when given an empty list.
     * @throws InvalidArgumentException When any event name is invalid or not a string.
     */
    public static function listeners(array $names, callable $callback): bool
    {
        return self::shared()->listeners($names, $callback);
    }

    /**
     * Trigger an event.
     *
     * Returns ordered listener results instead of bool. Missing listeners
     * return an empty array (no exception). A listener returning boolean
     * false stops further propagation.
     *
     * Reentrancy: listeners are snapshotted before dispatch, so listen() /
     * unregister() inside a listener affect the next trigger(), not the
     * one in progress.
     *
     * @param string $name Event name to trigger.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return array<int, mixed> Ordered listener return values.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function trigger(string $name, mixed $argument = null): array
    {
        return self::shared()->trigger($name, $argument);
    }

    /**
     * Remove listeners.
     *
     * @param string|array<int, string> $name Event name or list of event names.
     * @param callable|null $callback When given, only that callback is removed.
     * @return bool True only when every given name removed something, false otherwise.
     * @throws InvalidArgumentException When any event name is invalid.
     */
    public static function unregister(string|array $name, ?callable $callback = null): bool
    {
        return self::shared()->unregister($name, $callback);
    }

    /**
     * Check whether triggering a name would invoke any listener.
     *
     * @param string $name Event name to check.
     * @return bool True when an exact or wildcard listener matches.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function has(string $name): bool
    {
        return self::shared()->has($name);
    }

    /**
     * Get registered listeners.
     *
     * @param string|null $name Null for all listeners keyed by name, otherwise listeners firing for that name.
     * @return array<string, array<int, callable>>|array<int, callable>
     * @throws InvalidArgumentException When the given name is invalid.
     */
    public static function getListeners(?string $name = null): array
    {
        return self::shared()->getListeners($name);
    }

    /**
     * Set error messages by merging over defaults.
     *
     * Only the `name` key is currently thrown. `callback`, `array`, and
     * `listener` are accepted for backwards compatibility but reserved.
     *
     * @param array<string, string> $errorMessageArray Custom messages keyed by name, callback, array, listener.
     * @return bool True on success.
     * @throws InvalidArgumentException When a key is unknown or a message is not a non-empty string.
     */
    public static function setErrorMessage(array $errorMessageArray): bool
    {
        return self::shared()->setErrorMessage($errorMessageArray);
    }

    /**
     * Reset the shared dispatcher.
     *
     * @return bool True on success.
     */
    public static function reset(): bool
    {
        return self::shared()->reset();
    }
}
