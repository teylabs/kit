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
 * each entry keeps a fingerprint of the record's files (name, inode, size, mtime, ctime) and of the installed kit, re-taken with a few
 * stat calls on every read. Any difference (kit:add, kit:update, kit:remove, a hand edit, a composer update) misses, and the live read runs
 * with its own verification and errors exactly as without a cache. A missing, unreadable or foreign file is a miss too.
 */
final class DiscoveryCache
{
    /** Bumped when an entry's shape changes, so an older file is a miss. */
    private const VERSION = 2;

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
     * Writes the cache for one app id from `$discover`, the live read (written whole, then moved into place). The fingerprint is taken
     * before and after the read, and the read is repeated until both match, so the answers stored always belong to the files fingerprinted.
     * A record that cannot be read throws (nothing is written), as does a record that keeps changing.
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
            // Stat times have one-second resolution: a record written in the second the fingerprint is taken could change again in that
            // second and keep its fingerprint. Taking it once the clock has passed every fingerprinted file's ctime makes any later write differ.
            // A file dated more than two seconds ahead (clock skew) is never waited out: nothing is cached.
            $deadline = microtime(true) + 3;
            while (self::newest($root) >= time()) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('An installed record is dated in the future; nothing was cached.');
                }
                usleep(50_000);
            }
            $before = self::fingerprint($root);
            $entry = self::valid($discover());
            if ($entry === null) {
                throw new RuntimeException('The discovered answers are not in the cache\'s shape; nothing was cached.');
            }
            if (self::fingerprint($root) === $before) {
                break;
            }
        }
        $contents = '<?php return '.var_export(['version' => self::VERSION, 'entries' => [$appId => ['fingerprint' => $before] + $entry]], true).';'.PHP_EOL;
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
     * The installed record's files and everything else the cached answers were computed from, as they stand: lstat of the metadata
     * directory, the record directory, each entry in it and the 0.8 record; the kit's own files that compute or feed the answers (its
     * composer.json, the catalog and its pins, session-ui's feature.json, Lineages and the composition root); and the host's
     * vendor/composer/installed.json, which any composer install or update rewrites. No file is opened.
     */
    public static function fingerprint(string $root): string
    {
        return hash('sha256', serialize(self::stats($root)));
    }

    /** The newest mtime or ctime among the fingerprinted files. */
    private static function newest(string $root): int
    {
        $newest = 0;
        foreach (self::stats($root) as $stat) {
            $newest = max($newest, $stat[3] ?? 0, $stat[4] ?? 0);
        }

        return $newest;
    }

    /** @return array<string, list<int|bool>|null> */
    private static function stats(string $root): array
    {
        clearstatcache();
        $kit = dirname(__DIR__, 2);
        $parts = [];
        foreach (self::SOURCES as $source) {
            $parts['kit/'.$source] = self::stat($kit.'/'.$source);
        }
        $parts['host/installed.json'] = self::stat($root.'/vendor/composer/installed.json');
        $directory = $root.'/'.InstalledRecord::DIRECTORY;
        foreach ([dirname($directory), $directory, $root.'/'.InstalledRecord::LEGACY] as $path) {
            $parts[$path] = self::stat($path);
        }
        $entries = is_dir($directory) ? scandir($directory) : [];
        foreach ($entries === false ? ['(unreadable)'] : $entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $parts['installed/'.$entry] = self::stat($directory.'/'.$entry);
            }
        }

        return $parts;
    }

    /** @return list<int|bool>|null */
    private static function stat(string $path): ?array
    {
        $stat = @lstat($path);

        return $stat === false ? null : [is_link($path), $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime'], $stat['mode']];
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
