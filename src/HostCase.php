<?php

namespace Tey\Kit;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Declared paths whose segments differ only by case from what the host has: a starter-kit app keeps its auth pages in
 * `resources/js/pages/auth`, the kit declares `resources/js/pages/Auth`. Mod refuses such a path when it plans, so the kit
 * finds each one first and reports it as a conflict, and `kit:add --adopt-case` renames the host's directory to the declared case.
 */
final class HostCase
{
    /** The Inertia page directory: a rename inside it also renames the pages the host's routes and controllers render. */
    private const PAGES = 'resources/js/pages/';

    /**
     * Each declared path with a segment the host has under another case, walked as Mod walks it.
     *
     * @param  list<string>  $paths
     * @return list<array{declared: string, existing: string, kind: string}>  `existing` is the host's entry, `kind` is directory or file.
     */
    public static function collisions(string $root, array $paths): array
    {
        $collisions = [];
        foreach ($paths as $path) {
            $cursor = $root;
            $existing = [];
            $segments = explode('/', $path);
            foreach ($segments as $index => $segment) {
                if (! is_dir($cursor) || is_link($cursor)) {
                    break;
                }
                $aliases = array_values(array_filter(scandir($cursor) ?: [], fn (string $entry): bool => $entry !== $segment && strtolower($entry) === strtolower($segment)));
                if ($aliases !== []) {
                    $collisions[] = ['declared' => $path, 'existing' => implode('/', [...$existing, $aliases[0]]), 'kind' => $index < count($segments) - 1 ? 'directory' : 'file'];

                    break;
                }
                $existing[] = $segment;
                $cursor .= '/'.$segment;
            }
        }

        return $collisions;
    }

    /** What the host does about one collision: a plan line naming both paths. */
    public static function step(array $collision, string $command): string
    {
        $declared = self::declaredPrefix($collision);

        return 'Case conflict: the kit declares '.$declared.' but the host has '.$collision['existing'].'. '.($collision['kind'] === 'directory'
            ? 'Rename '.$collision['existing'].' to '.$declared.' (and the Inertia page names under it), or run '.$command.' --adopt-case to do it.'
            : 'Rename or remove '.$collision['existing'].'; --adopt-case renames directories only.');
    }

    /**
     * The renames and render-name rewrites --adopt-case makes, read-only. It refuses a file collision, and a directory whose
     * declared case already exists beside it (only a case-sensitive filesystem can hold both).
     *
     * @param  list<array{declared: string, existing: string, kind: string}>  $collisions
     * @return array{renames: array<string, string>, rewrites: array<string, list<array{from: string, to: string}>>}
     */
    public static function adoption(string $root, array $collisions): array
    {
        $renames = [];
        foreach ($collisions as $collision) {
            if ($collision['kind'] !== 'directory') {
                throw new RuntimeException('Case conflict at '.$collision['existing'].' is a file, not a directory; rename or remove it by hand.');
            }
            $renames[$collision['existing']] = self::declaredPrefix($collision);
        }
        foreach ($renames as $from => $to) {
            $siblings = array_values(array_filter(scandir($root.'/'.dirname($from)) ?: [], fn (string $entry): bool => strtolower($entry) === strtolower(basename($to))));
            if ($siblings !== [basename($from)] || is_link($root.'/'.$from) || ! is_dir($root.'/'.$from)) {
                throw new RuntimeException('Cannot rename '.$from.' to '.$to.': '.implode(' and ', array_map(fn (string $entry): string => dirname($from).'/'.$entry, $siblings)).' already exist under another case. Merge them by hand.');
            }
        }

        return ['renames' => $renames, 'rewrites' => self::rewrites($root, $renames)];
    }

    /**
     * Renames each directory to its declared case (git mv in a git checkout), in two steps so a case-insensitive filesystem
     * takes it, then rewrites the render names. Returns what it did.
     *
     * @param  array{renames: array<string, string>, rewrites: array<string, list<array{from: string, to: string}>>}  $adoption
     */
    public static function adopt(string $root, array $adoption): array
    {
        $git = self::git($root);
        foreach ($adoption['renames'] as $from => $to) {
            $interim = $from.'.kit-case-'.bin2hex(random_bytes(4));
            foreach ([[$from, $interim], [$interim, $to]] as [$source, $destination]) {
                if ($git && self::tracked($root, $source)) {
                    $process = new Process(['git', 'mv', '--', $source, $destination], $root);
                    $process->run();
                    if (! $process->isSuccessful()) {
                        throw new RuntimeException('git mv '.$source.' '.$destination.' failed: '.trim($process->getErrorOutput()));
                    }
                } elseif (! rename($root.'/'.$source, $root.'/'.$destination)) {
                    throw new RuntimeException('Unable to rename '.$source.' to '.$destination.'.');
                }
            }
        }
        foreach ($adoption['rewrites'] as $file => $changes) {
            $bytes = (string) file_get_contents($root.'/'.$file);
            foreach ($changes as $change) {
                $bytes = str_replace($change['from'], $change['to'], $bytes);
            }
            file_put_contents($root.'/'.$file, $bytes);
        }

        return $adoption + ['git' => $git];
    }

    /** The declared spelling of the colliding entry: the declared path cut to the existing path's depth. */
    private static function declaredPrefix(array $collision): string
    {
        return implode('/', array_slice(explode('/', $collision['declared']), 0, substr_count($collision['existing'], '/') + 1));
    }

    /**
     * `Inertia::render('auth/…')`, `inertia('auth/…')` and `Route::inertia(…, 'auth/…')` in routes/ and app/Http/, for each renamed page directory.
     *
     * @param  array<string, string>  $renames
     * @return array<string, list<array{from: string, to: string}>>
     */
    private static function rewrites(string $root, array $renames): array
    {
        $pages = [];
        foreach ($renames as $from => $to) {
            if (str_starts_with($from, self::PAGES)) {
                $pages[substr($from, strlen(self::PAGES))] = substr($to, strlen(self::PAGES));
            }
        }
        if ($pages === []) {
            return [];
        }
        $rewrites = [];
        foreach (['routes', 'app/Http'] as $directory) {
            if (! is_dir($root.'/'.$directory) || is_link($root.'/'.$directory)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile() || $file->isLink() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $bytes = (string) file_get_contents($file->getPathname());
                $changes = [];
                foreach ($pages as $old => $new) {
                    $pattern = '/(?:Inertia::render|\binertia|Route::inertia)\s*\((?:[^()\'"]*[\'"][^\'"]*[\'"]\s*,)?\s*([\'"])'.preg_quote($old, '/').'\//';
                    preg_match_all($pattern, $bytes, $matches);
                    foreach (array_unique($matches[0]) as $match) {
                        $changes[] = ['from' => $match, 'to' => substr($match, 0, -strlen($old) - 1).$new.'/'];
                    }
                }
                if ($changes !== []) {
                    $rewrites[substr($file->getPathname(), strlen($root) + 1)] = $changes;
                }
            }
        }
        ksort($rewrites);

        return $rewrites;
    }

    private static function git(string $root): bool
    {
        $process = new Process(['git', 'rev-parse', '--is-inside-work-tree'], $root);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) === 'true';
    }

    private static function tracked(string $root, string $path): bool
    {
        $process = new Process(['git', 'ls-files', '--', $path], $root);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }
}
