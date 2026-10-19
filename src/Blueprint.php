<?php

namespace Tey\Kit;

use RuntimeException;

final readonly class Blueprint
{
    private const REFERENCES = [
        'A' => 'sha256:686b417bd3d1caf246aca43c590cc29fd3747dfd5661b3addd6b6021b2f5c74b',
        'B' => 'sha256:857fc187e69cfcfcc26f07a9f56a0bb57ac5b7e99814ba70100f226ce9912b3b',
        'C' => 'sha256:18e53f09a1d3e9490bb443f6ba09c5990e95cca36dc10fe8b290b506d9677bdb',
        'D' => 'sha256:777333b17717b854a2da884f1eb15cd042a18927719494baca0350623c0add0f',
    ];

    /** @param array<string, string> $directories stub name => directory that holds it */
    private function __construct(public array $manifest, private array $directories, private ?string $release, private array $features = [], private ?Catalog $catalog = null) {}

    public static function release(string $release): self
    {
        if (! in_array($release, ['A', 'B', 'C', 'D'], true)) {
            throw new RuntimeException('Unknown blueprint release.');
        }
        $directory = dirname(__DIR__).'/resources/releases/'.$release;
        $manifest = self::manifestAt($directory);
        foreach (['format', 'id', 'version', 'groups', 'requirements', 'generated', 'host_owned', 'migration_order', 'verification'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new RuntimeException('Missing blueprint field: '.$field);
            }
        }

        $directories = [];
        foreach ($manifest['generated'] as $entry) {
            $directories[$entry['stub']] = $directory;
        }
        $blueprint = new self($manifest, $directories, $release);
        $blueprint->sourceReference();

        return $blueprint;
    }

    /** @return list<string> */
    public static function featureIds(?Catalog $catalog = null): array
    {
        return ($catalog ?? Catalog::package())->featureIds();
    }

    /**
     * Each feature's id, required features and owned paths, in canonical order (read-only).
     *
     * @return list<array{id: string, requires: list<string>, owns: list<string>}>
     */
    public static function entries(?Catalog $catalog = null): array
    {
        $catalog ??= Catalog::package();
        $entries = [];
        foreach ($catalog->featureIds() as $id) {
            $feature = self::featureAt($catalog, $id);
            self::featureHash($catalog, $id, $feature);
            $entries[] = ['id' => $id, 'requires' => array_values($feature['requires']['features']), 'owns' => array_values($feature['owns'])];
        }

        return $entries;
    }

    /** Every feature: the whole app. */
    public static function all(?Catalog $catalog = null): self
    {
        return self::features(self::featureIds($catalog), $catalog);
    }

    /** @param list<string> $ids */
    public static function features(array $ids, ?Catalog $catalog = null): self
    {
        $catalog ??= Catalog::package();
        [$manifest, $directories, $features] = self::compose($ids, $catalog);
        $blueprint = new self($manifest, $directories, null, $features, $catalog);
        $blueprint->sourceReference();

        return $blueprint;
    }

    /** @return list<string> Selected feature ids in canonical order; empty for a release fixture. */
    public function selection(): array
    {
        return $this->manifest['features'] ?? [];
    }

    /** Features can be added to an installed artifact, never removed, and the artifact itself stays the same. */
    public static function assertGrowth(?self $original, self $target): void
    {
        if ($original === null) {
            return;
        }
        if (($original->release === null) !== ($target->release === null)) {
            throw new RuntimeException('A release fixture and a feature selection cannot be upgraded into each other.');
        }
        if ($original->release !== null) {
            return;
        }
        foreach (['id', 'groups'] as $field) {
            if ($original->manifest[$field] !== $target->manifest[$field]) {
                throw new RuntimeException('Blueprint '.$field.' cannot change between feature selections.');
            }
        }
        $removed = array_values(array_diff($original->selection(), $target->selection()));
        if ($removed !== []) {
            throw new RuntimeException('Feature removal is refused: '.implode(', ', $removed).'. Features can only be added.');
        }
    }

    /**
     * Checks a selection against a catalog of feature manifests keyed by id.
     *
     * @param  array<string, array>  $catalog
     * @param  list<string>  $selected
     */
    public static function validateFeatures(array $catalog, array $selected): void
    {
        if ($selected === [] || count($selected) !== count(array_unique($selected))) {
            throw new RuntimeException('Select each feature once, and at least one.');
        }
        foreach ($selected as $id) {
            if (! isset($catalog[$id])) {
                throw new RuntimeException('Unknown feature: '.$id);
            }
        }
        $paths = [];
        $stubs = [];
        $owned = [];
        $hostOwned = [];
        foreach ($selected as $id) {
            $feature = $catalog[$id];
            foreach (['format', 'id', 'owns', 'files', 'requires', 'conflicts', 'host_owned'] as $field) {
                if (! array_key_exists($field, $feature)) {
                    throw new RuntimeException('Missing feature field: '.$id.'.'.$field);
                }
            }
            if ($feature['id'] !== $id) {
                throw new RuntimeException('Feature id does not match its manifest: '.$id);
            }
            foreach ($feature['owns'] as $prefix) {
                foreach ($owned as $other => $others) {
                    foreach ($others as $existing) {
                        if (self::within($prefix, $existing) || self::within($existing, $prefix)) {
                            throw new RuntimeException('Features own overlapping paths: '.$other.' and '.$id);
                        }
                    }
                }
                $owned[$id][] = $prefix;
            }
            $hostOwned = array_merge($hostOwned, $feature['host_owned']);
        }
        foreach ($selected as $id) {
            $feature = $catalog[$id];
            foreach ($feature['files'] as $path => $entry) {
                if (! array_filter($feature['owns'], fn (string $prefix): bool => self::within($path, $prefix))) {
                    throw new RuntimeException('Path outside feature ownership: '.$id.' '.$path);
                }
                foreach ($hostOwned as $host) {
                    if (self::within($path, $host)) {
                        throw new RuntimeException('Path is host-owned: '.$path);
                    }
                }
                if (isset($paths[$path])) {
                    throw new RuntimeException('Duplicate path across features: '.$path.' in '.$paths[$path].' and '.$id);
                }
                $paths[$path] = $id;
                if (isset($stubs[$entry['stub']])) {
                    throw new RuntimeException('Duplicate stub across features: '.$entry['stub']);
                }
                $stubs[$entry['stub']] = $id;
            }
            foreach ($feature['requires']['features'] as $required) {
                if (! in_array($required, $selected, true)) {
                    throw new RuntimeException('Feature '.$id.' requires feature '.$required.'.');
                }
            }
            foreach ($feature['conflicts'] as $conflict) {
                if (in_array($conflict, $selected, true)) {
                    throw new RuntimeException('Feature '.$id.' conflicts with feature '.$conflict.'.');
                }
            }
        }
        $packages = [];
        foreach ($selected as $id) {
            foreach ($catalog[$id]['requires']['packages'] as $package => $constraint) {
                if (isset($packages[$package]) && $packages[$package] !== $constraint) {
                    throw new RuntimeException('Features disagree on the constraint for '.$package.'.');
                }
                $packages[$package] = $constraint;
            }
        }
    }

    private static function within(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/');
    }

    /** @return array{0: array, 1: array<string, string>, 2: array<string, string>} */
    private static function compose(array $ids, Catalog $catalog): array
    {
        $manifests = [];
        foreach ($catalog->featureIds() as $id) {
            if (in_array($id, $ids, true)) {
                $manifests[$id] = self::featureAt($catalog, $id);
            }
        }
        self::validateFeatures($manifests, $ids);
        $ids = array_values(array_intersect($catalog->featureIds(), $ids));
        $base = self::baseManifest($catalog);
        $manifest = ['format' => $base['format'], 'id' => $base['id'], 'version' => $base['version'], 'groups' => $base['groups'], 'requirements' => [], 'generated' => [], 'host_owned' => []];
        $directories = [];
        $hashes = [];
        foreach ($ids as $id) {
            $feature = $manifests[$id];
            $manifest['requirements'] += $feature['requires']['packages'];
            $manifest['generated'] += $feature['files'];
            $manifest['host_owned'] = array_values(array_unique(array_merge($manifest['host_owned'], $feature['host_owned'])));
            foreach ($feature['files'] as $entry) {
                $directories[$entry['stub']] = $catalog->directory($id);
            }
            $hashes[$id] = self::featureHash($catalog, $id, $feature);
        }
        foreach (['migration_order', 'verification', 'auth', 'exceptions'] as $field) {
            $manifest[$field] = $base[$field];
        }
        $manifest['features'] = $ids;

        return [$manifest, $directories, $hashes];
    }

    private static function baseManifest(Catalog $catalog): array
    {
        $base = self::manifestAt($catalog->root);
        if ('sha256:'.hash('sha256', file_get_contents($catalog->root.'/blueprint.json')) !== $catalog->basePin) {
            throw new RuntimeException('Immutable blueprint resource identity mismatch.');
        }

        return $base;
    }

    /** Reads one feature manifest, with any `files_from` stub tree expanded into `files`. */
    private static function featureAt(Catalog $catalog, string $id): array
    {
        $directory = $catalog->directory($id);
        $path = $directory.'/feature.json';
        if (is_link($directory) || ! is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or linked feature manifest.');
        }
        $feature = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($feature) || ! array_key_exists('files_from', $feature)) {
            return $feature;
        }

        return ['files' => self::expand($directory, $feature)] + $feature;
    }

    /**
     * Expands `files_from` into the map an explicit `files` would give: explicit entries first, then the
     * tree's files sorted by relative path. A `.stub` suffix is dropped from the target name.
     *
     * @return array<string, array{stub: string, scaffold: string, ownership: string}>
     */
    private static function expand(string $directory, array $feature): array
    {
        $from = $feature['files_from'];
        if (! is_array($from) || array_diff(array_keys($from), ['stubs', 'target', 'scaffold']) !== [] || ! is_string($from['stubs'] ?? null) || ! is_string($from['target'] ?? null) || ! is_string($from['scaffold'] ?? 'kit')) {
            throw new RuntimeException('Invalid files_from declaration.');
        }
        $files = $feature['files'] ?? [];
        if (! is_array($files)) {
            throw new RuntimeException('Invalid feature files.');
        }
        $stubs = self::relative($from['stubs']);
        $target = self::relative($from['target']);
        $owns = $feature['owns'] ?? null;
        if (! is_array($owns) || ! array_filter($owns, fn ($prefix): bool => is_string($prefix) && self::within($target, $prefix))) {
            throw new RuntimeException('files_from target is outside feature ownership: '.$target);
        }
        $tree = $directory.'/'.$stubs;
        if (is_link($tree) || ! is_dir($tree) || ! str_starts_with((string) realpath($tree), (string) realpath($directory).'/')) {
            throw new RuntimeException('Missing, linked or escaping files_from stub tree.');
        }
        $found = [];
        $walk = function (string $folder, string $prefix) use (&$walk, &$found): void {
            foreach (scandir($folder, SCANDIR_SORT_NONE) as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $path = $folder.'/'.$name;
                if (is_link($path)) {
                    throw new RuntimeException('Linked stub refused: '.$prefix.$name);
                }
                if (str_starts_with($name, '.')) {
                    continue;
                }
                if (is_dir($path)) {
                    $walk($path, $prefix.$name.'/');
                } elseif (is_file($path)) {
                    $found[] = $prefix.$name;
                } else {
                    throw new RuntimeException('Only regular stub files are allowed: '.$prefix.$name);
                }
            }
        };
        $walk($tree, '');
        sort($found, SORT_STRING);
        foreach ($found as $relative) {
            self::relative($relative);
            $path = $target.'/'.(str_ends_with($relative, '.stub') && strlen($relative) > 5 ? substr($relative, 0, -5) : $relative);
            if (isset($files[$path])) {
                throw new RuntimeException('Duplicate target path in feature: '.$path);
            }
            $files[$path] = ['stub' => $stubs.'/'.$relative, 'scaffold' => $from['scaffold'] ?? 'kit', 'ownership' => 'generated'];
        }

        return $files;
    }

    /** A catalog-relative path: no empty, `.` or `..` segments, no absolute or backslash forms. */
    private static function relative(string $path): string
    {
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*(\/[A-Za-z0-9][A-Za-z0-9._-]*)*\z/', $path)) {
            throw new RuntimeException('Invalid catalog path: '.$path);
        }

        return $path;
    }

    /** Content-addresses one feature: its manifest bytes and every stub it owns. */
    private static function featureHash(Catalog $catalog, string $id, array $feature): string
    {
        $directory = $catalog->directory($id);
        $stubs = [];
        foreach ($feature['files'] as $entry) {
            $path = self::stubIn($directory, $entry['stub']);
            $stubs[$entry['stub']] = hash('sha256', file_get_contents($path));
        }
        ksort($stubs);
        $hash = 'sha256:'.hash('sha256', json_encode([hash_file('sha256', $directory.'/feature.json'), $stubs], JSON_THROW_ON_ERROR));
        if ($hash !== $catalog->pins[$id]) {
            throw new RuntimeException('Immutable blueprint resource identity mismatch.');
        }

        return $hash;
    }

    public function sourceReference(): string
    {
        return $this->reference($this->resources());
    }

    /** @return array{source_reference: string, manifest: array, files: array<string, string>} */
    public function snapshot(): array
    {
        $resources = $this->resources();
        $reference = $this->reference($resources);

        return ['source_reference' => $reference, 'manifest' => $this->manifest, 'files' => self::render($resources)];
    }

    /**
     * One feature on its own, for its own lineage: its pinned manifest, the base manifest and its rendered files.
     * Required features are not composed in; the caller checks them against what is installed.
     *
     * @return array{feature: array, base: array, pin: string, files: array<string, string>}
     */
    public static function lineage(string $id, ?Catalog $catalog = null): array
    {
        $catalog ??= Catalog::package();
        $feature = self::featureAt($catalog, $id);
        self::validateFeatures([$id => ['requires' => ['features' => [], 'packages' => $feature['requires']['packages'] ?? []]] + $feature], [$id]);
        $pin = self::featureHash($catalog, $id, $feature);
        $resources = [];
        foreach ($feature['files'] as $path => $entry) {
            $resources[$path] = file_get_contents(self::stubIn($catalog->directory($id), $entry['stub']));
        }

        return ['feature' => $feature, 'base' => self::baseManifest($catalog), 'pin' => $pin, 'files' => self::render($resources)];
    }

    /**
     * @param  array<string, string>  $resources
     * @return array<string, string>
     */
    private static function render(array $resources): array
    {
        $files = [];
        foreach ($resources as $path => $bytes) {
            // Only PHP stubs carry placeholders; other files (Vue pages use `{{ }}` themselves) are copied as they are.
            if (! str_ends_with($path, '.php')) {
                $files[$path] = $bytes;

                continue;
            }
            $namespace = 'App\\'.str_replace('/', '\\', substr(dirname($path), 4));
            $rendered = str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, pathinfo($path, PATHINFO_FILENAME)], $bytes);
            if (preg_match('/\{\{.*?\}\}/s', $rendered)) {
                throw new RuntimeException('Unsupported blueprint placeholder.');
            }
            $files[$path] = $rendered;
        }

        return $files;
    }

    private function resources(): array
    {
        $current = $this->release === null ? self::compose($this->selection(), $this->catalog)[0] : self::manifestAt(array_values($this->directories)[0]);
        if ($current !== $this->manifest) {
            throw new RuntimeException('Immutable blueprint manifest changed.');
        }
        $resources = [];
        foreach ($this->manifest['generated'] as $path => $entry) {
            $resources[$path] = $this->stub($entry['stub']);
        }

        return $resources;
    }

    private function reference(array $resources): string
    {
        $hashes = array_map(fn (string $bytes): string => hash('sha256', $bytes), $resources);
        ksort($hashes);
        if ($this->release === null) {
            // Every feature's pinned identity is part of the set's identity.
            $reference = 'sha256:'.hash('sha256', json_encode([$this->manifest, $hashes, self::compose($this->selection(), $this->catalog)[2]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return $reference;
        }
        $reference = 'sha256:'.hash('sha256', json_encode([$this->manifest, $hashes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if ($reference !== self::REFERENCES[$this->release]) {
            throw new RuntimeException('Immutable blueprint resource identity mismatch.');
        }

        return $reference;
    }

    private static function manifestAt(string $directory): array
    {
        $path = $directory.'/blueprint.json';
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or linked blueprint manifest.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function stubPath(string $stub): string
    {
        if (! isset($this->directories[$stub])) {
            if (! preg_match('/\A[a-z0-9-]+\.php\.stub\z/', $stub)) {
                throw new RuntimeException('Invalid blueprint resource.');
            }
            throw new RuntimeException('Missing or linked blueprint resource.');
        }

        return self::stubIn($this->directories[$stub], $stub);
    }

    private static function stubIn(string $directory, string $stub): string
    {
        if (! preg_match('/\A\.?[A-Za-z0-9][A-Za-z0-9._-]*(\/\.?[A-Za-z0-9][A-Za-z0-9._-]*)*\z/', $stub)) {
            throw new RuntimeException('Invalid blueprint resource.');
        }
        $path = $directory.'/'.$stub;
        if ($stub !== basename($stub) && ! self::inside($path, $directory)) {
            throw new RuntimeException('Missing or linked blueprint resource.');
        }
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or linked blueprint resource.');
        }

        return $path;
    }

    /** True when no part of the path below the directory is a link. */
    private static function inside(string $path, string $directory): bool
    {
        $current = $directory;
        foreach (explode('/', substr($path, strlen($directory) + 1)) as $segment) {
            $current .= '/'.$segment;
            if (is_link($current)) {
                return false;
            }
        }

        return true;
    }

    public function stub(string $stub): string
    {
        return file_get_contents($this->stubPath($stub));
    }
}
