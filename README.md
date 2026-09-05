# roolith-event
PHP event listener

Requirements: PHP >= 8.0 for both runtime and test suite. Install with `composer install` (runs PHPUnit via `vendor/bin/phpunit`; Composer picks PHPUnit 9 on PHP 8.0-8.2 and PHPUnit 12 on PHP 8.3+).

### Install
```
composer require roolith/event
```

### Usage
```php
use Roolith\Event\Event;

Event::listen('login', function () {
    echo 'Event user login fired! <br>';
});

Event::trigger('login'); // returns [null] (ordered listener results)
```

### Working example
```php
<?php
use Roolith\Event\Event;

require_once __DIR__ . '/../vendor/autoload.php';

class User {
    public function login() {
        return true;
    }

    public function logout() {
        return true;
    }

    public function updated() {
        return true;
    }
}

Event::listen('login', function () {
    echo 'Event user login fired! <br>';
});

$user = new User();

if($user->login()) {
    Event::trigger('login');
}
```

#### Usage with param
```php
Event::listen('logout', function ($param) {
    echo 'Event '. $param .' logout fired! <br>';
});

if($user->logout()) {
    Event::trigger('logout', 'user');
}
```

#### Usage with param array
Array arguments are spread into listener params. `null` means zero args; falsy `0`, `''`, `false` are passed through as one arg.
```php
Event::listen('updated', function($param1, $param2) {
    echo 'Event ('. $param1 .', '. $param2 .') updated fired! <br>';
});

if($user->updated()) {
    Event::trigger('updated', ['param1', 'param2']);
}
```

Arg-count mismatch throws `ArgumentCountError` from PHP itself; match listener arity to trigger args.

#### Multiple events
```php
Event::listeners(['login', 'user.login'], function () {
    // shared listener
});
```
Empty list returns `false` and registers nothing. Validation is atomic: failure leaves state untouched.

#### Trigger contract
`trigger()` never throws for a missing listener. It returns ordered listener results, `[]` when nothing matched:
```php
$results = Event::trigger('login'); // e.g. ['ok', null]
if ($results === []) {
    // no listener matched
}
```
Use introspection to avoid try/catch:
```php
if (Event::has('login')) {
    Event::trigger('login');
}
$all = Event::getListeners(); // keyed by name
$forName = Event::getListeners('event.login'); // exact + wildcard matches
```
Returning boolean `false` from a listener stops further propagation (exact then wildcard order):
```php
Event::listen('login', fn () => false); // second listener below never runs
Event::listen('login', fn () => 'never');
```

#### Unregister an event
```php
Event::unregister('updated');
Event::unregister(['a', 'b']); // array form, true only when every name removed something
$cb = function () {};
Event::listen('updated', $cb);
Event::unregister('updated', $cb); // remove one callback, leaves others intact
```
Invalid names throw `InvalidArgumentException`. Missing names return `false`. Array form returns `true` only when every name removed something. `listen()` always appends with no dedup: registering the same callable twice fires it twice. `unregister($name, $cb)` removes only the first `===` match, so one call leaves the second copy registered.

#### Wildcard event
```php
Event::listen('event.login', function () {
    echo 'Login Wild card fired! <br>';
});

Event::listen('event.logout', function () {
    echo 'Logout Wild card fired! <br>';
});

Event::listen('event.*', function ($param) {
    echo 'Wild card fired! - '.$param.' <br>';
});


Event::trigger('event.login', 'login');
Event::trigger('event.logout', 'logout');
```

Wildcard matching is single-level: `event.*` matches `event.login` but not `event.login.extra`, and `a.b.*` matches `a.b.c` but not `a.b.c.d`. Star-trigger fallback is intentional: triggering a wildcard pattern itself (e.g. `event.login.*` with `event.*` registered, or `a.b.c.*` with `a.b.*` registered) resolves to the closest ancestor wildcard listener. Exact registration wins: when both the star name and its ancestor are registered, triggering the star name fires only its exact listeners.

Valid names are segments of letters, digits, underscore joined by single dots, with optional terminal `.*` (e.g. `login`, `user.login`, `event.*`, `a.b.*`). Rejected: `**`, `..`, `foo*bar`, `*.foo`, `event.*.extra`, leading/trailing dots, empty segments, lone `*`, spaces, dashes.

#### Exceptions
Invalid names throw `Roolith\Event\Exceptions\InvalidArgumentException` (extends SPL `\InvalidArgumentException`, so standard catches work). Missing listeners do not throw; `trigger()` returns `[]`. The legacy `Roolith\Event\Exceptions\Exception` class is kept for BC but no longer thrown. BC break note: `InvalidArgumentException` no longer extends the library `Exception`, so existing `catch (Roolith\Event\Exceptions\Exception)` blocks around `listen()` / `trigger()` / `unregister()` will not catch validation errors; catch `Roolith\Event\Exceptions\InvalidArgumentException` (or SPL `\InvalidArgumentException`) instead. Custom validation messages set via `setErrorMessage()` are used for subsequently thrown validation errors. Only the `name` key is currently thrown; `callback`, `array`, and `listener` keys are accepted for BC but reserved/legacy (callable/array misuse surfaces as PHP `TypeError`, and missing listeners return `[]` instead of throwing).

```php
Event::setErrorMessage(['name' => 'custom-name-error']);
```

#### Isolated dispatcher (DI / testing)
`Event` is a static facade over a shared `Dispatcher`. Prefer instances for DI and test isolation:
```php
use Roolith\Event\Dispatcher;

$events = new Dispatcher();
$events->listen('login', fn () => 'ok');
$events->trigger('login'); // ['ok'] isolated from Event facade
```
Facade tests should isolate via `Event::reset()` in `tearDown` or `Event::setSharedDispatcher(new Dispatcher())`.

#### Reentrancy
Listeners are snapshotted before dispatch. `listen()` / `unregister()` inside a listener affect the next `trigger()`, not the one in progress. Nested `trigger()` calls run independently.

#### Reset
```php
Event::reset(); // clears shared dispatcher listeners
```

License: MIT.
