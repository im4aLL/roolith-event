<?php

declare(strict_types=1);
namespace Roolith\Event;

use Roolith\Event\Exceptions\InvalidArgumentException;
use Roolith\Event\Interfaces\DispatcherInterface;

/**
 * Instance-based event dispatcher.
 *
 * Use this for dependency injection and isolated testing:
 *
 *   $events = new Dispatcher();
 *   $events->listen('user.login', fn () => ...);
 *   $results = $events->trigger('user.login');
 *
 * The static Event facade proxies to a shared Dispatcher for
 * backwards-compatible global access.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $events = [];

    /**
     * @var array<string, string>
     */
    private array $errorMessage = [
        'name' => 'Name must be segments of letters, digits or underscore joined by dots, with optional terminal .*',
        'callback' => 'Invalid callback',
        'array' => 'Array required',
        'listener' => 'Listener not defined',
    ];

    /**
     * @param string $name Event name.
     * @param callable $callback Listener callback.
     * @return bool True on success.
     */
    public function listen(string $name, callable $callback): bool
    {
        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException($this->errorMessage['name']);
        }

        $this->events[$name][] = $callback;

        return true;
    }

    /**
     * @param array<int, string> $names List of event names.
     * @param callable $callback Listener callback shared by all given event names.
     * @return bool True on success, false when given an empty list.
     */
    public function listeners(array $names, callable $callback): bool
    {
        if ($names === []) {
            return false;
        }

        foreach ($names as $name) {
            if (!is_string($name) || !$this->isValidName($name)) {
                throw new InvalidArgumentException($this->errorMessage['name']);
            }
        }

        foreach ($names as $name) {
            $this->events[$name][] = $callback;
        }

        return true;
    }

    /**
     * @param string $name Event name to trigger.
     * @param mixed $argument Optional single argument or list of arguments (array) passed to listeners.
     * @return array<int, mixed> Ordered listener return values, empty when no listener matched.
     */
    public function trigger(string $name, mixed $argument = null): array
    {
        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException($this->errorMessage['name']);
        }

        $results = [];

        // Snapshot both lists before any invoke so listen()/unregister()
        // inside a listener only affects the next trigger().
        $exact = $this->events[$name] ?? [];
        $wildcardKey = $this->getWildcardListenerName($name);

        $wildcards = ($wildcardKey !== null && $wildcardKey !== $name)
            ? ($this->events[$wildcardKey] ?? [])
            : [];

        foreach ($exact as $callback) {
            $result = $this->invoke($callback, $argument);
            $results[] = $result;
            if ($result === false) {
                return $results;
            }
        }

        foreach ($wildcards as $callback) {
            $result = $this->invoke($callback, $argument);
            $results[] = $result;
            if ($result === false) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param string|array<int, string> $name Event name or list of event names.
     * @param callable|null $callback When given, only that callback is removed.
     * @return bool True only when every given name removed something, false otherwise.
     */
    public function unregister(string|array $name, ?callable $callback = null): bool
    {
        if (is_array($name)) {
            $names = $this->validateNames($name);
            if ($names === []) {
                return false;
            }
            foreach ($names as $single) {
                if (!$this->removeOne($single, $callback)) {
                    return false;
                }
            }

            return true;
        }

        if (!is_string($name) || !$this->isValidName($name)) {
            throw new InvalidArgumentException($this->errorMessage['name']);
        }

        return $this->removeOne($name, $callback);
    }

    /**
     * @param string $name Event name to check.
     * @return bool True when an exact or wildcard listener matches.
     */
    public function has(string $name): bool
    {
        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException($this->errorMessage['name']);
        }

        if (isset($this->events[$name])) {
            return true;
        }

        return $this->getWildcardListenerName($name) !== null;
    }

    /**
     * @param string|null $name Null for all listeners, otherwise listeners firing for that name.
     * @return array<string, array<int, callable>>|array<int, callable>
     */
    public function getListeners(?string $name = null): array
    {
        if ($name === null) {
            return $this->events;
        }

        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException($this->errorMessage['name']);
        }

        $matched = $this->events[$name] ?? [];
        $wildcardKey = $this->getWildcardListenerName($name);
        if ($wildcardKey !== null && $wildcardKey !== $name) {
            foreach ($this->events[$wildcardKey] ?? [] as $callback) {
                $matched[] = $callback;
            }
        }

        return $matched;
    }

    /**
     * @return bool True on success.
     */
    public function reset(): bool
    {
        $this->events = [];

        return true;
    }

    /**
     * Override default error messages by merging over defaults.
     *
     * Only the `name` key is currently thrown. `callback`, `array`, and
     * `listener` are accepted for backwards compatibility but reserved.
     *
     * @param array<string, string> $errorMessageArray Custom messages keyed by name, callback, array, listener.
     * @return bool True on success.
     */
    public function setErrorMessage(array $errorMessageArray): bool
    {
        $allowed = ['name', 'callback', 'array', 'listener'];

        foreach ($errorMessageArray as $key => $message) {
            if (!in_array($key, $allowed, true) || !is_string($message) || $message === '') {
                throw new InvalidArgumentException('Invalid error message key or value');
            }
        }

        $this->errorMessage = array_merge($this->errorMessage, $errorMessageArray);

        return true;
    }

    /**
     * Invoke one listener with trigger() argument semantics.
     *
     * @param callable $callback Listener to invoke.
     * @param mixed $argument Null calls with zero args, array spreads, anything else passes as one arg.
     * @return mixed Listener return value.
     */
    private function invoke(callable $callback, mixed $argument): mixed
    {
        if ($argument !== null && is_array($argument)) {
            return call_user_func_array($callback, $argument);
        }

        if ($argument !== null) {
            return call_user_func($callback, $argument);
        }

        return call_user_func($callback);
    }

    /**
     * Remove listeners for one validated name.
     *
     * @param string $name Validated event name.
     * @param callable|null $callback Null removes the whole name.
     * @return bool True when anything was removed.
     */
    private function removeOne(string $name, ?callable $callback): bool
    {
        if (!isset($this->events[$name])) {
            return false;
        }

        if ($callback === null) {
            unset($this->events[$name]);

            return true;
        }

        $kept = [];
        $removed = false;
        foreach ($this->events[$name] as $registered) {
            if (!$removed && $registered === $callback) {
                $removed = true;
                continue;
            }
            $kept[] = $registered;
        }

        if (!$removed) {
            return false;
        }

        if ($kept === []) {
            unset($this->events[$name]);
        } else {
            $this->events[$name] = $kept;
        }

        return true;
    }

    /**
     * Validate an array of names atomically before any removal.
     *
     * @param array<int, mixed> $names Raw names.
     * @return array<int, string> Validated names.
     */
    private function validateNames(array $names): array
    {
        foreach ($names as $single) {
            if (!is_string($single) || !$this->isValidName($single)) {
                throw new InvalidArgumentException($this->errorMessage['name']);
            }
        }

        /** @var array<int, string> $names */
        return $names;
    }

    /**
     * Get matching single-level wildcard storage key.
     *
     * `event.login` matches `event.*` stored as `event.*`.
     * `a.b.c` matches `a.b.*`. Matching is single-level: only the
     * immediate parent prefix is considered.
     *
     * @param string $name Event name to match.
     * @return string|null Wildcard storage key or null when none matches.
     */
    private function getWildcardListenerName(string $name): ?string
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

        if (isset($this->events[$candidate])) {
            return $candidate;
        }

        if ($candidate === $name) {
            for ($i = count($parts) - 2; $i >= 1; $i--) {
                $ancestor = implode('.', array_slice($parts, 0, $i)) . '.*';

                if ($ancestor !== $name && isset($this->events[$ancestor])) {
                    return $ancestor;
                }
            }
        }

        return null;
    }

    /**
     * Valid event name.
     *
     * Segments of letters, digits, underscore joined by single dots,
     * with an optional terminal `.*` wildcard. Rejects `**`, `..`,
     * `foo*bar`, `*.foo`, leading/trailing dots, empty segments, and lone `*`.
     *
     * @param string $name Event name to validate.
     * @return bool True when valid.
     */
    private function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*(\.\*)?$/', $name);
    }
}
