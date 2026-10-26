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
    private const MOD = '^0.11';

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
            throw new RuntimeException('Lifecycle requires tey/mod 0.11.');
        }

        return ['installed_mod_version' => $version];
    }

    /**
     * Read installed provenance only; it never grants permission to update files.
     * Every installed lineage, with the features each carries and the state of each owned file.
     */
    public function status(string $root): ?array
    {
        $this->runtime();
        // Validate the checkout and metadata paths with Mod's read-only API before opening any record.
        if (is_link($root.'/.mod-maintenance/installed') || (file_exists($root.'/.mod-maintenance/installed') && ! is_dir($root.'/.mod-maintenance/installed'))) {
            throw new RuntimeException('Installed kit records must be a regular directory.');
        }
        $records = glob($root.'/.mod-maintenance/installed/*.json') ?: [];
        if (file_exists($root.'/.mod-maintenance/installed.json') || is_link($root.'/.mod-maintenance/installed.json')) {
            $records[] = $root.'/.mod-maintenance/installed.json';
        }
        if ($records === []) {
            $empty = new Artifact('kit-status', '1', [], [], [], ['status']);
            Checkout::inspect($root, 'kit-status', $empty, $empty, [], []);

            return null;
        }
        $saved = [];
        $appId = null;
        foreach ($records as $path) {
            if (is_link($path) || ! is_file($path)) {
                throw new RuntimeException('Installed kit record must be a regular file.');
            }
            $record = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($record) || ! is_string($record['app_id'] ?? null) || ($appId !== null && $record['app_id'] !== $appId) || ! is_string($record['artifact']['name'] ?? null)) {
                throw new RuntimeException('Invalid installed kit record.');
            }
            $appId = $record['app_id'];
            $saved[$record['artifact']['name']] ??= $record['artifact']['metadata'] ?? [];
        }
        // Mod verifies every record's owner, root, app and identity.
        $lineages = [];
        $all = [];
        foreach (Checkout::installed($root, $appId) as $name => $installed) {
            $metadata = $saved[$name] ?? throw new RuntimeException('Invalid installed kit record.');
            $features = isset($metadata['feature']) ? [$metadata['feature']] : ($metadata['blueprint_features'] ?? []);
            if (! is_array($features) || ! array_is_list($features) || array_filter($features, fn ($feature) => ! is_string($feature) || $feature === '') !== []) {
                throw new RuntimeException('Invalid installed blueprint features.');
            }
            $states = [];
            foreach ($installed['files'] as $file => $hash) {
                $path = $root.'/'.$file;
                if (is_link($path) || (file_exists($path) && ! is_file($path))) {
                    throw new RuntimeException('Owned file must be a regular file: '.$file);
                }
                $states[$file] = ! file_exists($path) ? 'missing' : (hash_file('sha256', $path) === $hash ? 'unchanged' : 'edited');
            }
            $lineages[] = ['name' => $name, 'version' => $installed['version'], 'identity' => $installed['identity'], 'features' => $features, 'files' => $states];
            $all = array_merge($all, $features);
        }

        return ['blueprint_features' => $all, 'lineages' => $lineages];
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
        // A release's own Mod constraint records the Mod it was built with; runtime() decides which Mod runs it now.
        $outcomes = array_intersect_key(['tey/mod' => 'passed'], $target->manifest['requirements']) + self::compatibility(array_diff_key($target->manifest['requirements'], ['tey/mod' => true]));
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
