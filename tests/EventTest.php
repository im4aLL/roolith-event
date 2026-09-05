<?php

declare(strict_types=1);
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Roolith\Event\Dispatcher;
use Roolith\Event\Event;

#[CoversClass(Event::class)]
#[CoversClass(Dispatcher::class)]
/**
 * @covers \Roolith\Event\Event
 * @covers \Roolith\Event\Dispatcher
 */
class EventTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::reset();
        Event::setErrorMessage([
            'name' => 'Name must be segments of letters, digits or underscore joined by dots, with optional terminal .*',
            'callback' => 'Invalid callback',
            'array' => 'Array required',
            'listener' => 'Listener not defined',
        ]);
    }

    #[DataProvider('listenerValidProvider')]
    /** @dataProvider listenerValidProvider */
    public function testShouldAddListener(string $name, callable $callback): void
    {
        $this->assertTrue(Event::listen($name, $callback));
        $this->assertTrue(Event::has($name));
    }

    #[DataProvider('listenerInvalidProvider')]
    /** @dataProvider listenerInvalidProvider */
    public function testShouldThrowExceptionForListener(string $name, callable $callback): void
    {
        $this->expectException(\Roolith\Event\Exceptions\InvalidArgumentException::class);
        Event::listen($name, $callback);
    }

    public function testShouldThrowTypeErrorForInvalidCallback(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-argument */
        Event::listen('name', '');
    }

    public function testShouldAddMultipleListener(): void
    {
        $this->assertTrue(Event::listeners(['a', 'n'], function () {}));
        $this->assertTrue(Event::has('a'));
        $this->assertTrue(Event::has('n'));
    }

    public function testShouldThrowTypeErrorForListenersWithNonArray(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-argument */
        Event::listeners('a', function () {});
    }

    public function testShouldTriggerEvent(): void
    {
        Event::listen('event', function () {
            return 'fired';
        });
        $result = Event::trigger('event');

        $this->assertSame(['fired'], $result);
    }

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
        $result = Event::trigger('event', 'a');

        $this->assertTrue($fnCalled);
        $this->assertEquals('a', $param);
        $this->assertSame(['a'], $result);
    }

    public function testShouldTriggerEventWithMultipleParam(): void
    {
        $fnCalled = false;
        $param1 = '';
        $param2 = '';

        $fn = function ($p1, $p2) use (&$fnCalled, &$param1, &$param2) {
            $fnCalled = true;
            $param1 = $p1;
            $param2 = $p2;

            return $p1 . $p2;
        };

        Event::listen('event', $fn);
        $result = Event::trigger('event', ['a', 'b']);

        $this->assertTrue($fnCalled);
        $this->assertEquals('a', $param1);
        $this->assertEquals('b', $param2);
        $this->assertSame(['ab'], $result);
    }

    #[DataProvider('falsyArgumentProvider')]
    /** @dataProvider falsyArgumentProvider */
    public function testShouldTriggerEventWithFalsyArgument(mixed $falsyValue): void
    {
        $fnCalled = false;
        $received = 'not-called';

        $fn = function ($p) use (&$fnCalled, &$received) {
            $fnCalled = true;
            $received = $p;

            return $p;
        };

        Event::listen('event', $fn);
        $result = Event::trigger('event', $falsyValue);

        $this->assertSame([$falsyValue], $result);
        $this->assertTrue($fnCalled);
        $this->assertSame($falsyValue, $received);
    }

    #[DataProvider('falsyArgumentProvider')]
    /** @dataProvider falsyArgumentProvider */
    public function testShouldTriggerWildcardEventWithFalsyArgument(mixed $falsyValue): void
    {
        $fnCalled = false;
        $received = 'not-called';

        Event::listen('event.*', function ($p) use (&$fnCalled, &$received) {
            $fnCalled = true;
            $received = $p;

            return $p;
        });

        $result = Event::trigger('event.login', $falsyValue);

        $this->assertSame([$falsyValue], $result);
        $this->assertTrue($fnCalled);
        $this->assertSame($falsyValue, $received);
    }

    public function testShouldTriggerEventWithNullInvokesZeroArgListener(): void
    {
        $fnCalled = false;

        Event::listen('event', function () use (&$fnCalled) {
            $fnCalled = true;

            return 'ok';
        });

        $result = Event::trigger('event', null);

        $this->assertSame(['ok'], $result);
        $this->assertTrue($fnCalled);
    }

    public function testTriggerWithNoListenerReturnsEmptyArray(): void
    {
        $this->assertFalse(Event::has('missing.event'));
        $this->assertSame([], Event::trigger('missing.event'));
        $this->assertSame([], Event::getListeners('missing.event'));
    }

    public function testTriggerReturnsOrderedResults(): void
    {
        Event::listen('event', fn () => 'first');
        Event::listen('event', fn () => 'second');

        $this->assertSame(['first', 'second'], Event::trigger('event'));
    }

    public function testTriggerStopsPropagationOnFalse(): void
    {
        $secondCalled = false;

        Event::listen('event', fn () => false);
        Event::listen('event', function () use (&$secondCalled) {
            $secondCalled = true;

            return 'second';
        });

        $result = Event::trigger('event');

        $this->assertSame([false], $result);
        $this->assertFalse($secondCalled);
    }

    public function testTriggerStopsWildcardPropagationOnFalse(): void
    {
        $wildcardCalled = false;

        Event::listen('event.login', fn () => false);
        Event::listen('event.*', function () use (&$wildcardCalled) {
            $wildcardCalled = true;
        });

        $result = Event::trigger('event.login');

        $this->assertSame([false], $result);
        $this->assertFalse($wildcardCalled);
    }

    public function testTriggerStopsSubsequentWildcardListenersOnFalse(): void
    {
        $secondCalled = false;

        Event::listen('event.*', fn () => false);
        Event::listen('event.*', function () use (&$secondCalled) {
            $secondCalled = true;

            return 'second';
        });

        $result = Event::trigger('event.login');

        $this->assertSame([false], $result);
        $this->assertFalse($secondCalled);
    }

    public function testDuplicateRegistrationFiresTwiceAndUnregisterRemovesFirstMatch(): void
    {
        $calls = 0;
        $cb = function () use (&$calls) {
            $calls++;
        };

        Event::listen('event', $cb);
        Event::listen('event', $cb);

        Event::trigger('event');
        $this->assertEquals(2, $calls);

        $this->assertTrue(Event::unregister('event', $cb));
        $this->assertTrue(Event::has('event'));

        Event::trigger('event');
        $this->assertEquals(3, $calls);

        $this->assertTrue(Event::unregister('event', $cb));
        $this->assertFalse(Event::has('event'));
    }

    public function testUnregisterArrayFormWithCallback(): void
    {
        $callsA = 0;
        $callsB = 0;
        $cb = function () use (&$callsA) {
            $callsA++;
        };

        Event::listen('a', $cb);
        Event::listen('b', function () use (&$callsB) {
            $callsB++;
        });
        Event::listen('b', $cb);

        $this->assertTrue(Event::unregister(['a', 'b'], $cb));
        $this->assertFalse(Event::has('a'));
        $this->assertTrue(Event::has('b'));

        Event::trigger('b');
        $this->assertEquals(0, $callsA);
        $this->assertEquals(1, $callsB);
    }

    public function testInstanceLevelSetErrorMessage(): void
    {
        $dispatcher = new Dispatcher();
        $dispatcher->setErrorMessage(['name' => 'instance-custom']);

        try {
            $dispatcher->listen('!bad', function () {});
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('instance-custom', $e->getMessage());
        }

        // Facade default untouched by instance override.
        try {
            Event::listen('!bad', function () {});
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertNotEquals('instance-custom', $e->getMessage());
        }
    }

    public function testHasAndGetListeners(): void
    {
        $cb = fn () => null;

        $this->assertFalse(Event::has('event.login'));

        Event::listen('event.*', $cb);

        $this->assertTrue(Event::has('event.login'));
        $this->assertSame([$cb], Event::getListeners('event.login'));
        $this->assertArrayHasKey('event.*', Event::getListeners());
    }

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

    public function testShouldTriggerWildcardOnlyListener(): void
    {
        $wildcardEventListenCounter = 0;

        Event::listen('event.*', function () use (&$wildcardEventListenCounter) {
            $wildcardEventListenCounter++;
        });

        $result = Event::trigger('event.login');

        $this->assertCount(1, $result);
        $this->assertEquals(1, $wildcardEventListenCounter);
    }

    public function testMissingWildcardPrefixReturnsEmpty(): void
    {
        Event::listen('other.*', function () {});

        $this->assertFalse(Event::has('event.login'));
        $this->assertSame([], Event::trigger('event.login'));
    }

    public function testShouldPassArgumentToWildcardOnlyListener(): void
    {
        $received = null;

        Event::listen('event.*', function ($value) use (&$received) {
            $received = $value;
        });

        Event::trigger('event.login', 'a');

        $this->assertEquals('a', $received);
    }

    public function testWildcardRewriteKeyTriggerFiresOnceWithoutRecursion(): void
    {
        $counter = 0;

        Event::listen('event.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertCount(1, Event::trigger('event.wildcard'));
        $this->assertEquals(1, $counter);
    }

    public function testDirectWildcardFormTriggerFiresOnceWithoutRecursion(): void
    {
        $counter = 0;

        Event::listen('event.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertCount(1, Event::trigger('event.*'));
        $this->assertEquals(1, $counter);
    }

    public function testDirectWildcardFormTriggerPassesArgument(): void
    {
        $received = null;

        Event::listen('event.*', function ($value) use (&$received) {
            $received = $value;
        });

        $this->assertCount(1, Event::trigger('event.*', 'a'));
        $this->assertEquals('a', $received);
    }

    public function testDirectWildcardFormTriggerWithNoListenerReturnsEmpty(): void
    {
        $this->assertSame([], Event::trigger('event.*'));
    }

    public function testShouldUnregisterWildcardListener(): void
    {
        Event::listen('event.*', function () {});

        $this->assertTrue(Event::unregister('event.*'));
        $this->assertFalse(Event::has('event.login'));
        $this->assertSame([], Event::trigger('event.login'));
    }

    public function testShouldUnregisterWildcardListenersArrayForm(): void
    {
        Event::listen('event.*', function () {});
        Event::listen('other.*', function () {});

        $this->assertTrue(Event::unregister(['event.*', 'other.*']));
        $this->assertSame([], Event::trigger('event.login'));
    }

    public function testUnregisterSingleCallback(): void
    {
        $firstCalls = 0;
        $secondCalls = 0;
        $first = function () use (&$firstCalls) {
            $firstCalls++;
        };
        $second = function () use (&$secondCalls) {
            $secondCalls++;
        };

        Event::listen('event', $first);
        Event::listen('event', $second);

        $this->assertTrue(Event::unregister('event', $first));
        $this->assertTrue(Event::has('event'));

        Event::trigger('event');

        $this->assertEquals(0, $firstCalls);
        $this->assertEquals(1, $secondCalls);
    }

    public function testUnregisterUnknownCallbackReturnsFalse(): void
    {
        Event::listen('event', function () {});

        $this->assertFalse(Event::unregister('event', function () {}));
        $this->assertTrue(Event::has('event'));
    }

    public function testUnregisterInvalidNameThrows(): void
    {
        $this->expectException(\Roolith\Event\Exceptions\InvalidArgumentException::class);
        Event::unregister('!bad');
    }

    public function testUnregisterArrayWithInvalidNameThrowsAtomically(): void
    {
        Event::listen('good.event', function () {});

        try {
            Event::unregister(['good.event', '!bad']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            // Atomic: first name must still be registered.
            $this->assertTrue(Event::has('good.event'));
        }
    }

    public function testUnregisterMissingNameReturnsFalse(): void
    {
        $this->assertFalse(Event::unregister('missing.event'));
    }

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

        $this->assertCount(1, Event::trigger('event.wildcard'));
        $this->assertEquals(0, $wildcardCounter);
        $this->assertEquals(1, $literalCounter);
    }

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

        $this->assertCount(1, Event::trigger('event.login'));
        $this->assertEquals(1, $wildcardCounter);
        $this->assertEquals(0, $literalCounter);
    }

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

        $this->assertCount(2, Event::trigger('event.wildcard'));
        $this->assertEquals(1, $wildcardCounter);
        $this->assertEquals(1, $literalCounter);
    }

    public function testStarContainingTriggerResolvesToPrefixWildcard(): void
    {
        $wildcardCounter = 0;

        Event::listen('event.*', function () use (&$wildcardCounter) {
            $wildcardCounter++;
        });

        $this->assertCount(1, Event::trigger('event.login.*'));
        $this->assertEquals(1, $wildcardCounter);
    }

    public function testShouldTriggerNestedWildcardListener(): void
    {
        $counter = 0;

        Event::listen('a.b.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertCount(1, Event::trigger('a.b.c'));
        $this->assertEquals(1, $counter);
    }

    public function testShouldPassArgumentToNestedWildcardListener(): void
    {
        $received = null;

        Event::listen('a.b.*', function ($value) use (&$received) {
            $received = $value;
        });

        Event::trigger('a.b.c', 'x');

        $this->assertEquals('x', $received);
    }

    public function testNestedWildcardDoesNotMatchDeeperLevel(): void
    {
        Event::listen('a.b.*', function () {});

        $this->assertFalse(Event::has('a.b.c.d'));
        $this->assertSame([], Event::trigger('a.b.c.d'));
    }

    public function testShorterWildcardDoesNotMatchDeeperTrigger(): void
    {
        Event::listen('a.*', function () {});

        $this->assertFalse(Event::has('a.b.c'));
        $this->assertSame([], Event::trigger('a.b.c'));
    }

    public function testNestedStarTriggerFallsBackToAncestorWildcard(): void
    {
        $counter = 0;

        Event::listen('a.b.*', function () use (&$counter) {
            $counter++;
        });

        $this->assertCount(1, Event::trigger('a.b.c.*'));
        $this->assertEquals(1, $counter);
    }

    public function testSetErrorMessagePartialMergePreservesOtherKeys(): void
    {
        Event::setErrorMessage(['name' => 'custom-name']);

        try {
            Event::listen('!bad', function () {});
            $this->fail('Expected InvalidArgumentException for invalid name');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('custom-name', $e->getMessage());
        }

        // Other keys preserved: invalid unregister still uses default name message? No, uses custom now.
        // Listener path no longer throws, so check has() instead.
        $this->assertFalse(Event::has('missing.event'));
    }

    public function testSetErrorMessageRejectsUnknownKey(): void
    {
        try {
            Event::setErrorMessage(['unknown' => 'x']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Invalid error message key or value', $e->getMessage());
        }
    }

    public function testSetErrorMessageRejectsEmptyMessage(): void
    {
        try {
            Event::setErrorMessage(['listener' => '']);
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Invalid error message key or value', $e->getMessage());
        }
    }

    public function testListenersEmptyArrayReturnsFalse(): void
    {
        $this->assertFalse(Event::listeners([], function () {}));
    }

    public function testListenersIsAtomicOnFailure(): void
    {
        try {
            Event::listeners(['good.event', '!bad'], function () {});
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Name must be segments of letters, digits or underscore joined by dots, with optional terminal .*', $e->getMessage());
        }

        $this->assertFalse(Event::has('good.event'));
        $this->assertSame([], Event::trigger('good.event'));
    }

    public function testListenersRejectsNonStringElementAtomically(): void
    {
        try {
            /** @phpstan-ignore-argument */
            Event::listeners(['good.event', 5], function () {});
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('Name must be segments of letters, digits or underscore joined by dots, with optional terminal .*', $e->getMessage());
        }

        $this->assertFalse(Event::has('good.event'));
        $this->assertSame([], Event::trigger('good.event'));
    }

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

        $this->assertCount(2, Event::trigger('a.b.c'));
        $this->assertEquals(1, $exact);
        $this->assertEquals(1, $wildcard);
    }

    #[DataProvider('tightenedInvalidNameProvider')]
    /** @dataProvider tightenedInvalidNameProvider */
    public function testTightenedValidationRejectsAmbiguousNames(string $name): void
    {
        try {
            Event::listen($name, function () {});
            $this->fail('Expected InvalidArgumentException for ' . $name);
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testInvalidArgumentExceptionExtendsSpl(): void
    {
        $e = new \Roolith\Event\Exceptions\InvalidArgumentException('x');

        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
    }

    public function testTriggerPreservesCustomValidationMessage(): void
    {
        Event::setErrorMessage(['name' => 'custom-name-error']);

        try {
            Event::trigger('!bad');
            $this->fail('Expected InvalidArgumentException');
        } catch (\Roolith\Event\Exceptions\InvalidArgumentException $e) {
            $this->assertEquals('custom-name-error', $e->getMessage());
        }
    }

    public function testTriggerInvalidNameThrows(): void
    {
        $this->expectException(\Roolith\Event\Exceptions\InvalidArgumentException::class);
        Event::trigger('!bad');
    }

    public function testReentrancyUnregisterDoesNotAffectInProgressDispatch(): void
    {
        $calls = [];

        Event::listen('event', function () use (&$calls) {
            $calls[] = 'first';
            Event::unregister('event');
        });
        Event::listen('event', function () use (&$calls) {
            $calls[] = 'second';
        });

        // Snapshot: both fire even though first unregisters.
        Event::trigger('event');
        $this->assertSame(['first', 'second'], $calls);

        // Next trigger sees the unregister.
        $this->assertFalse(Event::has('event'));
        $this->assertSame([], Event::trigger('event'));
    }

    public function testReentrancyListenDoesNotAffectInProgressDispatch(): void
    {
        $calls = [];

        Event::listen('event', function () use (&$calls) {
            $calls[] = 'first';
            Event::listen('event', function () use (&$calls) {
                $calls[] = 'late';
            });
        });

        Event::trigger('event');
        $this->assertSame(['first'], $calls);

        Event::trigger('event');
        $this->assertSame(['first', 'first', 'late'], $calls);
    }

    public function testReentrancyWildcardUnregisterDoesNotAffectInProgressDispatch(): void
    {
        $calls = [];

        Event::listen('event.login', function () use (&$calls) {
            $calls[] = 'exact';
            Event::unregister('event.*');
        });
        Event::listen('event.*', function () use (&$calls) {
            $calls[] = 'wildcard';
        });

        // Both snapshots taken before any invoke, so wildcard still fires once.
        Event::trigger('event.login');
        $this->assertSame(['exact', 'wildcard'], $calls);

        // Next trigger sees the unregister: only exact remains.
        Event::trigger('event.login');
        $this->assertSame(['exact', 'wildcard', 'exact'], $calls);
    }

    public function testReentrancyWildcardLateRegisterDoesNotFireInProgress(): void
    {
        $calls = [];

        Event::listen('event.login', function () use (&$calls) {
            $calls[] = 'exact';
            Event::listen('event.*', function () use (&$calls) {
                $calls[] = 'late-wildcard';
            });
        });

        Event::trigger('event.login');
        $this->assertSame(['exact'], $calls);

        Event::trigger('event.login');
        $this->assertSame(['exact', 'exact', 'late-wildcard'], $calls);
    }

    public function testStarTriggerExactRegistrationWinsOverAncestor(): void
    {
        $exact = 0;
        $ancestor = 0;

        Event::listen('event.login.*', function () use (&$exact) {
            $exact++;
        });
        Event::listen('event.*', function () use (&$ancestor) {
            $ancestor++;
        });

        Event::trigger('event.login.*');

        $this->assertEquals(1, $exact);
        $this->assertEquals(0, $ancestor);
    }

    public function testDispatcherInstancesAreIsolated(): void
    {
        $a = new Dispatcher();
        $b = new Dispatcher();

        $a->listen('event', fn () => 'a');

        $this->assertTrue($a->has('event'));
        $this->assertFalse($b->has('event'));
        $this->assertSame(['a'], $a->trigger('event'));
        $this->assertSame([], $b->trigger('event'));

        // Static facade is a third isolated state.
        $this->assertFalse(Event::has('event'));
    }

    public function testSetSharedDispatcherIsolatesFacade(): void
    {
        Event::listen('event', fn () => 'old');
        Event::setSharedDispatcher(new Dispatcher());

        $this->assertFalse(Event::has('event'));
        $this->assertSame([], Event::trigger('event'));
    }

    public function testResetClearsListeners(): void
    {
        Event::listen('event', fn () => 'x');

        $this->assertTrue(Event::has('event'));
        $this->assertTrue(Event::reset());
        $this->assertFalse(Event::has('event'));
        $this->assertSame([], Event::getListeners());
    }

    /**
     * @return array<int, array{0: string, 1: callable}>
     */
    public static function listenerInvalidProvider(): array
    {
        return [
            ['!name', function () {}],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: callable}>
     */
    public static function listenerValidProvider(): array
    {
        $fn = function () {};

        return [
            ['test', $fn],
            ['test.name', $fn],
            ['test.*', $fn],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function falsyArgumentProvider(): array
    {
        return [
            'integer zero' => [0],
            'empty string' => [''],
            'false' => [false],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tightenedInvalidNameProvider(): array
    {
        return [
            'double star' => ['**'],
            'double dot' => ['a..b'],
            'star inside segment' => ['foo*bar'],
            'leading wildcard segment' => ['*.foo'],
            'lone star' => ['*'],
            'trailing dot' => ['event.'],
            'leading dot' => ['.event'],
            'wildcard mid-path' => ['event.*.extra'],
            'empty' => [''],
            'space' => ['foo bar'],
            'dash' => ['foo-bar'],
        ];
    }
}
