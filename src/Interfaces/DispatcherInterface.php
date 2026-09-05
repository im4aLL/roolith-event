<?php

declare(strict_types=1);
namespace Roolith\Event\Interfaces;

interface DispatcherInterface
{
    /**
     * Register a listener for an event.
     *
     * @param string $name Event name.
     * @param callable $callback Listener callback.
     * @return bool True on success.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When the event name is invalid.
     */
    public function listen(string $name, callable $callback): bool;

    /**
     * Register one listener for multiple events atomically.
     *
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success, false when given an empty list.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When any event name is invalid or not a string.
     */
    public function listeners(array $names, callable $callback): bool;

    /**
     * Trigger an event.
     *
     * Never throws for a missing listener: returns an empty array instead.
     * Listeners run in registration order (exact listeners first, then the
     * matching single-level wildcard). Returning boolean false from a listener
     * stops further propagation.
     *
     * Reentrancy: the listener list is snapshotted before dispatch, so
     * listen()/unregister() calls inside a listener affect the next
     * trigger(), not the one in progress.
     *
     * @param string $name Event name to trigger.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return array<int, mixed> Ordered listener return values, empty when no listener matched.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When the event name is invalid.
     */
    public function trigger(string $name, mixed $argument = null): array;

    /**
     * Remove listeners.
     *
     * @param string|array<int, string> $name Event name or list of event names.
     * @param callable|null $callback When given, only that callback is removed; otherwise the whole event name is removed.
     * @return bool True only when every given name removed something, false otherwise.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When any event name is invalid.
     */
    public function unregister(string|array $name, ?callable $callback = null): bool;

    /**
     * Check whether triggering a name would invoke any listener.
     *
     * @param string $name Event name to check.
     * @return bool True when an exact or wildcard listener matches.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When the event name is invalid.
     */
    public function has(string $name): bool;

    /**
     * Get registered listeners.
     *
     * @param string|null $name When null, return all listeners keyed by event name; otherwise return listeners that would fire for that name.
     * @return array<string, array<int, callable>>|array<int, callable> All listeners or listeners for one name.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When the given name is invalid.
     */
    public function getListeners(?string $name = null): array;

    /**
     * Remove all listeners.
     *
     * @return bool True on success.
     */
    public function reset(): bool;

    /**
     * Override default error messages by merging over defaults.
     *
     * Only the `name` key is currently thrown. `callback`, `array`, and
     * `listener` are accepted for backwards compatibility but reserved.
     *
     * @param array<string, string> $errorMessageArray Custom messages keyed by name, callback, array, listener.
     * @return bool True on success.
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException When a key is unknown or a message is not a non-empty string.
     */
    public function setErrorMessage(array $errorMessageArray): bool;
}
