<?php
use PHPUnit\Framework\TestCase;
use Roolith\Event\Event;

class EventTest extends TestCase
{
    public function tearDown(): void
    {
        Event::reset();
    }

    /**
     * @dataProvider listenerValidProvider
     * @param $name
     * @param $callback
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldAddListener($name, $callback)
    {
        $listener = Event::listen($name, $callback);

        $this->assertTrue($listener);
    }

    /**
     * @dataProvider listenerInvalidProvider
     * @param $name
     * @param $callback
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldThrowExceptionForListener($name, $callback)
    {
        $this->expectException(\Roolith\Event\Exceptions\InvalidArgumentException::class);
        Event::listen($name, $callback);
    }

    public function testShouldAddMultipleListener()
    {
        $listener = Event::listeners(['a', 'n'], function (){});
        $this->assertTrue($listener);

        $this->expectException(\Roolith\Event\Exceptions\InvalidArgumentException::class);
        Event::listeners('a', function (){});
    }

    public function testShouldTriggerEvent()
    {
        Event::listen('event', function () {});
        $result = Event::trigger('event');

        $this->assertTrue($result);
    }

    public function testShouldTriggerEventWithParam()
    {
        $fnCalled = false;
        $param = '';

        $fn = function ($p) use (&$fnCalled, &$param) {
            $fnCalled = true;
            $param = $p;

            return $p;
        };

        Event::listen('event', $fn);
        Event::trigger('event', 'a');

        $this->assertTrue($fnCalled);
        $this->assertEquals('a', $param);
    }

    public function testShouldTriggerEventWithMultipleParam()
    {
        $fnCalled = false;
        $param1 = '';
        $param2 = '';

        $fn = function ($p1, $p2) use (&$fnCalled, &$param1, &$param2) {
            $fnCalled = true;
            $param1 = $p1;
            $param2 = $p2;

            return true;
        };

        Event::listen('event', $fn);
        Event::trigger('event', ['a', 'b']);

        $this->assertTrue($fnCalled);
        $this->assertEquals('a', $param1);
        $this->assertEquals('b', $param2);
    }

    public function testShouldListenWildcardEvent()
    {
        $loginEventListenCounter = 0;
        $logoutEventListenCounter = 0;
        $wildcardEventListenCounter = 0;

        Event::listen('event.login', function () use (&$loginEventListenCounter) {
            $loginEventListenCounter++;
        });

        Event::listen('event.logout', function () use (&$logoutEventListenCounter) {
            $logoutEventListenCounter++;
        });

        Event::listen('event.*', function () use (&$wildcardEventListenCounter) {
            $wildcardEventListenCounter++;
        });

        Event::trigger('event.login');
        Event::trigger('event.logout');

        $this->assertEquals(1, $loginEventListenCounter);
        $this->assertEquals(1, $logoutEventListenCounter);
        $this->assertEquals(2, $wildcardEventListenCounter);
    }

    public function listenerInvalidProvider()
    {
        return [
            ['!name', function () {}],
            ['name', ''],
        ];
    }

    public function listenerValidProvider()
    {
        $fn = function () {};

        return [
            ['test', $fn],
            ['test.name', $fn],
            ['test.*', $fn],
        ];
    }
}