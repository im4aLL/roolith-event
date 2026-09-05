<?php
use PHPUnit\Framework\TestCase;
use Roolith\Event\Event;

class EventTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::reset();
        Event::setErrorMessage([
            'name' => 'Name characters should contain alphanumeric with ., * and _',
            'callback' => 'Invalid callback',
            'array' => 'Array required',
            'listener' => 'Listener not defined',
        ]);
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
     * Should trigger an event with falsy single args.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     * @dataProvider falsyArgumentProvider
     */
    public function testShouldTriggerEventWithFalsyArgument(mixed $falsyValue): void
    {
        $fnCalled = false;
        $received = 'not-called';

        $fn = function ($p) use (&$fnCalled, &$received) {
            $fnCalled = true;
            $received = $p;
        };

        Event::listen('event', $fn);
        $result = Event::trigger('event', $falsyValue);

        $this->assertTrue($result);
        $this->assertTrue($fnCalled);
        $this->assertSame($falsyValue, $received);
    }

    /**
     * Should trigger wildcard listener with falsy single args.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     * @dataProvider falsyArgumentProvider
     */
    public function testShouldTriggerWildcardEventWithFalsyArgument(mixed $falsyValue): void
    {
        $fnCalled = false;
        $received = 'not-called';

        Event::listen('event.*', function ($p) use (&$fnCalled, &$received) {
            $fnCalled = true;
            $received = $p;
        });

        $result = Event::trigger('event.login', $falsyValue);

        $this->assertTrue($result);
        $this->assertTrue($fnCalled);
        $this->assertSame($falsyValue, $received);
    }

    /**
     * Null argument invokes zero-arg listener (null is the no-argument sentinel).
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldTriggerEventWithNullInvokesZeroArgListener(): void
    {
        $fnCalled = false;

        Event::listen('event', function () use (&$fnCalled) {
            $fnCalled = true;
        });

        $result = Event::trigger('event', null);

        $this->assertTrue($result);
        $this->assertTrue($fnCalled);
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
     * Nested wildcard listener fires on immediate child.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldTriggerNestedWildcardListener(): void
    {
        $counter = 0;

        Event::listen('a.b.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertTrue(Event::trigger('a.b.c'));
        $this->assertEquals(1, $counter);
    }

    /**
     * Nested wildcard passes argument to listener.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShouldPassArgumentToNestedWildcardListener(): void
    {
        $received = null;

        Event::listen('a.b.*', function ($value) use (&$received) {
            $received = $value;
        });

        Event::trigger('a.b.c', 'x');

        $this->assertEquals('x', $received);
    }

    /**
     * Nested wildcard does not fire for deeper levels (single-level).
     *
     * Single-level semantics are intentional: `a.b.*` matches `a.b.c`
     * but not `a.b.c.d`. Note this tightens the old first-segment
     * behavior where `a.*` matched any depth under `a.`.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testNestedWildcardDoesNotMatchDeeperLevel(): void
    {
        Event::listen('a.b.*', function () {});

        try {
            Event::trigger('a.b.c.d');
            $this->fail('Expected Exception for deeper level');
        } catch (\Roolith\Event\Exceptions\Exception $e) {
            $this->assertEquals('Listener not defined', $e->getMessage());
        }
    }

    /**
     * Shorter wildcard does not fire for deeper trigger (single-level).
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testShorterWildcardDoesNotMatchDeeperTrigger(): void
    {
        Event::listen('a.*', function () {});

        try {
            Event::trigger('a.b.c');
            $this->fail('Expected Exception for deeper trigger');
        } catch (\Roolith\Event\Exceptions\Exception $e) {
            $this->assertEquals('Listener not defined', $e->getMessage());
        }
    }

    /**
     * Star-containing nested trigger falls back to ancestor wildcard.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testNestedStarTriggerFallsBackToAncestorWildcard(): void
    {
        $counter = 0;

        Event::listen('a.b.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertTrue(Event::trigger('a.b.c.*'));
        $this->assertEquals(1, $counter);
    }

    /**
     * Partial setErrorMessage merges and preserves other keys.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testSetErrorMessagePartialMergePreservesOtherKeys(): void
    {
        Event::setErrorMessage(['listener' => 'custom-missing']);

        try {
            Event::trigger('missing.event');
            $this->fail('Expected Exception for missing listener');
        } catch (\Roolith\Event\Exceptions\Exception $e) {
            $this->assertEquals('custom-missing', $e->getMessage());
        }

        try {
            Event::listen('!bad', function () {});
            $this->fail('Expected InvalidArgumentException for invalid name');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Name characters should contain alphanumeric with ., * and _', $e->getMessage());
        }
    }

    /**
     * setErrorMessage rejects unknown keys.
     *
     * @return void
     */
    public function testSetErrorMessageRejectsUnknownKey(): void
    {
        try {
            Event::setErrorMessage(['unknown' => 'x']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Invalid error message key or value', $e->getMessage());
        }
    }

    /**
     * setErrorMessage rejects empty message.
     *
     * @return void
     */
    public function testSetErrorMessageRejectsEmptyMessage(): void
    {
        try {
            Event::setErrorMessage(['listener' => '']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Invalid error message key or value', $e->getMessage());
        }
    }

    /**
     * Empty listeners list returns false and registers nothing.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testListenersEmptyArrayReturnsFalse(): void
    {
        $this->assertFalse(Event::listeners([], function () {}));
    }

    /**
     * listeners() is atomic: failure leaves nothing registered.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testListenersIsAtomicOnFailure(): void
    {
        try {
            Event::listeners(['good.event', '!bad'], function () {});
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Name characters should contain alphanumeric with ., * and _', $e->getMessage());
        }

        try {
            Event::trigger('good.event');
            $this->fail('Expected Exception for unregistered event');
        } catch (\Roolith\Event\Exceptions\Exception $e) {
            $this->assertEquals('Listener not defined', $e->getMessage());
        }
    }

    /**
     * listeners() rejects non-string element atomically.
     *
     * @return void
     */
    public function testListenersRejectsNonStringElementAtomically(): void
    {
        try {
            /** @phpstan-ignore-argument */
            Event::listeners(['good.event', 5], function () {});
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Name characters should contain alphanumeric with ., * and _', $e->getMessage());
        }

        try {
            Event::trigger('good.event');
            $this->fail('Expected Exception for unregistered event');
        } catch (\Roolith\Event\Exceptions\Exception $e) {
            $this->assertEquals('Listener not defined', $e->getMessage());
        }
    }

    /**
     * Exact plus nested wildcard listeners co-fire on exact trigger.
     *
     * @return void
     * @throws \Roolith\Event\Exceptions\Exception
     * @throws \Roolith\Event\Exceptions\InvalidArgumentException
     */
    public function testExactPlusNestedWildcardCoFire(): void
    {
        $exact = 0;
        $wildcard = 0;

        Event::listen('a.b.c', function () use (&$exact) {
            $exact++;
        });
        Event::listen('a.b.*', function () use (&$wildcard) {
            $wildcard++;
        });

        $this->assertTrue(Event::trigger('a.b.c'));
        $this->assertEquals(1, $exact);
        $this->assertEquals(1, $wildcard);
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

    /**
     * Falsy argument provider.
     *
     * @return array<string, array{0: mixed}>
     */
    public function falsyArgumentProvider(): array
    {
        return [
            'integer zero' => [0],
            'empty string' => [''],
            'false' => [false],
        ];
    }
}
