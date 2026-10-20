<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Str;
use RuntimeException;
use Tey\Kit\Features\Workspaces\Models\Workspace;

/**
 * A workspace's slug is the segment after the `@` that marks a tenant (`/@acme/notes`). The `@` is the only marker, so
 * the rest of the root belongs to the app and a slug needs no reserved list; it only has to be well formed and free.
 */
final class Slugs
{
    /** The route constraint for `{workspace}`, and what a slug may be: lowercase letters, digits and single hyphens between them. */
    public const PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public static function valid(string $slug): bool
    {
        return strlen($slug) <= 63 && preg_match('/\A'.self::PATTERN.'\z/', $slug) === 1;
    }

    /** Refuses a slug that is malformed or taken by another workspace. */
    public static function assertAvailable(string $slug, ?int $except = null): void
    {
        if (! self::valid($slug)) {
            throw new RuntimeException('slug_invalid');
        }
        if (Workspace::query()->where('slug', $slug)->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            throw new RuntimeException('slug_taken');
        }
    }

    /** A free slug for a name: its slug, or that with a number after it when it is empty or taken. */
    public static function unique(string $name): string
    {
        $base = Str::limit(Str::slug($name), 56, '');
        $base = $base === '' ? 'workspace' : $base;
        for ($attempt = 1;; $attempt++) {
            $candidate = $attempt === 1 ? $base : $base.'-'.$attempt;
            try {
                self::assertAvailable($candidate);

                return $candidate;
            } catch (RuntimeException) {
            }
        }
    }
}
