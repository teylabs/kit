<?php

namespace Tey\Kit\Features;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;
use Tey\Mod\Maintenance\InstalledRecord;

/**
 * What the composition root reads from the installed lineage record on every request, cached by `php artisan optimize` (`kit:cache`) and
 * removed by `optimize:clear` (`kit:clear`): per app id, the adopted lineages, whether session-ui carries workspace-ui, and the host tables
 * the workspaces lineage adopted.
 *
 * Reading and verifying the record costs tens of milliseconds a request. The cache holds an entry only while the record is unchanged:
 * each entry keeps a fingerprint of the record's files and of the installed kit, re-taken on every read with a few lstat calls and a hash of
 * the record's small JSON files. Any difference (kit:add, kit:update, kit:remove, a hand edit, a composer update) misses, and the live read
 * runs with its own verification and errors exactly as without a cache. A missing, unreadable or foreign file is a miss too.
 *
 * The fingerprint holds nothing a copy of the checkout changes: no ctime, inode or device. A host that runs `optimize` in its build and
 * then extracts or copies the image (Laravel Cloud) gives every file a new ctime, and often a new inode, while its mtime, size and contents
 * stay; the cache must still hit there. What each path contributes is in stats().
 */
final class DiscoveryCache
{
    /** Bumped when an entry's shape changes, so an older file is a miss. */
    private const VERSION = 5;

    /** The kit's files the cached answers depend on, relative to the package root. */
    private const SOURCES = [
        'composer.json',
        'src/Catalog.php',
        'src/Lineages.php',
        'src/Features/FeaturesServiceProvider.php',
        'src/Features/DiscoveryCache.php',
        'resources/features/session-ui/feature.json',
    ];

    /** @var array<string, array<string, mixed>|null> the file's entries, read once per process and path */
    private static array $files = [];

    public static function path(Application $app): string
    {
        return $app->bootstrapPath('cache/kit-features.php');
    }

    /**
     * The cached entry for this app id when its fingerprint still matches, else null.
     *
     * @return array{lineages: list<string>, carry: bool, tables: list<string>|null}|null
     */
    public static function entry(Application $app, string $appId): ?array
    {
        $path = self::path($app);
        if (! array_key_exists($path, self::$files)) {
            self::$files[$path] = self::read($path);
        }
        $entry = self::$files[$path][$appId] ?? null;
        if (! is_array($entry) || ($entry['fingerprint'] ?? null) !== self::fingerprint($app->basePath())) {
            return null;
        }

        return self::valid($entry);
    }

    /**
     * The entry in its exact shape, or null: a list of strings for the lineages, a bool for the carry, and a list of strings or null for
     * the tables. Anything else is a miss, so the request reads the record live.
     *
     * @param  array<mixed>  $entry
     * @return array{lineages: list<string>, carry: bool, tables: list<string>|null}|null
     */
    private static function valid(array $entry): ?array
    {
        if (! array_key_exists('lineages', $entry) || ! array_key_exists('carry', $entry) || ! array_key_exists('tables', $entry)) {
            return null;
        }
        if (! self::strings($entry['lineages']) || ! is_bool($entry['carry']) || ($entry['tables'] !== null && ! self::strings($entry['tables']))) {
            return null;
        }

        return ['lineages' => $entry['lineages'], 'carry' => $entry['carry'], 'tables' => $entry['tables']];
    }

    private static function strings(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value;
    }

