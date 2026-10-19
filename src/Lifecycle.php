<?php

namespace Tey\Kit;

use Closure;
use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use InvalidArgumentException;
use ReflectionFunction;
use RuntimeException;
use Tey\Mod\Maintenance\Artifact;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\Maintenance\FilePlan;
use Tey\Mod\Maintenance\Maintenance;

/** Opinionated adapter; all lifecycle effects belong to Mod's public API. */
final readonly class Lifecycle
{
    /** 0.8.99 is the inline alias of the unreleased 0.9 API (dev-main#7cd30eb) until 0.9 is tagged. */
    private const MOD = '0.8.99 || ^0.9';

    /** Checks actual installed packages and PHP, never a caller-supplied version inventory. */
    public static function compatibility(array $requirements): array
    {
        $outcomes = [];
        foreach ($requirements as $package => $constraint) {
            if (! is_string($package) || ! is_string($constraint)) {
                throw new InvalidArgumentException('Named package constraints are required.');
            }
            // Installed aliases count, as Composer itself resolves them.
            $passed = $package === 'php' ? Semver::satisfies(PHP_VERSION, $constraint) : InstalledVersions::isInstalled($package) && InstalledVersions::satisfies(new VersionParser, $package, $constraint);
            $outcomes[$package] = $passed ? 'passed' : 'failed';
        }

        return $outcomes;
    }

    /** The Mod release this process runs on; lifecycle needs the released Maintenance API. */
    public function runtime(): array
    {
        $version = InstalledVersions::isInstalled('tey/mod') ? InstalledVersions::getPrettyVersion('tey/mod') : null;
        if ($version === null || ! InstalledVersions::satisfies(new VersionParser, 'tey/mod', self::MOD) || ! method_exists(Checkout::class, 'installed')) {
            throw new RuntimeException('Lifecycle requires tey/mod 0.9.');
        }

        return ['installed_mod_version' => $version];
    }

    /** Read installed provenance only; it never grants permission to update files. */
    public function status(string $root): ?array
    {
        $this->runtime();
        // Mod 0.8 has no public installed-record reader. Validate the checkout and
        // metadata paths with its read-only API before opening the local record.
        $empty = new Artifact('kit-status', '1', [], [], [], ['status']);
        Checkout::inspect($root, 'kit-status', $empty, $empty, [], []);
        $path = $root.'/.mod-maintenance/installed.json';
        if (! file_exists($path)) {
            return null;
        }
        if (! is_file($path)) {
            throw new RuntimeException('Installed kit record must be a regular file.');
        }
        $record = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($record) || ($record['format'] ?? null) !== 1 || ($record['owner'] ?? null) !== 'tey/mod:installed' || ($record['root'] ?? null) !== $root || ! is_string($record['app_id'] ?? null) || ! is_array($record['artifact'] ?? null)) {
            throw new RuntimeException('Invalid installed kit record.');
        }
        $saved = $record['artifact'];
        $files = [];
        if (! is_string($saved['name'] ?? null) || ! is_string($saved['version'] ?? null) || ! is_array($saved['groups'] ?? null) || ! is_array($saved['bindings'] ?? null) || ! is_array($saved['checks'] ?? null)) {
            throw new RuntimeException('Invalid installed artifact.');
        }
        if (! is_array($saved['files'] ?? null)) {
            throw new RuntimeException('Invalid installed artifact files.');
        }
        foreach ($saved['files'] as $file => $encoded) {
            if (! is_string($encoded) || ($bytes = base64_decode($encoded, true)) === false) {
                throw new RuntimeException('Invalid installed artifact bytes.');
            }
            $files[$file] = $bytes;
        }
        $artifact = new Artifact($saved['name'], $saved['version'], $files, $saved['groups'], $saved['bindings'], $saved['checks'], $saved['metadata'] ?? []);
        if (($saved['identity'] ?? null) !== $artifact->identity()) {
            throw new RuntimeException('Invalid installed artifact identity.');
        }
        $features = $artifact->metadata['blueprint_features'] ?? [];
        if (! is_array($features) || ! array_is_list($features) || array_filter($features, fn ($feature) => ! is_string($feature) || $feature === '') !== []) {
            throw new RuntimeException('Invalid installed blueprint features.');
        }
        $plan = Checkout::inspect($root, $record['app_id'], $artifact, $artifact, [], []);
        $states = [];
        foreach ($plan->files as $file => $entry) {
            $states[$file] = match ($entry['state']) {
                'unchanged' => 'unchanged',
                'conflict' => 'edited',
                'restore' => 'missing',
                default => throw new RuntimeException('Unexpected installed file state.'),
            };
        }

        return ['blueprint_features' => $features, 'artifact' => ['name' => $artifact->name, 'version' => $artifact->version, 'identity' => $artifact->identity()], 'files' => $states];
    }

    public function artifact(Blueprint $blueprint, array $bindings): Artifact
    {
        $this->runtime();
        $snapshot = $blueprint->snapshot();
        $manifest = $snapshot['manifest'];

        $metadata = ['blueprint_source' => $snapshot['source_reference'], 'blueprint_manifest' => $manifest, 'applied_migration_ids' => []];
        if (isset($manifest['features'])) {
            $metadata['blueprint_features'] = $manifest['features'];
        }

        return new Artifact($manifest['id'], $manifest['version'], $snapshot['files'], $manifest['groups'], $bindings, $manifest['verification'], metadata: $metadata);
    }

    /** The original for a first adoption: no files, and the same name, groups, bindings and checks as the target. */
    private function empty(Artifact $b): Artifact
    {
        return new Artifact($b->name, $b->version, [], $b->groups, $b->bindings, $b->checks);
    }

    public function plan(string $root, string $appId, ?Blueprint $original, Blueprint $target, array $bindings, array $inputPaths): FilePlan
    {
        Blueprint::assertGrowth($original, $target);
        $runtime = $this->runtime();
        $compatibility = $this->compatible($target);
        $b = $this->artifact($target, $bindings);
        $a = $original === null ? $this->empty($b) : $this->artifact($original, $bindings);
        $this->installed($root, $appId, $original === null ? null : $a, [$a]);
        $plan = Checkout::inspect($root, $appId, $a, $b, $inputPaths, $compatibility);
        $this->inputs($root, $inputPaths, $runtime);

        return $plan;
    }

    public function apply(FilePlan $plan, ?Blueprint $original, Blueprint $target, array $inputPaths, string $intent, Closure $hostOperationCheck, bool $exclusiveCheckout): array
    {
        Blueprint::assertGrowth($original, $target);
        $runtime = $this->runtime();
        $compatibility = $this->compatible($target);
        $b = $this->artifact($target, $plan->target->bindings);
        $a = $original === null ? $this->empty($b) : $this->artifact($original, $plan->baseline->bindings);
        if ($plan->target->identity() !== $b->identity() || $plan->baseline->identity() !== $a->identity() || $plan->compatibility !== $compatibility) {
            throw new RuntimeException('Plan does not match the immutable blueprint and current compatibility.');
        }
        $this->installed($plan->root, $plan->appId, $original === null ? null : $a, [$a]);
        Checkout::inspect($plan->root, $plan->appId, $a, $b, $inputPaths, $compatibility);
        $this->inputs($plan->root, $inputPaths, $runtime);
        $this->hostCheck($plan->root, $inputPaths, $hostOperationCheck);
        $result = Maintenance::apply($plan, $inputPaths, $intent, $this->checks($original, $target, $hostOperationCheck), $exclusiveCheckout);

        return $result + ['runtime' => $runtime];
    }

    public function resume(string $root, string $appId, ?Blueprint $original, Blueprint $target, array $bindings, array $inputPaths, string $intent, Closure $hostOperationCheck, bool $exclusiveCheckout): array
    {
        Blueprint::assertGrowth($original, $target);
        $runtime = $this->runtime();
        $compatibility = $this->compatible($target);
        $b = $this->artifact($target, $bindings);
        $a = $original === null ? $this->empty($b) : $this->artifact($original, $bindings);
        $this->installed($root, $appId, $original === null ? null : $a, [$a, $b]);
        Checkout::inspect($root, $appId, $a, $b, $inputPaths, $compatibility);
        $this->inputs($root, $inputPaths, $runtime);
        $this->hostCheck($root, $inputPaths, $hostOperationCheck);
        $result = Maintenance::resume($root, $appId, $a, $b, $inputPaths, $compatibility, $intent, $this->checks($original, $target, $hostOperationCheck), $exclusiveCheckout);

        return $result + ['runtime' => $runtime];
    }

    /**
     * An upgrade's original must be the installed artifact exactly, metadata included; Mod refuses it otherwise.
     * Resume may also meet the target when the intent already completed.
     *
     * @param  list<Artifact>  $accepted
     */
    private function installed(string $root, string $appId, ?Artifact $original, array $accepted): void
    {
        if ($original === null) {
            return;
        }
        $identity = Checkout::installed($root, $appId)[$original->name]['identity'] ?? null;
        if ($identity === null) {
            return;
        }
        foreach ($accepted as $artifact) {
            if ($identity === $artifact->identity()) {
                return;
            }
        }
        throw new RuntimeException('The original given must be exactly the installed artifact, features and bindings included.');
    }

    private function compatible(Blueprint $target): array
    {
        $outcomes = self::compatibility($target->manifest['requirements']);
        foreach ($outcomes as $package => $outcome) {
            if ($outcome !== 'passed') {
                throw new RuntimeException('Incompatible blueprint dependency: '.$package);
            }
        }

        return $outcomes;
    }

    /** Every host verification input exists, and the executor provenance is this runtime. */
    public function inputs(string $root, array $inputPaths, array $runtime): void
    {
        $required = ['verification-policy', 'verification-code', 'dependency-lock', 'host-config', 'host-binding', 'host-operations', 'executor-provenance'];
        foreach ($required as $id) {
            if (! isset($inputPaths[$id]) || ! is_file($root.'/'.$inputPaths[$id])) {
                throw new RuntimeException('Missing required host verification input: '.$id);
            }
        }
        $path = $root.'/'.$inputPaths['executor-provenance'];
        if (filesize($path) > 4096 || json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR) !== $runtime) {
            throw new RuntimeException('Executor provenance input must match the currently verified runtime.');
        }
    }

    /** The host check must be implemented in the bound host-operations input. */
    public function hostCheck(string $root, array $inputPaths, Closure $hostOperationCheck): void
    {
        $implementation = (new ReflectionFunction($hostOperationCheck))->getFileName();
        if ($implementation === false || realpath($implementation) !== realpath($root.'/'.$inputPaths['host-operations'])) {
            throw new RuntimeException('Host operation check must be implemented in the bound host-operations input.');
        }
    }

    private function checks(?Blueprint $original, Blueprint $target, Closure $hostOperationCheck): array
    {
        if ($target->manifest['verification'] !== ['dependency compatibility', 'generated resource integrity', 'host operation and tenant acceptance']) {
            throw new RuntimeException('Unsupported blueprint verification policy.');
        }

        return [
            'dependency compatibility' => function () use ($target): bool {
                $this->runtime();
                $this->compatible($target);

                return true;
            },
            'generated resource integrity' => function () use ($original, $target): bool {
                $original?->sourceReference();
                $target->sourceReference();

                return true;
            },
            'host operation and tenant acceptance' => function () use ($hostOperationCheck, $original, $target): bool {
                $result = $hostOperationCheck();
                // Host code may change source outside the observed checkout inputs.
                $this->runtime();
                $this->compatible($target);
                $original?->sourceReference();
                $target->sourceReference();

                return $result === true;
            },
        ];
    }
}
