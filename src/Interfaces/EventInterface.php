<?php
namespace Roolith\Event\Interfaces;

use Roolith\Event\Exceptions\Exception;
use Roolith\Event\Exceptions\InvalidArgumentException;

interface EventInterface
{
    /**
     * Register a listener for an event.
     *
     * @param string $name Event name (alphanumeric with ., * and _).
     * @param callable $callback Listener callback to invoke when the event is triggered.
     * @return bool True on success.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function listen(string $name, callable $callback): bool;

    /**
     * Register a listener for multiple events.
     *
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success.
     * @throws InvalidArgumentException When any event name is invalid.
     */
    public static function listeners(array $names, callable $callback): bool;

    /**
     * Trigger an event.
     *
     * @param string $name Event name to trigger.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return bool True on success.
     * @throws Exception When no listener is defined for the event.
     * @throws InvalidArgumentException When the event name is invalid.
     */
    public static function trigger(string $name, mixed $argument = null): bool;

    /**
     * Remove event listener(s).
     *
     * @param string|array<int, string> $name Event name or list of event names to remove.
     * @return bool True if removed, false when nothing was registered.
     */
    public static function unregister(string|array $name): bool;
}
