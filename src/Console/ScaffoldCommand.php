<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Writes a feature's pages, page partials, types and CSS into the app once; from then on the app owns them. Plain filesystem writes, no
 * records: an existing file is never overwritten unless --force names it, and --diff only reads. Migrations and the shared components
 * (resources/js/components/Kit) load from the package and are never written.
 */
final class ScaffoldCommand extends Command
{
    protected $signature = 'kit:scaffold {feature : The feature whose files to write} {--only=* : Write only these paths (a file, or a folder of them); repeat it or separate with commas} {--force=* : Overwrite these existing paths; repeat it or separate with commas} {--diff : Print a unified diff from each app file to the kit\'s current version; writes nothing}';

    protected $description = 'Write a feature\'s pages and partials into the app once (never overwrites without --force; --diff is read-only)';

    private const SHARED = 'resources/js/components/Kit/';

    public function handle(): int
    {
        try {
            $feature = $this->argument('feature');
            if (! is_string($feature)) {
                throw new RuntimeException('Name one feature.');
            }
            $files = $this->select(self::files($feature), $this->paths('only'));
            $force = $this->paths('force');
            foreach ($force as $path) {
                if ($this->matching(array_keys($files), $path) === []) {
                    throw new RuntimeException('--force='.$path.' names no file this run writes.');
                }
            }
            if ($files === []) {
                $this->line($feature.' has no files to scaffold.');

                return self::SUCCESS;
            }
            $root = $this->laravel->basePath();
            if ($this->option('diff')) {
                foreach ($files as $path => $source) {
                    $this->diff($root, $path, $source);
                }

                return self::SUCCESS;
            }
            // A linked folder on the way would carry a write outside the app: refuse before writing anything.
            foreach (array_keys($files) as $path) {
                for ($folder = dirname($path); $folder !== '.'; $folder = dirname($folder)) {
                    if (is_link($root.'/'.$folder)) {
                        throw new RuntimeException('Refusing to write through a linked folder: '.$folder.' (for '.$path.')');
                    }
                }
            }
            $written = $skipped = 0;
            foreach ($files as $path => $source) {
                $target = $root.'/'.$path;
                $exists = file_exists($target) || is_link($target);
                if ($exists && $this->matching([$path], ...$force) === []) {
                    $this->line('skipped '.$path.' (exists; --force='.$path.' overwrites it)');
                    $skipped++;

                    continue;
                }
                if (is_link($target) || is_dir($target)) {
                    throw new RuntimeException('Refusing to overwrite a link or folder: '.$path);
                }
                if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                    throw new RuntimeException('Could not create the folder for '.$path);
                }
                if (file_put_contents($target, file_get_contents($source)) === false) {
                    throw new RuntimeException('Could not write '.$path);
                }
                $this->line(($exists ? 'overwrote ' : 'wrote ').$path);
                $written++;
            }
            $this->line($written.' written, '.$skipped.' skipped.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * The feature's listed files (feature.json `files`), app path => stub path, without migrations (`files_from`) or shared components.
     *
     * @return array<string, string>
     */
    public static function files(string $feature): array
    {
        if (! preg_match('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $feature)) {
            throw new RuntimeException('Invalid feature id: '.$feature);
        }
        $directory = dirname(__DIR__, 2).'/resources/features/'.$feature;
        $manifest = $directory.'/feature.json';
        if (! is_file($manifest) || is_link($manifest)) {
            throw new RuntimeException('Unknown feature: '.$feature);
        }
        $decoded = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
        $entries = is_array($decoded) ? ($decoded['files'] ?? []) : null;
        if (! is_array($entries)) {
            throw new RuntimeException('Invalid '.$feature.'/feature.json.');
        }
        $files = [];
        foreach ($entries as $path => $entry) {
            $stub = is_array($entry) ? ($entry['stub'] ?? null) : null;
            if (! self::relative($path) || ! self::relative($stub)) {
                throw new RuntimeException('Invalid path in '.$feature.'/feature.json.');
            }
            if (str_starts_with($path, self::SHARED)) {
                continue;
            }
            if (! is_file($directory.'/'.$stub) || is_link($directory.'/'.$stub)) {
                throw new RuntimeException('Missing or linked stub: '.$feature.'/'.$stub);
            }
            $files[$path] = $directory.'/'.$stub;
        }
        ksort($files);

        return $files;
    }

    /** @phpstan-assert-if-true string $path */
    private static function relative(mixed $path): bool
    {
        return is_string($path) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*(\/[A-Za-z0-9][A-Za-z0-9._-]*)*\z/', $path) === 1 && preg_match('#(\A|/)\.\.(/|\z)#', $path) !== 1;
    }

    /** @return list<string> */
    private function paths(string $option): array
    {
        $values = $this->option($option);
        $paths = array_merge([], ...array_map(fn (mixed $paths): array => is_string($paths) ? explode(',', $paths) : [], is_array($values) ? $values : []));

        return array_values(array_filter(array_map(fn (string $path): string => trim($path, " \t/"), $paths), fn (string $path): bool => $path !== ''));
    }

    /**
     * @param  array<string, string>  $files
     * @param  list<string>  $only
     * @return array<string, string>
     */
    private function select(array $files, array $only): array
    {
        if ($only === []) {
            return $files;
        }
        $selected = [];
        foreach ($only as $path) {
            $matched = $this->matching(array_keys($files), $path);
            if ($matched === []) {
                throw new RuntimeException('--only='.$path.' names no file of this feature.');
            }
            $selected += array_intersect_key($files, array_flip($matched));
        }
        ksort($selected);

        return $selected;
    }

    /**
     * The paths that are one of the names, or inside a folder one names.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function matching(array $paths, string ...$names): array
    {
        return array_values(array_filter($paths, function (string $path) use ($names): bool {
            foreach ($names as $name) {
                if ($path === $name || str_starts_with($path, $name.'/')) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function diff(string $root, string $path, string $source): void
    {
        $target = $root.'/'.$path;
        $kit = (string) file_get_contents($source);
        if (! is_file($target)) {
            $this->line('missing '.$path.' (kit:scaffold writes it)');
            $this->line(self::unified('', $kit, '/dev/null', 'b/'.$path));

            return;
        }
        $app = (string) file_get_contents($target);
        if ($app === $kit) {
            $this->line('unchanged '.$path);

            return;
        }
        $this->line(self::unified($app, $kit, 'a/'.$path, 'b/'.$path));
    }

    /** A unified diff (three lines of context) from $from to $to. */
    public static function unified(string $from, string $to, string $fromName, string $toName): string
    {
        $a = $from === '' ? [] : explode("\n", rtrim($from, "\n"));
        $b = $to === '' ? [] : explode("\n", rtrim($to, "\n"));
        // Trim the common head and tail, then a longest common subsequence over what is left.
        $head = 0;
        while ($head < count($a) && $head < count($b) && $a[$head] === $b[$head]) {
            $head++;
        }
        $tail = 0;
        while ($tail < count($a) - $head && $tail < count($b) - $head && $a[count($a) - 1 - $tail] === $b[count($b) - 1 - $tail]) {
            $tail++;
        }
        $x = array_slice($a, $head, count($a) - $head - $tail);
        $y = array_slice($b, $head, count($b) - $head - $tail);
        $lcs = array_fill(0, count($x) + 1, array_fill(0, count($y) + 1, 0));
        for ($i = count($x) - 1; $i >= 0; $i--) {
            for ($j = count($y) - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $x[$i] === $y[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        // Each op: [' ' | '-' | '+', line, line number in a, line number in b] (0-based).
        $ops = [];
        for ($k = 0; $k < $head; $k++) {
            $ops[] = [' ', $a[$k], $k, $k];
        }
        $i = $j = 0;
        while ($i < count($x) || $j < count($y)) {
            if ($i < count($x) && $j < count($y) && $x[$i] === $y[$j]) {
                $ops[] = [' ', $x[$i], $head + $i++, $head + $j++];
            } elseif ($j < count($y) && ($i === count($x) || $lcs[$i][$j + 1] >= $lcs[$i + 1][$j])) {
                $ops[] = ['+', $y[$j], $head + $i, $head + $j++];
            } else {
                $ops[] = ['-', $x[$i], $head + $i++, $head + $j];
            }
        }
        for ($k = 0; $k < $tail; $k++) {
            $ops[] = [' ', $a[count($a) - $tail + $k], count($a) - $tail + $k, count($b) - $tail + $k];
        }
        $out = ['--- '.$fromName, '+++ '.$toName];
        $changes = array_keys(array_filter($ops, fn (array $op): bool => $op[0] !== ' '));
        $n = 0;
        while ($n < count($changes)) {
            $start = max(0, $changes[$n] - 3);
            $end = $changes[$n];
            while ($n + 1 < count($changes) && $changes[$n + 1] - $end <= 7) {
                $end = $changes[++$n];
            }
            $end = min(count($ops) - 1, $end + 3);
            $n++;
            $hunk = array_slice($ops, $start, $end - $start + 1);
            $fromCount = count(array_filter($hunk, fn (array $op): bool => $op[0] !== '+'));
            $toCount = count(array_filter($hunk, fn (array $op): bool => $op[0] !== '-'));
            $out[] = sprintf('@@ -%s +%s @@', self::range($ops[$start][2], $fromCount), self::range($ops[$start][3], $toCount));
            foreach ($hunk as $op) {
                $out[] = $op[0].$op[1];
            }
        }

        return implode("\n", $out);
    }

    private static function range(int $start, int $count): string
    {
        // A unified diff names an empty range by the line before it.
        return ($count === 0 ? $start : $start + 1).($count === 1 ? '' : ','.$count);
    }
}
