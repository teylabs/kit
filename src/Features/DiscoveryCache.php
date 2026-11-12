<?php

namespace Tey\Kit\Features;

use Illuminate\Contracts\Foundation\Application;
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
    private const VERSION = 1;

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
        if (! is_array($entry['lineages'] ?? null) || ! is_bool($entry['carry'] ?? null) || ! (is_array($entry['tables'] ?? null) || ($entry['tables'] ?? false) === null)) {
            return null;
        }

        return ['lineages' => array_values($entry['lineages']), 'carry' => $entry['carry'], 'tables' => $entry['tables'] === null ? null : array_values($entry['tables'])];
    }

    /**
     * Writes the cache for one app id from the live record (written whole, then moved into place). A record that cannot be read throws,
     * so `optimize` fails loudly rather than caching it.
     *
     * @param  array{lineages: list<string>, carry: bool, tables: list<string>|null}  $entry
     */
    public static function write(Application $app, string $appId, array $entry): void
    {
        $path = self::path($app);
        // Stat times have one-second resolution: a record written in the second the fingerprint is taken could change again in that
        // second and keep its fingerprint. Taking it once the clock has passed every record's ctime makes any later write differ.
        while (self::newest($app->basePath()) >= time()) {
            usleep(50_000);
        }
        $contents = '<?php return '.var_export(['version' => self::VERSION, 'entries' => [$appId => ['fingerprint' => self::fingerprint($app->basePath())] + $entry]], true).';'.PHP_EOL;
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (@file_put_contents($temporary, $contents) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to write '.$path.'.');
        }
        self::forget();
    }

    public static function clear(Application $app): void
    {
        $path = self::path($app);
        if (is_file($path) && ! @unlink($path)) {
            throw new \RuntimeException('Unable to remove '.$path.'.');
        }
        self::forget();
    }

    /** Drops what this process read, so the next lookup reads the file again. */
    public static function forget(): void
    {
        self::$files = [];
    }

    /**
     * The installed record's files and the installed kit, as they stand: lstat of the metadata directory, the record directory, each
     * entry in it and the 0.8 record, plus the kit's composer.json. No file is opened.
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
        $parts = ['kit' => self::stat(dirname(__DIR__, 2).'/composer.json')];
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
