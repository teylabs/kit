<?php

namespace Tey\Kit\Features\Core\Workbench;

use InvalidArgumentException;

/** A workbench key names a kind of workbench (`deck-editor`), not a record: lowercase letters, digits, `.`, `_` and `-`, at most 48 characters. */
final class WorkbenchKey
{
    public const PATTERN = '[a-z0-9][a-z0-9._-]{0,47}';

    public static function valid(string $key): bool
    {
        return preg_match('/^'.self::PATTERN.'$/', $key) === 1;
    }

    public static function assert(string $key): string
    {
        if (! self::valid($key)) {
            throw new InvalidArgumentException('A workbench key is lowercase letters, digits, ".", "_" and "-", at most 48 characters: '.$key);
        }

        return $key;
    }
}
