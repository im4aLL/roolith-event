<?php

declare(strict_types=1);
namespace Roolith\Event\Exceptions;

/**
 * Invalid event argument.
 *
 * Extends SPL InvalidArgumentException so standard
 * catch blocks for \InvalidArgumentException keep working.
 */
class InvalidArgumentException extends \InvalidArgumentException
{
}