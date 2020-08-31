# roolith-event
PHP event listener

### Install
```
composer require roolith/event
```

### Usage
```php
Event::listen('login', function () {
    echo 'Event user login fired! <br>';
});

Event::trigger('login');
```

### Working example
```php
<?php
use Roolith\Event\Event;

require_once __DIR__ . '/PATH_TO_AUTOLOAD/autoload.php';

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
```php
Event::listen('updated', function($param1, $param2) {
    echo 'Event ('. $param1 .', '. $param2 .') updated fired! <br>';
});

if($user->updated()) {
    Event::trigger('updated', ['param1', 'param2']);
}
```

#### Unregister an event
```php
Event::unregister('updated');
```

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