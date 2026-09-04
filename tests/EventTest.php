<?php
use PHPUnit\Framework\TestCase;
use Roolith\Event\Event;

class EventTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::reset();
    }

    /**
     * Should add a listener.
     *
     * @param string $name Event name.
     * @param callable $callback Listener callback.
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     * @dataProvider listenerValidProvider
     */
    public function testShouldAddListener(string $name, callable $callback): void
    {
        $listener = Event::listen($name, $callback);

        $this->assertTrue($listener);
    }

    /**
     * Should throw for invalid event name.
     *
     * @param string $name Invalid event name.
     * @param callable $callback Listener callback.
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     * @dataProvider listenerInvalidProvider
     */
    public function testShouldThrowExceptionForListener(string $name, callable $callback): void
    {
        $this->expectException(\Roolith\Event\Exceptions\InvalidArgumentException::class);
        Event::listen($name, $callback);
    }

    /**
     * Should throw TypeError for non-callable callback.
     *
     * @return void
     */
    public function testShouldThrowTypeErrorForInvalidCallback(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-argument */
        Event::listen('name', '');
    }

    /**
     * Should add multiple listeners.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldAddMultipleListener(): void
    {
        $listener = Event::listeners(['a', 'n'], function (){});
        $this->assertTrue($listener);
    }

    /**
     * Should throw TypeError when listeners() receives a non-array.
     *
     * @return void
     */
    public function testShouldThrowTypeErrorForListenersWithNonArray(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-argument */
        Event::listeners('a', function (){});
    }

    /**
     * Should trigger an event.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldTriggerEvent(): void
    {
        Event::listen('event', function () {});
        $result = Event::trigger('event');

        $this->assertTrue($result);
    }

    /**
     * Should trigger an event with a single param.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldTriggerEventWithParam(): void
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

    /**
     * Should trigger an event with multiple params.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldTriggerEventWithMultipleParam(): void
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

    /**
     * Should listen to wildcard events.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldListenWildcardEvent(): void
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

    /**
     * Should trigger a wildcard-only listener.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldTriggerWildcardOnlyListener(): void
    {
        $wildcardEventListenCounter = 0;

        Event::listen('event.*', function () use (&$wildcardEventListenCounter) {
            $wildcardEventListenCounter++;
        });

        $result = Event::trigger('event.login');

        $this->assertTrue($result);
        $this->assertEquals(1, $wildcardEventListenCounter);
    }

    /**
     * Should still throw when wildcard prefix does not match.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldStillThrowWhenWildcardPrefixDoesNotMatch(): void
    {
        Event::listen('other.*', function () {});

        $this->expectException(\Roolith\Event\Exceptions\Exception::class);
        Event::trigger('event.login');
    }

    /**
     * Should pass argument to wildcard-only listener.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldPassArgumentToWildcardOnlyListener(): void
    {
        $received = null;

        Event::listen('event.*', function ($value) use (&$received) {
            $received = $value;
        });

        Event::trigger('event.login', 'a');

        $this->assertEquals('a', $received);
    }

    /**
     * Wildcard rewrite key trigger fires once without recursion.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testWildcardRewriteKeyTriggerFiresOnceWithoutRecursion(): void
    {
        $counter = 0;

        Event::listen('event.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertTrue(Event::trigger('event.wildcard'));
        $this->assertEquals(1, $counter);
    }

    /**
     * Direct wildcard form trigger fires once without recursion.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testDirectWildcardFormTriggerFiresOnceWithoutRecursion(): void
    {
        $counter = 0;

        Event::listen('event.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertTrue(Event::trigger('event.*'));
        $this->assertEquals(1, $counter);
    }

    /**
     * Direct wildcard form trigger passes argument to listener.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testDirectWildcardFormTriggerPassesArgument(): void
    {
        $received = null;

        Event::listen('event.*', function ($value) use (&$received) {
            $received = $value;
        });

        $this->assertTrue(Event::trigger('event.*', 'a'));
        $this->assertEquals('a', $received);
    }

    /**
     * Direct wildcard form trigger throws when no listener registered.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testDirectWildcardFormTriggerThrowsWhenNoListener(): void
    {
        $this->expectException(\Roolith\Event\Exceptions\Exception::class);
        Event::trigger('event.*');
    }

    /**
     * Should unregister a wildcard listener using wildcard form.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldUnregisterWildcardListener(): void
    {
        Event::listen('event.*', function () {});

        $this->assertTrue(Event::unregister('event.*'));

        $this->expectException(\Roolith\Event\Exceptions\Exception::class);
        Event::trigger('event.login');
    }

    /**
     * Should unregister wildcard listeners using array form.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldUnregisterWildcardListenersArrayForm(): void
    {
        Event::listen('event.*', function () {});
        Event::listen('other.*', function () {});

        $this->assertTrue(Event::unregister(['event.*', 'other.*']));

        $this->expectException(\Roolith\Event\Exceptions\Exception::class);
        Event::trigger('event.login');
    }

    /**
     * Literal wildcard segment listener must not fire on other names under the prefix.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testLiteralWildcardSegmentDoesNotFireAsWildcard(): void
    {
        $wildcardCounter = 0;
        $literalCounter = 0;

        Event::listen('event.*', function () use (&$wildcardCounter) {
            $wildcardCounter++;
        });

        Event::listen('event.wildcard', function () use (&$literalCounter) {
            $literalCounter++;
        });

        Event::trigger('event.login');

        $this->assertEquals(1, $wildcardCounter);
        $this->assertEquals(0, $literalCounter);
    }

    /**
     * Unregistering wildcard form must leave literal wildcard segment listener intact.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testUnregisterWildcardLeavesLiteralWildcardSegment(): void
    {
        $wildcardCounter = 0;
        $literalCounter = 0;

        Event::listen('event.*', function () use (&$wildcardCounter) {
            $wildcardCounter++;
        });

        Event::listen('event.wildcard', function () use (&$literalCounter) {
            $literalCounter++;
        });

        $this->assertTrue(Event::unregister('event.*'));

        $this->assertTrue(Event::trigger('event.wildcard'));
        $this->assertEquals(0, $wildcardCounter);
        $this->assertEquals(1, $literalCounter);
    }

    /**
     * Unregistering literal wildcard segment must leave wildcard listener intact.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testUnregisterLiteralWildcardSegmentLeavesWildcard(): void
    {
        $wildcardCounter = 0;
        $literalCounter = 0;

        Event::listen('event.*', function () use (&$wildcardCounter) {
            $wildcardCounter++;
        });

        Event::listen('event.wildcard', function () use (&$literalCounter) {
            $literalCounter++;
        });

        $this->assertTrue(Event::unregister('event.wildcard'));

        $this->assertTrue(Event::trigger('event.login'));
        $this->assertEquals(1, $wildcardCounter);
        $this->assertEquals(0, $literalCounter);
    }

    /**
     * Triggering literal wildcard segment fires both literal and wildcard listeners.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testTriggerLiteralWildcardSegmentFiresBothListeners(): void
    {
        $wildcardCounter = 0;
        $literalCounter = 0;

        Event::listen('event.*', function () use (&$wildcardCounter) {
            $wildcardCounter++;
        });

        Event::listen('event.wildcard', function () use (&$literalCounter) {
            $literalCounter++;
        });

        $this->assertTrue(Event::trigger('event.wildcard'));
        $this->assertEquals(1, $wildcardCounter);
        $this->assertEquals(1, $literalCounter);
    }

    /**
     * Star-containing trigger still resolves to its prefix wildcard without recursion.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testStarContainingTriggerResolvesToPrefixWildcard(): void
    {
        $wildcardCounter = 0;

        Event::listen('event.*', function () use (&$wildcardCounter) {
            $wildcardCounter++;
        });

        $this->assertTrue(Event::trigger('event.login.*'));
        $this->assertEquals(1, $wildcardCounter);
    }

    /**
     * Invalid listener provider.
     *
     * @return array<int, array{0: string, 1: callable}>
     */
    public function listenerInvalidProvider(): array
    {
        return [
            ['!name', function () {}],
        ];
    }

    /**
     * Valid listener provider.
     *
     * @return array<int, array{0: string, 1: callable}>
     */
    public function listenerValidProvider(): array
    {
        $fn = function () {};

        return [
            ['test', $fn],
            ['test.name', $fn],
            ['test.*', $fn],
        ];
    }
}