    /**
     * Writes the cache for one app id from `$discover`, the live read (written whole, then moved into place). A record that cannot be
     * read throws and nothing is written, as does a record that keeps changing.
     *
     * The order makes the stored answers belong to the files the stored fingerprint describes:
     * 1. the before state: the fingerprint and a hash of every fingerprinted file's contents;
     * 2. the read;
     * 3. a wait until the clock has passed every fingerprinted file's mtime (stat times have one-second resolution);
     * 4. the after state, taken the same way, which is stored. A different before and after repeats the read.
     * A write during the read changes the contents hash, even a same-second, same-size rewrite that keeps the fingerprint. A write after
     * step 4 lands in a later second than every mtime fingerprinted, so its mtime differs and requests miss; a write under `.mod-maintenance`
     * that also restores its mtime still misses on its contents hash, which requests re-take. The kit's sources and installed.json are
     * hashed here, at cache build time, never per request.
     *
     * @param  Closure(): array{lineages: list<string>, carry: bool, tables: list<string>|null}  $discover
     */
    public static function write(Application $app, string $appId, Closure $discover): void
    {
        $path = self::path($app);
        $root = $app->basePath();
        for ($attempt = 0; ; $attempt++) {
            if ($attempt === 5) {
                throw new RuntimeException('The installed record kept changing while it was read; nothing was cached.');
            }
            $before = [self::fingerprint($root), self::contents($root)];
            $entry = self::valid($discover());
            if ($entry === null) {
                throw new RuntimeException('The discovered answers are not in the cache\'s shape; nothing was cached.');
            }
            // A file dated more than three seconds ahead (clock skew) is never waited out: nothing is cached.
            $deadline = microtime(true) + 3;
            while (self::newest($root) >= time()) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('An installed record is dated in the future; nothing was cached.');
                }
                usleep(50_000);
            }
            $after = [self::fingerprint($root), self::contents($root)];
            if ($after === $before) {
                break;
            }
        }
        $fingerprint = $after[0];
        $contents = '<?php return '.var_export(['version' => self::VERSION, 'entries' => [$appId => ['fingerprint' => $fingerprint] + $entry]], true).';'.PHP_EOL;
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (@file_put_contents($temporary, $contents) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write '.$path.'.');
        }
        self::forget();
    }

    public static function clear(Application $app): void
    {
        $path = self::path($app);
        if (is_file($path) && ! @unlink($path)) {
            throw new RuntimeException('Unable to remove '.$path.'.');
        }
        self::forget();
    }

    /** Drops what this process read, so the next lookup reads the file again. */
    public static function forget(): void
    {
        self::$files = [];
    }

    /**
     * Everything the cached answers were computed from, as it stands (see paths() and stats()): lstat of each path, and the contents hash of
     * each regular file under `.mod-maintenance`. No other file is opened.
     */
    public static function fingerprint(string $root): string
    {
        return hash('sha256', serialize(self::stats($root)));
    }

    /** A hash of the contents of every fingerprinted file (cache build time only; requests never open these files). */
    private static function contents(string $root): string
    {
        $hashes = [];
        foreach (self::paths($root) as $key => $path) {
            $hashes[$key] = is_file($path) ? (@hash_file('xxh128', $path) ?: '(unreadable)') : null;
        }

        return hash('xxh128', serialize($hashes));
    }

    /** The newest mtime among the fingerprinted files (the root and its parents carry none). */
    private static function newest(string $root): int
    {
        clearstatcache();
        $newest = 0;
        foreach (self::paths($root) as $key => $path) {
            $stat = str_starts_with($key, 'root:') ? false : @lstat($path);
            $newest = max($newest, $stat === false ? 0 : $stat['mtime']);
        }

        return $newest;
    }

    /**
     * What each fingerprinted path contributes, none of it changed by extracting or copying the checkout:
     * - the root and its parents: only what the live read checks (a link or not, a directory or not, and the mode), never their times, which
     *   change whenever anything in them does (a log, a session file, the home directory), nor their device and inode, which differ between
     *   the build container and the instance;
     * - the kit's sources and the host's installed.json: a link or not, size, mtime and mode;
     * - every entry of the `.mod-maintenance` tree: the same, its name (paths() names each entry, and absent ones), and for a regular file
     *   the xxh128 hash of its contents, so a same-size rewrite that keeps its mtime misses too. The records are small JSON files: hashing
     *   them costs microseconds, against tens of milliseconds for the live read.
     *
     * @return array<string, list<int|bool|string>|null>
     */
    private static function stats(string $root): array
    {
        clearstatcache();
        $maintenance = dirname(InstalledRecord::DIRECTORY);
        $parts = [];
        foreach (self::paths($root) as $key => $path) {
            if (str_starts_with($key, 'root:')) {
                $parts[$key] = self::identity($path);

                continue;
            }
            $parts[$key] = self::stat($path);
            if ($parts[$key] !== null && ($key === $maintenance || str_starts_with($key, $maintenance.'/')) && ! is_link($path) && is_file($path)) {
                $parts[$key][] = @hash_file('xxh128', $path) ?: '(unreadable)';
            }
        }

        return $parts;
    }

    /**
     * Every fingerprinted path, by a stable key: what the live read (`Checkout::installed()`) checks or reads, and the kit's sources of the
     * answers. That is the checkout root and each of its parents (the live read refuses a linked root or parent), the whole
     * `.mod-maintenance` tree, walked recursively (the live read refuses a linked, special or case-colliding entry anywhere in it, and reads
     * owner.json, the record directory, each record and the 0.8 record from it), the kit's files that compute the answers, and the host's
     * vendor/composer/installed.json.
     *
     * @return array<string, string>
     */
    private static function paths(string $root): array
    {
        $kit = dirname(__DIR__, 2);
        $paths = [];
        foreach (self::SOURCES as $source) {
            $paths['kit/'.$source] = $kit.'/'.$source;
        }
        $paths['host/installed.json'] = $root.'/vendor/composer/installed.json';
        $cursor = '';
        foreach (explode('/', ltrim($root, '/')) as $segment) {
            $cursor .= '/'.$segment;
            $paths['root:'.$cursor] = $cursor;
        }
        self::walk($root.'/'.dirname(InstalledRecord::DIRECTORY), dirname(InstalledRecord::DIRECTORY), $paths);
        // Named even when absent, so one appearing is a change.
        $paths += [InstalledRecord::DIRECTORY => $root.'/'.InstalledRecord::DIRECTORY, InstalledRecord::LEGACY => $root.'/'.InstalledRecord::LEGACY, '.mod-maintenance/owner.json' => $root.'/.mod-maintenance/owner.json'];

        return $paths;
    }

    /**
     * Adds `$path` and, when it is an unlinked directory, everything under it, keyed by the path relative to the checkout.
     *
     * @param  array<string, string>  $paths
     */
    private static function walk(string $path, string $key, array &$paths): void
    {
        $paths[$key] = $path;
        if (is_link($path) || ! is_dir($path)) {
            return;
        }
        $entries = scandir($path);
        if ($entries === false) {
            $paths[$key.'/(unreadable)'] = $path.'/(unreadable)';

            return;
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::walk($path.'/'.$entry, $key.'/'.$entry, $paths);
            }
        }
    }

    /** @return list<int|bool>|null A link or not, size, mtime and mode: no ctime or inode, which a copy of the file changes. */
    private static function stat(string $path): ?array
    {
        $stat = @lstat($path);

        return $stat === false ? null : [is_link($path), $stat['size'], $stat['mtime'], $stat['mode']];
    }

    /** @return list<int|bool>|null A directory's identity without its times, device or inode: a link or not, a directory or not, its mode. */
    private static function identity(string $path): ?array
    {
        $stat = @lstat($path);

        return $stat === false ? null : [is_link($path), is_dir($path), $stat['mode']];
    }

    /** @return array<string, mixed>|null */
    private static function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        try {
            $data = require $path;
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) && ($data['version'] ?? null) === self::VERSION && is_array($data['entries'] ?? null) ? $data['entries'] : null;
    }
}
