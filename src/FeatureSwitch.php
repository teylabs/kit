<?php

namespace Tey\Kit;

use Tey\Kit\Features\FeaturesServiceProvider;
use Throwable;

/**
 * Switches adopted features on. With `kit.features` null, a feature is on once its lineage is installed, so there is nothing to do. With an explicit
 * list in config/kit.php, that list overrides the lineages, so kit:add and kit:adopt add the feature to it, in canonical order. The edit is made only to a
 * literal list of quoted ids (`'features' => ['core', 'shell'],`, on one line or several), checked by reading the file back, and undone if the
 * file no longer says the same thing. Anything else (env(), a computed list, a symlink) is left alone and reported as the edit to make.
 */
final class FeatureSwitch
{
    /**
     * @param  list<string>  $ids  the features just adopted
     * @return array{changed: list<string>, attention: list<string>}
     */
    public static function on(string $root, array $ids, ?array $configured): array
    {
        if ($configured === null) {
            return ['changed' => [], 'attention' => []];
        }
        $missing = array_values(array_diff($ids, $configured));
        if ($missing === []) {
            return ['changed' => [], 'attention' => []];
        }
        $wanted = array_values(array_intersect(array_keys(FeaturesServiceProvider::FEATURES), [...$configured, ...$missing]));
        $manual = ['config/kit.php sets kit.features, which overrides the adopted lineages, so '.implode(', ', $missing).' stay off. Add '.implode(', ', array_map(fn (string $id): string => "'".$id."'", $missing)).' to it, or set it to null to follow the lineages.'];
        $path = $root.'/config/kit.php';
        if (is_link($path) || ! is_file($path)) {
            return ['changed' => [], 'attention' => $manual];
        }
        $before = (string) file_get_contents($path);
        if (preg_match_all("/(['\"])features\\1\\s*=>\\s*\\[([^\\]]*)\\]/", $before, $matches, PREG_OFFSET_CAPTURE) !== 1 || ! preg_match("/\\A\\s*(?:(['\"])[a-z0-9-]+\\1\\s*,\\s*)*(?:(['\"])[a-z0-9-]+\\2\\s*,?\\s*)?\\z/", $matches[2][0][0])) {
            return ['changed' => [], 'attention' => $manual];
        }
        [$list, $offset] = $matches[2][0];
        preg_match_all("/['\"]([a-z0-9-]+)['\"]/", $list, $current);
        if ($current[1] !== $configured) {
            // The file is not where this process's config came from (cached, or overridden elsewhere).
            return ['changed' => [], 'attention' => $manual];
        }
        $multiline = str_contains($list, "\n");
        $closing = $multiline && preg_match('/\n([ \t]*)\z/', $list, $found) ? $found[1] : '';
        // An empty multi-line list has no item to copy the indent from: one level in from its closing bracket.
        $indent = $multiline && preg_match('/\n([ \t]*)[\'"]/', $list, $found) ? $found[1] : $closing.'    ';
        $items = array_map(fn (string $id): string => "'".$id."'", $wanted);
        $replacement = $multiline ? "\n".$indent.implode(",\n".$indent, $items).",\n".$closing : implode(', ', $items);
        $after = substr_replace($before, $replacement, $offset, strlen($list));
        file_put_contents($path, $after);
        try {
            $read = (static fn (string $file): mixed => require $file)($path);
            $ok = is_array($read) && ($read['features'] ?? null) === $wanted;
        } catch (Throwable) {
            $ok = false;
        }
        if (! $ok) {
            file_put_contents($path, $before);

            return ['changed' => [], 'attention' => $manual];
        }
        if (function_exists('config')) {
            config(['kit.features' => $wanted]);
        }

        return ['changed' => $missing, 'attention' => []];
    }
}
