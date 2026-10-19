<?php

namespace Tey\Kit;

use Closure;
use Composer\Semver\Semver;
use RuntimeException;
use Tey\Mod\Maintenance\Artifact;
use Tey\Mod\Maintenance\Checkout;
use Tey\Mod\Maintenance\FilePlan;
use Tey\Mod\Maintenance\Maintenance;

/**
 * One Mod lineage per feature, named `tey/kit:<feature>`; the app is the union of its installed lineages.
 *
 * Feature requires are checked here, against the installed versions, and reach Mod as `requires:<id>`
 * compatibility outcomes with Mod requires '*'. Feature `conflicts` and owned-path overlaps stay kit-side.
 * Features are added in requires order, one lineage at a time, because Mod refuses every other lineage
 * while one has an unfinished update.
 */
final readonly class Lineages
{
    public const PREFIX = 'tey/kit:';

    private const VERIFICATION = ['dependency compatibility', 'generated resource integrity', 'host operation and tenant acceptance'];

    public function __construct(private Lifecycle $lifecycle, private ?Catalog $catalog = null) {}

    public static function name(string $id): string
    {
        return self::PREFIX.$id;
    }

    public function artifact(string $id, array $bindings): Artifact
    {
        $this->lifecycle->runtime();
        $lineage = Blueprint::lineage($id, $this->catalog);
        $base = $lineage['base'];
        if ($base['verification'] !== self::VERIFICATION) {
            throw new RuntimeException('Unsupported blueprint verification policy.');
        }
        $requires = array_fill_keys(array_map(self::name(...), $lineage['feature']['requires']['features']), '*');

        return new Artifact(self::name($id), $base['version'], $lineage['files'], $base['groups'], $bindings, $base['verification'], metadata: ['blueprint' => $base['id'], 'feature' => $id, 'blueprint_source' => $lineage['pin'], 'applied_migration_ids' => []], requires: $requires, hostOwned: array_values($lineage['feature']['host_owned']));
    }

    /**
     * The adopted features: installed `tey/kit:` lineages, in catalog order when the catalog knows them.
     *
     * @return array<string, array{identity: string, version: string, files: array<string, string>}>
     */
    public static function adopted(string $root, string $appId, ?Catalog $catalog = null): array
    {
        $adopted = [];
        foreach (Checkout::installed($root, $appId) as $name => $record) {
            if (str_starts_with($name, self::PREFIX)) {
                $adopted[substr($name, strlen(self::PREFIX))] = $record;
            }
        }
        $order = array_flip(($catalog ?? Catalog::package())->featureIds());
        uksort($adopted, fn (string $a, string $b): int => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        return $adopted;
    }

    /**
     * The features with every requirement added, in catalog order. Each requirement must come before what requires it.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function closure(array $ids): array
    {
        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('Select each feature once, and at least one.');
        }
        $catalog = $this->catalog ?? Catalog::package();
        $selected = [];
        $pending = $ids;
        while ($pending !== []) {
            $id = array_shift($pending);
            if (isset($selected[$id])) {
                continue;
            }
            $catalog->directory($id);
            $selected[$id] = true;
            array_push($pending, ...Blueprint::lineage($id, $catalog)['feature']['requires']['features']);
        }
        $ordered = array_values(array_intersect($catalog->featureIds(), array_keys($selected)));
        foreach ($ordered as $position => $id) {
            foreach (Blueprint::lineage($id, $catalog)['feature']['requires']['features'] as $required) {
                if (array_search($required, $ordered, true) > $position) {
                    throw new RuntimeException('Catalog order puts '.$id.' before its requirement '.$required.'.');
                }
            }
        }

        return $ordered;
    }

    /**
     * Read-only: what adding the features would do. Nothing is written.
     *
     * @param  list<string>  $ids
     * @return array{plan: string, executable: bool, features: list<array>, missing_packages: array<string, string>, host_steps: list<string>}
     */
    public function preview(string $root, string $appId, array $ids, array $bindings, array $inputPaths): array
    {
        $runtime = $this->lifecycle->runtime();
        $this->settled($root, $appId);
        $closure = $this->closure($ids);
        $installed = self::adopted($root, $appId, $this->catalog);
        $this->validate(array_values(array_unique(array_merge(array_keys($installed), $closure))));
        $this->lifecycle->inputs($root, $inputPaths, $runtime);
        $rows = [];
        $missing = [];
        $paths = [];
        foreach ($closure as $id) {
            $target = $this->artifact($id, $bindings);
            if (isset($installed[$id])) {
                // Re-adopting is a no-op; an installed feature changes only through kit:update.
                $rows[] = ['feature' => $id, 'action' => 'adopted', 'target' => $target->identity(), 'installed' => $installed[$id]['identity'], 'executable' => true];

                continue;
            }
            $compatibility = $this->compatibility($id, $installed, $closure);
            $requirements = array_diff_key($target->requires, array_flip(array_map(self::name(...), array_keys($installed))));
            // Requirements added earlier in this run are not installed yet; file states do not depend on them.
            $observed = $requirements === [] ? $target : new Artifact($target->name, $target->version, $target->files, $target->groups, $target->bindings, $target->checks, $target->metadata, hostOwned: $target->hostOwned);
            $plan = $this->inspect($root, $appId, $observed, $inputPaths, $compatibility);
            $packages = Blueprint::lineage($id, $this->catalog)['feature']['requires']['packages'];
            foreach ($packages as $package => $constraint) {
                if (($compatibility[$package] ?? null) !== 'passed') {
                    $missing[$package] = $constraint;
                }
            }
            $paths = array_merge($paths, array_keys($target->files));
            $rows[] = ['feature' => $id, 'action' => 'add', 'target' => $target->identity(), 'files' => $plan->files, 'compatibility' => $compatibility, 'executable' => $plan->executable()];
        }
        $inputs = array_map(fn (string $path): string => hash_file('sha256', $root.'/'.$path), $inputPaths);
        $identity = 'sha256:'.hash('sha256', json_encode([$appId, $root, $rows, $inputs], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $steps = [];
        foreach ($missing as $package => $constraint) {
            $steps[] = 'composer require '.$package.':"'.$constraint.'"';
        }
        if (array_filter($paths, fn (string $path): bool => str_starts_with($path, 'database/migrations/'))) {
            $steps[] = 'php artisan migrate';
        }
        if (array_filter($paths, fn (string $path): bool => str_starts_with($path, 'resources/js/'))) {
            $steps[] = 'rebuild the frontend';
        }

        return ['plan' => $identity, 'executable' => ! in_array(false, array_column($rows, 'executable'), true), 'features' => $rows, 'missing_packages' => $missing, 'host_steps' => $steps];
    }

    /**
     * Adds the reviewed plan's features in requires order, one lineage each, stopping at the first that does not complete.
     *
     * @param  list<string>  $ids
     */
    public function add(string $root, string $appId, array $ids, array $bindings, array $inputPaths, string $expectedPlan, ?string $intent, Closure $hostOperationCheck): array
    {
        $preview = $this->preview($root, $appId, $ids, $bindings, $inputPaths);
        if ($expectedPlan !== $preview['plan']) {
            throw new RuntimeException('A matching freshly reviewed expected plan is required.');
        }
        if (! $preview['executable']) {
            throw new RuntimeException('The plan is not executable; nothing was written.');
        }
        $this->lifecycle->hostCheck($root, $inputPaths, $hostOperationCheck);
        $intent = $intent === null || $intent === '' ? 'kit-add-'.substr($preview['plan'], 7, 16) : $intent;
        $results = [];
        foreach ($preview['features'] as $row) {
            if ($row['action'] !== 'add') {
                continue;
            }
            $id = $row['feature'];
            $target = $this->artifact($id, $bindings);
            $plan = $this->inspect($root, $appId, $target, $inputPaths, $this->compatibility($id, self::adopted($root, $appId, $this->catalog), []));
            if ($target->identity() !== $row['target'] || $plan->files !== $row['files'] || ! $plan->executable()) {
                throw new RuntimeException('The checkout changed since the reviewed plan: '.$id.'.');
            }
            $result = Maintenance::apply($plan, $inputPaths, $intent.':'.$id, $this->checks($id, $target, $hostOperationCheck), true);
            $results[$id] = $result;
            if ($result['status'] !== 'completed') {
                return ['status' => $result['status'], 'plan' => $preview['plan'], 'intent' => $intent, 'failed' => $id, 'features' => $results];
            }
        }

        return ['status' => 'completed', 'plan' => $preview['plan'], 'intent' => $intent, 'failed' => null, 'features' => $results];
    }

    /**
     * Finishes the first of the features whose adoption under this intent did not complete. The rest are added again with a fresh plan.
     *
     * @param  list<string>  $ids
     */
    public function resume(string $root, string $appId, array $ids, array $bindings, array $inputPaths, string $intent, Closure $hostOperationCheck): array
    {
        $runtime = $this->lifecycle->runtime();
        $this->lifecycle->inputs($root, $inputPaths, $runtime);
        $this->lifecycle->hostCheck($root, $inputPaths, $hostOperationCheck);
        $installed = self::adopted($root, $appId, $this->catalog);
        foreach ($this->closure($ids) as $id) {
            if (isset($installed[$id])) {
                continue;
            }
            $target = $this->artifact($id, $bindings);
            $result = Maintenance::resume($root, $appId, self::empty($target), $target, $inputPaths, $this->compatibility($id, $installed, []), $intent.':'.$id, $this->checks($id, $target, $hostOperationCheck), true, array_keys($target->files));

            return ['status' => $result['status'], 'intent' => $intent, 'failed' => $result['status'] === 'completed' ? null : $id, 'features' => [$id => $result]];
        }
        throw new RuntimeException('Every feature is already adopted; nothing to resume.');
    }

    /** One adopted feature's update plan, from the original catalog it was installed from to this catalog. */
    public function updatePlan(string $root, string $appId, string $id, array $bindings, array $inputPaths, ?Catalog $original = null): FilePlan
    {
        $runtime = $this->lifecycle->runtime();
        $this->settled($root, $appId);
        [$baseline, $target, $compatibility] = $this->update($root, $appId, $id, $bindings, $original);
        $this->validate(array_keys(self::adopted($root, $appId, $this->catalog)));
        $plan = Checkout::inspect($root, $appId, $baseline, $target, $inputPaths, $compatibility);
        $this->lifecycle->inputs($root, $inputPaths, $runtime);

        return $plan;
    }

    public function updateApply(FilePlan $plan, string $id, array $inputPaths, string $intent, Closure $hostOperationCheck, ?Catalog $original = null): array
    {
        $fresh = $this->updatePlan($plan->root, $plan->appId, $id, $plan->target->bindings, $inputPaths, $original);
        if ($fresh->identity() !== $plan->identity()) {
            throw new RuntimeException('Plan does not match the immutable feature and current checkout.');
        }
        $this->lifecycle->hostCheck($plan->root, $inputPaths, $hostOperationCheck);

        return Maintenance::apply($plan, $inputPaths, $intent, $this->checks($id, $plan->target, $hostOperationCheck), true);
    }

    public function updateResume(string $root, string $appId, string $id, array $bindings, array $inputPaths, string $intent, Closure $hostOperationCheck, ?Catalog $original = null): array
    {
        $runtime = $this->lifecycle->runtime();
        $this->lifecycle->inputs($root, $inputPaths, $runtime);
        $this->lifecycle->hostCheck($root, $inputPaths, $hostOperationCheck);
        [$baseline, $target, $compatibility] = $this->update($root, $appId, $id, $bindings, $original, resuming: true);

        return Maintenance::resume($root, $appId, $baseline, $target, $inputPaths, $compatibility, $intent, $this->checks($id, $target, $hostOperationCheck), true);
    }

    /** @return array{0: Artifact, 1: Artifact, 2: array<string, string>} */
    private function update(string $root, string $appId, string $id, array $bindings, ?Catalog $original, bool $resuming = false): array
    {
        $installed = self::adopted($root, $appId, $this->catalog);
        if (! isset($installed[$id])) {
            throw new RuntimeException('Feature '.$id.' is not adopted; add it with kit:add.');
        }
        $baseline = (new self($this->lifecycle, $original ?? $this->catalog))->artifact($id, $bindings);
        $target = $this->artifact($id, $bindings);
        if (! in_array($installed[$id]['identity'], $resuming ? [$baseline->identity(), $target->identity()] : [$baseline->identity()], true)) {
            throw new RuntimeException('The original given must be exactly the installed artifact, features and bindings included.');
        }

        return [$baseline, $target, $this->compatibility($id, $installed, [])];
    }

    /** Refuses while any lineage has an unfinished update, whichever it is. */
    private function settled(string $root, string $appId): void
    {
        // A name no lineage has, so Mod reports every unfinished lineage.
        $probe = new Artifact('tey/kit', '0', [], [], [], ['settled']);
        Checkout::inspect($root, $appId, $probe, $probe, [], ['settled' => 'passed']);
    }

    /** Every new path is listed as a claim: identical bytes are claimed without a write, differing bytes are a conflict. */
    private function inspect(string $root, string $appId, Artifact $target, array $inputPaths, array $compatibility): FilePlan
    {
        return Checkout::inspect($root, $appId, self::empty($target), $target, $inputPaths, $compatibility, array_keys($target->files));
    }

    /** The original for a first adoption: no files, and the same name, groups, bindings and checks as the target. */
    private static function empty(Artifact $target): Artifact
    {
        return new Artifact($target->name, $target->version, [], $target->groups, $target->bindings, $target->checks);
    }

    /**
     * Ownership, host-owned paths, conflicts and package agreement across every feature the app would have.
     *
     * @param  list<string>  $ids
     */
    private function validate(array $ids): void
    {
        $manifests = [];
        foreach ($ids as $id) {
            $manifests[$id] = Blueprint::lineage($id, $this->catalog)['feature'];
        }
        Blueprint::validateFeatures($manifests, $ids);
    }

    /**
     * Package constraints, plus each required feature: adopted at this kit's release line, or added earlier in the same run.
     *
     * @param  array<string, array{version: string}>  $installed
     * @param  list<string>  $planned
     * @return array<string, string>
     */
    private function compatibility(string $id, array $installed, array $planned): array
    {
        $lineage = Blueprint::lineage($id, $this->catalog);
        $outcomes = Lifecycle::compatibility($lineage['feature']['requires']['packages']) + ['tey/mod' => 'passed'];
        $range = '^'.implode('.', array_slice(explode('.', $lineage['base']['version']), 0, 2));
        foreach ($lineage['feature']['requires']['features'] as $required) {
            $adopted = isset($installed[$required]) && Semver::satisfies($installed[$required]['version'], $range);
            $outcomes['requires:'.$required] = $adopted || in_array($required, $planned, true) ? 'passed' : 'failed';
        }

        return $outcomes;
    }

    /** @return array<string, Closure> */
    private function checks(string $id, Artifact $target, Closure $hostOperationCheck): array
    {
        $verify = function () use ($id, $target): bool {
            $this->lifecycle->runtime();
            if (in_array('failed', Lifecycle::compatibility(Blueprint::lineage($id, $this->catalog)['feature']['requires']['packages']), true)) {
                throw new RuntimeException('Incompatible feature dependency: '.$id);
            }
            if ($this->artifact($id, $target->bindings)->identity() !== $target->identity()) {
                throw new RuntimeException('Immutable feature resource identity changed: '.$id);
            }

            return true;
        };

        return [
            'dependency compatibility' => $verify,
            'generated resource integrity' => $verify,
            'host operation and tenant acceptance' => function () use ($hostOperationCheck, $verify): bool {
                $result = $hostOperationCheck();
                // Host code may change source outside the observed checkout inputs.
                $verify();

                return $result === true;
            },
        ];
    }
}
