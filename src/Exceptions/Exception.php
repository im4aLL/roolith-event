<?php

declare(strict_types=1);
namespace Roolith\Event\Exceptions;

/**
 * Legacy event exception.
 *
 * Kept for backwards compatibility. Since v2 `Dispatcher::trigger()`
 * no longer throws when no listener matches (it returns an empty array),
 * so this exception is no longer raised by the dispatcher itself.
 *
 * @deprecated No longer thrown by trigger(). Check has() before triggering if needed.
 */
class Exception extends \Exception
{
}