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
        // Mod paths cannot start with a dot (`.env`); those host-owned prefixes are enforced kit-side only, in validate().
        $hostOwned = array_values(array_filter($lineage['feature']['host_owned'], fn (string $prefix): bool => ! str_starts_with($prefix, '.')));

        return new Artifact(self::name($id), $base['version'], $lineage['files'], $base['groups'], $bindings, $base['verification'], metadata: ['blueprint' => $base['id'], 'feature' => $id, 'blueprint_source' => $lineage['pin'], 'applied_migration_ids' => []], requires: $requires, hostOwned: $hostOwned);
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
     * Only the reviewed `$claim` paths are claimed. Other existing files with identical bytes are listed as
     * `claimable` and stay unowned, so the plan is not executable until each is claimed or removed.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $claim
     * @return array{plan: string, executable: bool, features: list<array>, claim: list<string>, claimable: list<string>, missing_packages: array<string, string>, host_steps: list<string>}
     */
    public function preview(string $root, string $appId, array $ids, array $bindings, array $inputPaths, array $claim = []): array
    {
        $claim = self::claims($claim);
        $runtime = $this->lifecycle->runtime();
        $this->settled($root, $appId);
        $closure = $this->closure($ids);
        $installed = self::adopted($root, $appId, $this->catalog);
        $this->validate(array_values(array_unique(array_merge(array_keys($installed), $closure))));
        $this->lifecycle->inputs($root, $inputPaths, $runtime);
        $rows = [];
        $missing = [];
        $paths = [];
        $claimable = [];
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
            $plan = $this->inspect($root, $appId, $observed, $inputPaths, $compatibility, $claim);
            $candidates = Maintenance::diagnose($plan, $inputPaths)['claimable'];
            $claimable = array_merge($claimable, $candidates);
            $packages = Blueprint::lineage($id, $this->catalog)['feature']['requires']['packages'];
            foreach ($packages as $package => $constraint) {
                if (($compatibility[$package] ?? null) !== 'passed') {
                    $missing[$package] = $constraint;
                }
            }
            $paths = array_merge($paths, array_keys($target->files));
            $rows[] = ['feature' => $id, 'action' => 'add', 'target' => $target->identity(), 'files' => $plan->files, 'claimable' => $candidates, 'compatibility' => $compatibility, 'executable' => $plan->executable()];
        }
        $stray = array_diff($claim, $paths);
        if ($stray !== []) {
            throw new RuntimeException('Claims must name files a feature being added delivers: '.implode(', ', $stray).'.');
        }
        $inputs = array_map(fn (string $path): string => hash_file('sha256', $root.'/'.$path), $inputPaths);
        $identity = 'sha256:'.hash('sha256', json_encode([$appId, $root, $rows, $inputs, $claim], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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

        return ['plan' => $identity, 'executable' => ! in_array(false, array_column($rows, 'executable'), true), 'features' => $rows, 'claim' => $claim, 'claimable' => $claimable, 'missing_packages' => $missing, 'host_steps' => $steps];
    }

    /**
     * Adds the reviewed plan's features in requires order, one lineage each, stopping at the first that does not complete.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $claim  The reviewed claim list the preview was made with.
     */
    public function add(string $root, string $appId, array $ids, array $bindings, array $inputPaths, string $expectedPlan, ?string $intent, Closure $hostOperationCheck, array $claim = []): array
    {
        $preview = $this->preview($root, $appId, $ids, $bindings, $inputPaths, $claim);
        if ($expectedPlan !== $preview['plan']) {
            throw new RuntimeException('A matching freshly reviewed expected plan is required.');
        }
        if (! $preview['executable']) {
            $unclaimed = $preview['claimable'] === [] ? '' : ' Review and claim each identical existing file with --claim, or remove it: '.implode(', ', $preview['claimable']).'.';
            throw new RuntimeException('The plan is not executable; nothing was written.'.$unclaimed);
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
            $plan = $this->inspect($root, $appId, $target, $inputPaths, $this->compatibility($id, self::adopted($root, $appId, $this->catalog), []), $preview['claim']);
            if ($target->identity() !== $row['target'] || $plan->files !== $row['files'] || ! $plan->executable()) {
                throw new RuntimeException('The checkout changed since the reviewed plan: '.$id.'.');
            }
            $result = Maintenance::apply($plan, $inputPaths, $intent.':'.$id, $this->checks($id, $target, $hostOperationCheck), true);
            $results[$id] = $result;
            if ($result['status'] !== 'completed') {
                return $this->outcome($root, $appId, $ids, $result['status'], $intent, $id, $results) + ['plan' => $preview['plan']];
            }
        }

        return $this->outcome($root, $appId, $ids, 'completed', $intent, null, $results) + ['plan' => $preview['plan']];
    }

    /**
     * Finishes the original request: the feature whose adoption under this intent did not complete, then every
     * remaining feature in requires order, each from a fresh plan with the same reviewed claim list.
     * Stops at the first that does not complete, and reports which features are done and which remain.
     *
     * @param  list<string>  $ids  The features of the original request.
     * @param  list<string>  $claim  The reviewed claim list of the original apply.
     */
    public function resume(string $root, string $appId, array $ids, array $bindings, array $inputPaths, string $intent, Closure $hostOperationCheck, array $claim = []): array
    {
        $runtime = $this->lifecycle->runtime();
        $claim = self::claims($claim);
        $this->lifecycle->inputs($root, $inputPaths, $runtime);
        $this->lifecycle->hostCheck($root, $inputPaths, $hostOperationCheck);
        $closure = $this->closure($ids);
        $installed = self::adopted($root, $appId, $this->catalog);
        $remaining = array_values(array_diff($closure, array_keys($installed)));
        if ($remaining === []) {
            throw new RuntimeException('Every feature is already adopted; nothing to resume.');
        }
        $first = $remaining[0];
        $journal = self::journal($root, $intent.':'.$first);
        if ($journal === null || ($journal['lineage'] ?? null) !== self::name($first)) {
            throw new RuntimeException('No unfinished adoption of '.$first.' under intent '.$intent.'; check the intent the apply printed.');
        }
        $results = [];
        foreach ($remaining as $id) {
            $target = $this->artifact($id, $bindings);
            $compatibility = $this->compatibility($id, self::adopted($root, $appId, $this->catalog), []);
            $checks = $this->checks($id, $target, $hostOperationCheck);
            if ($id === $first) {
                // The interrupted plan's own claims, as its journal recorded them.
                $claimed = array_keys(is_array($journal['claimed'] ?? null) ? $journal['claimed'] : []);
                $result = Maintenance::resume($root, $appId, self::empty($target), $target, $inputPaths, $compatibility, $intent.':'.$id, $checks, true, $claimed);
            } else {
                $this->validate(array_values(array_unique(array_merge(array_keys(self::adopted($root, $appId, $this->catalog)), [$id]))));
                $plan = $this->inspect($root, $appId, $target, $inputPaths, $compatibility, $claim);
                if (! $plan->executable()) {
                    $results[$id] = ['status' => 'not_executable', 'files' => $plan->files];

                    return $this->outcome($root, $appId, $ids, 'not_executable', $intent, $id, $results);
                }
                $result = Maintenance::apply($plan, $inputPaths, $intent.':'.$id, $checks, true);
            }
            $results[$id] = $result;
            if ($result['status'] !== 'completed') {
                return $this->outcome($root, $appId, $ids, $result['status'], $intent, $id, $results);
            }
        }

        return $this->outcome($root, $appId, $ids, 'completed', $intent, null, $results);
    }

    /**
     * The intents of a lineage's unfinished updates, read from Mod's journals: no installed record committed the
     * journal's plan, and the record has not moved off its baseline. Reads maintenance metadata only.
     *
     * @return list<string>
     */
    public static function pendingIntents(string $root, string $lineage): array
    {
        $record = $root.'/.mod-maintenance/installed/'.hash('sha256', $lineage).'.json';
        $installed = self::data($record);
        $intents = [];
        foreach (glob($root.'/.mod-maintenance/journal-*.json') ?: [] as $path) {
            $envelope = self::data($path)['envelope'] ?? null;
            if (! is_array($envelope) || ($envelope['lineage'] ?? null) !== $lineage || ! is_string($envelope['intent'] ?? null)) {
                continue;
            }
            $finished = ($installed['commit']['plan'] ?? null) === ($envelope['plan'] ?? false);
            $unmoved = $installed === null ? ($envelope['originals'] ?? null) === [] : ($installed['artifact']['identity'] ?? null) === ($envelope['baseline'] ?? false);
            if (! $finished && $unmoved) {
                $intents[] = $envelope['intent'];
            }
        }

        return $intents;
    }

    /**
     * How to finish the unfinished update a `pending:<lineage>` refusal names, or null when the message is not one.
     *
     * @return list<string>|null
     */
    public static function pendingHint(string $root, string $message): ?array
    {
        if (! preg_match('/pending:([^)\s]+)/', $message, $match)) {
            return null;
        }
        $lineage = $match[1];
        $lines = ['Pending lineage: '.$lineage.'. Finish it before any other plan.'];
        $intents = str_starts_with($lineage, self::PREFIX) ? self::pendingIntents($root, $lineage) : [];
        if ($intents === []) {
            return [...$lines, 'Its intent is the "intent" field of its journal in .mod-maintenance/journal-*.json.'];
        }
        $feature = substr($lineage, strlen(self::PREFIX));
        foreach ($intents as $intent) {
            $lines[] = 'Intent: '.$intent;
            // kit:add names each feature's intent `<intent>:<feature>`; any other intent is a kit:update one.
            $lines[] = str_ends_with($intent, ':'.$feature)
                ? 'Resume: php artisan kit:add '.$feature.' --resume --intent='.substr($intent, 0, -strlen(':'.$feature)).' (name every feature of the original request, and its --claim list, to finish them all)'
                : 'Resume: php artisan kit:update resume --lineage='.$feature.' --intent='.$intent.' --exclusive-checkout';
        }

        return $lines;
    }

    /** A sorted, unique claim list of clean relative paths. */
    private static function claims(array $claim): array
    {
        $claim = array_values(array_unique(array_filter(array_map('trim', $claim), fn (string $path): bool => $path !== '')));
        sort($claim);

        return $claim;
    }

    /** The envelope of the journal Mod keeps for one intent, if there is one. */
    private static function journal(string $root, string $intent): ?array
    {
        $envelope = self::data($root.'/.mod-maintenance/journal-'.hash('sha256', $intent).'.json')['envelope'] ?? null;

        return is_array($envelope) ? $envelope : null;
    }

    /** One maintenance metadata file, read as data; a missing, linked or unreadable file is null. */
    private static function data(string $path): ?array
    {
        if (is_link($path) || ! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true, 64);

        return is_array($data) ? $data : null;
    }

    /**
     * A run's result, with the requested features (requirements included) that are adopted and that remain.
     *
     * @param  list<string>  $ids
     */
    private function outcome(string $root, string $appId, array $ids, string $status, string $intent, ?string $failed, array $results): array
    {
        $closure = $this->closure($ids);
        $done = array_values(array_intersect($closure, array_keys(self::adopted($root, $appId, $this->catalog))));

        return ['status' => $status, 'intent' => $intent, 'failed' => $failed, 'features' => $results, 'done' => $done, 'remaining' => array_values(array_diff($closure, $done))];
    }

    /** One adopted feature's update plan, from its installed artifact (or an explicit original catalog) to this catalog. */
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
        $target = $this->artifact($id, $bindings);
        if ((self::adopted($root, $appId, $this->catalog)[$id]['identity'] ?? null) === $target->identity()) {
            // Already completed: the record is the target now, so there is nothing left to resume.
            return ['status' => 'completed', 'no_op' => true, 'plan' => null, 'receipt' => null, 'written' => [], 'reconciled' => [], 'checks' => [], 'orphans' => []];
        }
        [$baseline, $target, $compatibility] = $this->update($root, $appId, $id, $bindings, $original);

        return Maintenance::resume($root, $appId, $baseline, $target, $inputPaths, $compatibility, $intent, $this->checks($id, $target, $hostOperationCheck), true);
    }

    /**
     * The original is the installed artifact itself, rebuilt from its record, so an app upgrades after the catalog
     * it was installed from is gone. An explicit original catalog must rebuild exactly that artifact.
     *
     * @return array{0: Artifact, 1: Artifact, 2: array<string, string>}
     */
    private function update(string $root, string $appId, string $id, array $bindings, ?Catalog $original): array
    {
        $installed = self::adopted($root, $appId, $this->catalog);
        if (! isset($installed[$id])) {
            throw new RuntimeException('Feature '.$id.' is not adopted; add it with kit:add.');
        }
        $baseline = $original === null ? self::installedArtifact($root, $id, $installed[$id]['identity']) : (new self($this->lifecycle, $original))->artifact($id, $bindings);
        if ($baseline->identity() !== $installed[$id]['identity']) {
            throw new RuntimeException('The original given must be exactly the installed artifact, features and bindings included.');
        }

        return [$baseline, $this->artifact($id, $bindings), $this->compatibility($id, $installed, [])];
    }

    /**
     * Rebuilds one feature's installed artifact from its Mod record. Mod has already verified the record
     * (owner, root, app and fingerprint) in Checkout::installed(); the rebuilt identity must equal it.
     */
    private static function installedArtifact(string $root, string $id, string $identity): Artifact
    {
        $path = $root.'/.mod-maintenance/installed/'.hash('sha256', self::name($id)).'.json';
        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException('Missing or linked installed record for feature '.$id.'.');
        }
        $saved = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR)['artifact'] ?? null;
        if (! is_array($saved) || ($saved['name'] ?? null) !== self::name($id) || ($saved['metadata']['feature'] ?? null) !== $id || ! is_array($saved['files'] ?? null)) {
            throw new RuntimeException('Invalid installed record for feature '.$id.'.');
        }
        $files = [];
        foreach ($saved['files'] as $file => $encoded) {
            $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
            if ($bytes === false) {
                throw new RuntimeException('Invalid installed record for feature '.$id.'.');
            }
            $files[$file] = $bytes;
        }
        $artifact = new Artifact($saved['name'], $saved['version'], $files, $saved['groups'], $saved['bindings'], $saved['checks'], $saved['metadata'], $saved['requires'] ?? [], $saved['host_owned'] ?? []);
        if ($artifact->identity() !== $identity) {
            throw new RuntimeException('Installed record does not rebuild the installed artifact for feature '.$id.'.');
        }

        return $artifact;
    }

    /** Refuses while any lineage has an unfinished update, whichever it is. */
    private function settled(string $root, string $appId): void
    {
        // A name no lineage has, so Mod reports every unfinished lineage.
        $probe = new Artifact('tey/kit', '0', [], [], [], ['settled']);
        Checkout::inspect($root, $appId, $probe, $probe, [], ['settled' => 'passed']);
    }

    /**
     * Only the reviewed claims among this feature's paths reach Mod: a listed identical file is claimed without a write,
     * a listed differing file is a conflict, and an unlisted existing file stays unowned.
     *
     * @param  list<string>  $claim
     */
    private function inspect(string $root, string $appId, Artifact $target, array $inputPaths, array $compatibility, array $claim): FilePlan
    {
        return Checkout::inspect($root, $appId, self::empty($target), $target, $inputPaths, $compatibility, array_values(array_intersect($claim, array_keys($target->files))));
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
