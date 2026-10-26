<?php

namespace Tey\Kit;

use Closure;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use RuntimeException;
use Tey\Kit\Features\Core\Defaults;
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
     * A host step a feature being added declares, and the app has not met, is listed in `unmet` (and `host_steps`)
     * with what to change, and the plan is not executable until it is met. Every other host step, npm requirement and
     * optional integration is reported (`steps`, `npm`, `optional`) with `attention` and `warnings`, and never blocks.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $claim
     * @return array{plan: string, executable: bool, features: list<array>, claim: list<string>, claimable: list<string>, missing_packages: array<string, string>, host_steps: list<string>, unmet: list<string>, steps: list<array>, npm: array<string, array>, optional: list<array>, attention: bool, warnings: list<string>}
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
            $rows[] = ['feature' => $id, 'action' => 'add', 'target' => $target->identity(), 'files' => $plan->files, 'claimable' => $candidates, 'compatibility' => $compatibility, 'executable' => $plan->executable(), 'migrations' => HostRequirements::migrations($plan->files)];
        }
        $stray = array_diff($claim, $paths);
        if ($stray !== []) {
            throw new RuntimeException('Claims must name files a feature being added delivers: '.implode(', ', $stray).'.');
        }
        $adding = array_values(array_diff($closure, array_keys($installed)));
        $requirements = $this->requirements($root, $adding);
        $inputs = array_map(fn (string $path): string => hash_file('sha256', $root.'/'.$path), $inputPaths);
        // The reviewed requirement outcomes are bound too: a host change to them between preview and apply needs a fresh preview.
        $identity = 'sha256:'.hash('sha256', json_encode([$appId, $root, $rows, $inputs, $claim, HostRequirements::bound($requirements)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $unmet = $this->unmet($adding);
        $steps = $unmet;
        foreach ($requirements['steps'] as $step) {
            if ($step['status'] === 'unmet') {
                $steps[] = $step['message'];
            }
        }
        foreach ($missing as $package => $constraint) {
            $steps[] = 'composer require '.$package.':"'.$constraint.'"';
        }
        if (array_filter($paths, fn (string $path): bool => str_starts_with($path, 'database/migrations/'))) {
            $steps[] = 'php artisan migrate';
        }
        if (array_filter($paths, fn (string $path): bool => str_starts_with($path, 'resources/js/'))) {
            $steps[] = 'rebuild the frontend';
        }

        return ['plan' => $identity, 'executable' => $unmet === [] && ! in_array(false, array_column($rows, 'executable'), true), 'features' => $rows, 'claim' => $claim, 'claimable' => $claimable, 'missing_packages' => $missing, 'host_steps' => $steps, 'unmet' => $unmet] + $requirements;
    }

    /**
     * The host requirements of the given features: their steps, npm requirements and optional integrations, checked.
     *
     * @param  list<string>  $ids
     */
    public function requirements(string $root, array $ids): array
    {
        $features = [];
        foreach ($ids as $id) {
            $features[$id] = Blueprint::lineage($id, $this->catalog)['feature'];
        }

        return HostRequirements::of($root, $features);
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
            $unmet = implode('', array_map(fn (string $step): string => "\nUnmet host step: ".$step, $preview['unmet']));
            throw new RuntimeException('The plan is not executable; nothing was written.'.$unclaimed.$unmet);
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
            $result = Maintenance::apply($plan, $inputPaths, $intent.':'.$id, $this->checks($id, $target->bindings, $target->identity(), $hostOperationCheck), true);
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
        $unmet = $this->unmet($remaining);
        if ($unmet !== []) {
            throw new RuntimeException('Meet each host step first; nothing was written.'.implode('', array_map(fn (string $step): string => "\nUnmet host step: ".$step, $unmet)));
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
            $checks = $this->checks($id, $target->bindings, $target->identity(), $hostOperationCheck);
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
     * Read-only: what adopting a feature already on disk would record, for an app that installed it before lifecycle
     * lineages existed. Nothing is overwritten. Each file the feature delivers is
     * - `identical` to the bytes of a known release (the catalog's history.json, or this catalog): claimed as it is;
     * - `edited`: kept by the app, out of the lineage, against the release `--from` names (else the closest one, the
     *   release most identical files match), so a later kit:update keeps it until that release changes it;
     * - `missing`: created with this catalog's bytes.
     *
     * @return array{plan: FilePlan, from: string, files: array<string, string>, kept: array<string, string>}
     */
    public function adoptPlan(string $root, string $appId, string $id, array $bindings, array $inputPaths, ?string $from = null): array
    {
        $runtime = $this->lifecycle->runtime();
        $this->settled($root, $appId);
        $installed = self::adopted($root, $appId, $this->catalog);
        if (isset($installed[$id])) {
            throw new RuntimeException('Feature '.$id.' is already adopted; update it with: php artisan kit:update plan --lineage='.$id);
        }
        $this->validate(array_values(array_unique(array_merge(array_keys($installed), [$id]))));
        $target = $this->artifact($id, $bindings);
        $history = $this->history($id) + ['current' => array_map(fn (string $bytes): string => hash('sha256', $bytes), $target->files)];
        $disk = [];
        foreach (array_keys($target->files) as $path) {
            $disk[$path] = self::read($root, $path);
        }
        $present = array_filter($disk, fn (?string $bytes): bool => $bytes !== null);
        if ($present === []) {
            throw new RuntimeException('None of '.$id.'\'s files are here; add it with: php artisan kit:add '.$id);
        }
        $known = fn (string $path, string $bytes): bool => in_array(hash('sha256', $bytes), array_column($history, $path), true);
        if ($from === null) {
            $matches = array_map(fn (array $files): int => count(array_filter($present, fn (string $bytes, string $path): bool => ($files[$path] ?? null) === hash('sha256', $bytes), ARRAY_FILTER_USE_BOTH)), $history);
            if (max($matches) === 0) {
                throw new RuntimeException('No file of '.$id.' matches a known release; name the release it was installed from with --from: '.implode(', ', array_keys($history)).'.');
            }
            // The closest release; a tie goes to the later one.
            $from = array_search(max($matches), array_reverse($matches, true), true);
        } elseif (! isset($history[$from])) {
            throw new RuntimeException('Unknown release '.$from.' for '.$id.'; known: '.implode(', ', array_keys($history)).'.');
        }
        $files = [];
        $kept = [];
        $owned = [];
        foreach ($disk as $path => $bytes) {
            if ($bytes === null) {
                $files[$path] = 'missing';
                $owned[$path] = $target->files[$path];
            } elseif ($known($path, $bytes)) {
                $files[$path] = 'identical';
                $owned[$path] = $bytes;
            } else {
                $files[$path] = 'edited';
                $kept[$path] = $history[$from][$path] ?? $history['current'][$path];
            }
        }
        $record = self::keeping(new Artifact($target->name, $target->version, $owned + $target->files, $target->groups, $target->bindings, $target->checks, $target->metadata, $target->requires, $target->hostOwned), $kept, ['adopted_from' => $from]);
        $claim = array_keys(array_filter($files, fn (string $state): bool => $state === 'identical'));
        $plan = Checkout::inspect($root, $appId, self::empty($record), $record, $inputPaths, $this->compatibility($id, $installed, []), $claim);
        $this->lifecycle->inputs($root, $inputPaths, $runtime);

        return ['plan' => $plan, 'from' => $from, 'files' => $files, 'kept' => $kept];
    }

    /** Records the reviewed adoption: identical files are claimed without a write, missing ones created, edited ones left alone. */
    public function adoptApply(FilePlan $plan, string $id, array $inputPaths, string $intent, Closure $hostOperationCheck, ?string $from = null): array
    {
        $fresh = $this->adoptPlan($plan->root, $plan->appId, $id, $plan->target->bindings, $inputPaths, $from)['plan'];
        if ($fresh->identity() !== $plan->identity()) {
            throw new RuntimeException('Plan does not match the immutable feature and current checkout.');
        }
        $this->lifecycle->hostCheck($plan->root, $inputPaths, $hostOperationCheck);

        return Maintenance::apply($plan, $inputPaths, $intent, $this->checks($id, $plan->target->bindings, $this->artifact($id, $plan->target->bindings)->identity(), $hostOperationCheck), true);
    }

    /**
     * Undoes an adoption whose apply stopped without finishing (a crash: a fresh apply that fails rolls itself back).
     * Every file the journal planned to create is removed when it still holds the planned bytes; claimed files were
     * never written and stay. A created file that changed since, a finished adoption or any other journal is refused,
     * and nothing is removed.
     *
     * @return list<string> The removed paths.
     */
    public function adoptRollback(string $root, string $appId, string $id, string $intent): array
    {
        $this->lifecycle->runtime();
        $path = $root.'/.mod-maintenance/journal-'.hash('sha256', $intent).'.json';
        $envelope = self::journal($root, $intent);
        if ($envelope === null || ($envelope['lineage'] ?? null) !== self::name($id) || ($envelope['app_id'] ?? null) !== $appId || ($envelope['originals'] ?? null) !== [] || ! is_array($envelope['files'] ?? null) || ! is_array($envelope['targets'] ?? null)) {
            throw new RuntimeException('No unfinished adoption of '.$id.' under intent '.$intent.'.');
        }
        if (! in_array($intent, self::pendingIntents($root, self::name($id)), true)) {
            throw new RuntimeException('The adoption of '.$id.' under intent '.$intent.' already finished; update it with: php artisan kit:update plan --lineage='.$id);
        }
        $created = [];
        foreach ($envelope['files'] as $file => $entry) {
            if (($entry['state'] ?? null) !== 'create') {
                continue;
            }
            $planned = base64_decode((string) ($envelope['targets'][$file] ?? ''), true);
            $current = self::read($root, $file);
            if ($current !== null && $current !== $planned) {
                throw new RuntimeException('Created file changed since the adoption stopped, so nothing was rolled back: '.$file);
            }
            if ($current !== null) {
                $created[] = $file;
            }
        }
        foreach ($created as $file) {
            unlink($root.'/'.$file);
            // Drop the directories the apply created, up to the first one that holds anything else.
            for ($dir = dirname($root.'/'.$file); $dir !== $root && is_dir($dir) && count(scandir($dir)) === 2; $dir = dirname($dir)) {
                rmdir($dir);
            }
        }
        unlink($path);

        return $created;
    }

    /**
     * The features with files in the checkout and no lineage: an app that installed them before lifecycle lineages existed.
     *
     * @return list<string>
     */
    public function preLifecycle(string $root, string $appId): array
    {
        $installed = self::adopted($root, $appId, $this->catalog);

        return array_values(array_filter(($this->catalog ?? Catalog::package())->featureIds(), fn (string $id): bool => ! isset($installed[$id]) && $this->onDisk($root, $id)));
    }

    /**
     * Each earlier release's file hashes for one feature, oldest first, from the catalog's `history.json`
     * (`{"format": 1, "releases": {"<release>": {"<feature>": {"<path>": "<sha256>"}}}}`, last `current`). Hashes only: a match
     * says which release a file came from, and the bytes recorded are always the app's own.
     *
     * @return array<string, array<string, string>>
     */
    public function history(string $id): array
    {
        $path = ($this->catalog ?? Catalog::package())->root.'/history.json';
        if (! file_exists($path)) {
            return [];
        }
        $history = self::data($path);
        if (($history['format'] ?? null) !== 1 || ! is_array($history['releases'] ?? null)) {
            throw new RuntimeException('Invalid catalog history.');
        }
        $releases = [];
        foreach ($history['releases'] as $release => $features) {
            $files = $features[$id] ?? [];
            if (! is_string($release) || ! is_array($files) || array_filter($files, fn ($hash, $file): bool => ! is_string($file) || ! is_string($hash) || ! preg_match('/\A[0-9a-f]{64}\z/', $hash), ARRAY_FILTER_USE_BOTH) !== []) {
                throw new RuntimeException('Invalid catalog history.');
            }
            // `current` guards the file against drift (bin/catalog-history.php --check); this catalog itself is always `current`.
            if ($files !== [] && $release !== 'current') {
                $releases[$release] = $files;
            }
        }

        return $releases;
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
            // kit:add names each feature's intent `<intent>:<feature>`; another first install is a kit:adopt one, and the rest are kit:update ones.
            $lines[] = match (true) {
                str_ends_with($intent, ':'.$feature) => 'Resume: php artisan kit:add '.$feature.' --resume --intent='.substr($intent, 0, -strlen(':'.$feature)).' (name every feature of the original request, and its --claim list, to finish them all)',
                (self::journal($root, $intent)['originals'] ?? null) === [] => 'Roll back: php artisan kit:adopt '.$feature.' rollback --intent='.$intent.' --exclusive-checkout (then plan the adoption again)',
                default => 'Resume: php artisan kit:update resume --lineage='.$feature.' --intent='.$intent.' --exclusive-checkout',
            };
        }

        return $lines;
    }

    /**
     * The host steps the features declare that the app has not met, each with what to change. Read-only: the
     * app's files are host-owned, so kit names the change and never makes it.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function unmet(array $ids): array
    {
        $unmet = [];
        foreach ($ids as $id) {
            foreach (Blueprint::lineage($id, $this->catalog)['feature']['host_steps'] ?? [] as $kind => $step) {
                // The other kinds never block; HostRequirements reports them. Blueprint refused unknown kinds.
                if ($kind !== 'user_model') {
                    continue;
                }
                $message = self::userModel($step['implements'], $step['uses']);
                if ($message !== null && ! in_array($message, $unmet, true)) {
                    $unmet[] = $message;
                }
            }
        }

        return $unmet;
    }

    /**
     * Checked by reflection: kit.models.user (else the `users` auth provider's model) and the concierge.models.user the
     * app will run with name one model, and it implements the contract and uses the trait. Null when met.
     */
    private static function userModel(string $contract, string $trait): ?string
    {
        $model = config('kit.models.user') ?? config('auth.providers.users.model');
        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return 'Set kit.models.user (or the users auth provider\'s model) to your user model class.';
        }
        $concierge = Defaults::value(app(), 'concierge', 'models.user');
        if ($concierge !== $model) {
            return 'concierge.models.user ('.(is_string($concierge) ? $concierge : 'unset').') must name the user model '.$model.'. Publish config/concierge.php and set models.user to '.$model.'::class.';
        }
        $implements = is_a($model, $contract, true);
        $uses = in_array($trait, class_uses_recursive($model), true);
        if ($implements && $uses) {
            return null;
        }
        $class = new ReflectionClass($model);
        $file = (string) $class->getFileName();
        $path = str_starts_with($file, base_path().'/') ? substr($file, strlen(base_path()) + 1) : $file;
        $short = fn (string $name): string => substr((string) strrchr('\\'.$name, '\\'), 1);
        $implement = "\n    class ".$class->getShortName().' extends ... implements '.$short($contract);
        $use = "\n        use ".$short($trait).';';

        return match (true) {
            $implements => $model.' implements '.$contract.' but must also use '.$trait.'. In '.$path.', add the import and use the trait in the class body:'
                ."\n    use ".$trait.';'.$use,
            $uses => $model.' uses '.$trait.' but must also implement '.$contract.'. In '.$path.', add the import and implement the contract on the class declaration:'
                ."\n    use ".$contract.';'.$implement,
            default => $model.' must implement '.$contract.'. In '.$path.', add the two imports, implement the contract on the class declaration and use the trait in its body:'
                ."\n    use ".$contract.';'
                ."\n    use ".$trait.';'.$implement.$use,
        };
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

    /**
     * One feature's next update step, from its installed artifact (or an explicit original catalog) to this catalog.
     * Usually that is the whole update. When the update moves files to a feature that is not installed yet, it takes
     * steps, each one an ordinary Mod update: see step().
     */
    public function updatePlan(string $root, string $appId, string $id, array $bindings, array $inputPaths, ?Catalog $original = null): FilePlan
    {
        $runtime = $this->lifecycle->runtime();
        $this->settled($root, $appId);
        $step = $this->step($root, $appId, $id, $bindings, $original);
        // The app as it will be once the step's feature and any receivers it waits on are installed.
        $ids = array_unique([...array_keys(self::adopted($root, $appId, $this->catalog)), $step['feature'], ...$step['waiting']]);
        $this->validate(array_values(array_intersect(Blueprint::featureIds($this->catalog), $ids)));
        $plan = Checkout::inspect($root, $appId, $step['baseline'], $step['target'], $inputPaths, $step['compatibility'], $step['claim'], $step['removals']);
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
        $step = $this->step($plan->root, $plan->appId, $id, $plan->target->bindings, $original);

        return Maintenance::apply($plan, $inputPaths, $intent, $this->checks($step['feature'], $plan->target->bindings, $step['expected']()->identity(), $hostOperationCheck, $step['expected']), true) + ['next' => $step['next']];
    }

    public function updateResume(string $root, string $appId, string $id, array $bindings, array $inputPaths, string $intent, Closure $hostOperationCheck, ?Catalog $original = null): array
    {
        $runtime = $this->lifecycle->runtime();
        $this->lifecycle->inputs($root, $inputPaths, $runtime);
        $this->lifecycle->hostCheck($root, $inputPaths, $hostOperationCheck);
        $installed = self::adopted($root, $appId, $this->catalog)[$id] ?? null;
        $record = $installed === null ? null : self::installedArtifact($root, $id, $installed['identity']);
        if ($record !== null && $this->reconcile($root, $record, $this->artifact($id, $bindings))[0]->identity() === $installed['identity']) {
            // Already completed: the record is the target now, so there is nothing left to resume.
            return ['status' => 'completed', 'no_op' => true, 'plan' => null, 'receipt' => null, 'written' => [], 'reconciled' => [], 'checks' => [], 'orphans' => []];
        }
        $step = $this->step($root, $appId, $id, $bindings, $original);

        return Maintenance::resume($root, $appId, $step['baseline'], $step['target'], $inputPaths, $step['compatibility'], $intent, $this->checks($step['feature'], $bindings, $step['expected']()->identity(), $hostOperationCheck, $step['expected']), true, $step['claim'], $step['removals']) + ['next' => $step['next']];
    }

    /**
     * The next Mod update for `kit:update --lineage=<id>`.
     *
     * The original is the installed artifact itself, rebuilt from its record, so an app upgrades after the catalog
     * it was installed from is gone. An explicit original catalog must rebuild exactly that artifact.
     *
     * A feature adopted in place keeps the files the app had edited out of the lineage; see reconcile().
     *
     * Paths the target drops reach Mod as `removals`: a `moved` path is released, a `removed` path is deleted
     * (delete-if-unchanged) or released (keep-as-host). A dropped path with neither is refused.
     *
     * Mod moves a file as release on the giver, then claim on the receiver. A giver whose target requires a receiver
     * that is not installed cannot go first as it is, and the receiver's target bytes may differ from the released
     * ones, so a move to an uninstalled feature takes three runs, each reviewed on its own:
     *  1. `--lineage=<giver>`: the giver goes to an interim artifact, its target without the requires on the
     *     uninstalled receivers, releasing the moved files. Its metadata keeps the released bytes. An edited moved
     *     file stops this step before any write, with its diff.
     *  2. `--lineage=<receiver>`: the receiver is installed at exactly those released bytes, claiming them, so
     *     nothing is written. A later `--lineage=<receiver>` updates it to its target like any other update.
     *  3. `--lineage=<giver>`: the interim goes to the target, which now finds its requires installed.
     *
     * @return array{feature: string, baseline: Artifact, target: Artifact, compatibility: array<string, string>, claim: list<string>, removals: array<string, string>, waiting: list<string>, expected: Closure(): Artifact, next: ?string}
     */
    private function step(string $root, string $appId, string $id, array $bindings, ?Catalog $original): array
    {
        $installed = self::adopted($root, $appId, $this->catalog);
        if (! isset($installed[$id])) {
            foreach (array_keys($installed) as $giver) {
                $released = self::released(self::installedArtifact($root, $giver, $installed[$giver]['identity']), $id);
                if ($released !== []) {
                    $receiver = $this->received($id, $bindings, $giver, $released);

                    return ['feature' => $id, 'baseline' => self::empty($receiver), 'target' => $receiver, 'compatibility' => $this->compatibility($id, $installed, []), 'claim' => array_keys($receiver->files), 'removals' => [], 'waiting' => [], 'expected' => fn (): Artifact => $this->received($id, $bindings, $giver, $released), 'next' => 'Run kit:update plan --lineage='.$giver.' to finish its update, and kit:update plan --lineage='.$id.' to update '.$id.' to its target.'];
                }
            }
            throw new RuntimeException('Feature '.$id.' is not adopted; '.($this->onDisk($root, $id) ? 'its files are already here, so adopt them in place: php artisan kit:adopt '.$id.' plan' : 'add it with kit:add.'));
        }
        $baseline = $original === null ? self::installedArtifact($root, $id, $installed[$id]['identity']) : (new self($this->lifecycle, $original))->artifact($id, $bindings);
        if ($baseline->identity() !== $installed[$id]['identity']) {
            throw new RuntimeException('The original given must be exactly the installed artifact, features and bindings included.');
        }
        $target = $this->artifact($id, $bindings);
        [$removals, $moves] = $this->removals($id, $baseline, $target);
        $waiting = array_values(array_unique(array_filter($moves, fn (string $to): bool => ! isset($installed[$to]))));
        if ($waiting === []) {
            [$kept, $claim] = $this->reconcile($root, $baseline, $target);

            return ['feature' => $id, 'baseline' => $baseline, 'target' => $kept, 'compatibility' => $this->compatibility($id, $installed, []), 'claim' => $claim, 'removals' => $removals, 'waiting' => [], 'expected' => fn (): Artifact => $this->artifact($id, $bindings), 'next' => null];
        }
        $this->unedited($root, $id, $baseline, array_keys($moves));
        $released = array_intersect_key($baseline->files, $moves);
        $interim = $this->interim($target, $baseline->version, $released, $moves);

        return ['feature' => $id, 'baseline' => $baseline, 'target' => $interim, 'compatibility' => $this->compatibility($id, $installed, $waiting), 'claim' => [], 'removals' => $removals, 'waiting' => $waiting, 'expected' => fn (): Artifact => $this->interim($this->artifact($id, $bindings), $baseline->version, $released, $moves), 'next' => 'Run kit:update plan --lineage='.implode(' and --lineage=', $waiting).' to take the released files, then kit:update plan --lineage='.$id.' again to finish.'];
    }

    /**
     * The update target for a lineage whose record keeps app-edited files (`kept`: path => the release sha256 the edit
     * was made against). A kept file the release has not changed stays the app's, out of the target and never written.
     * Once the release changes it, it is claimed: Mod takes it without a write when the app already has the new bytes,
     * and plans it as a conflict otherwise, exactly as it does an edited owned file. A kept file the app deleted, or the
     * release dropped, is no longer kept.
     *
     * @return array{0: Artifact, 1: list<string>}
     */
    private function reconcile(string $root, Artifact $baseline, Artifact $target): array
    {
        $kept = $baseline->metadata['kept'] ?? [];
        if (! is_array($kept) || $kept === []) {
            return [$target, []];
        }
        $still = [];
        $claim = [];
        foreach ($kept as $path => $hash) {
            $current = isset($target->files[$path]) ? self::read($root, $path) : null;
            if ($current === null) {
                continue;
            }
            if (hash('sha256', $target->files[$path]) === $hash && $current !== $target->files[$path]) {
                $still[$path] = $hash;
            } else {
                $claim[] = $path;
            }
        }
        sort($claim);

        return [self::keeping($target, $still), $claim];
    }

    /**
     * The artifact without the kept paths, recording them in its metadata; the plain artifact when nothing is kept.
     *
     * @param  array<string, string>  $kept
     */
    private static function keeping(Artifact $target, array $kept, array $metadata = []): Artifact
    {
        if ($kept === [] && $metadata === []) {
            return $target;
        }
        ksort($kept);
        $metadata = $target->metadata + $metadata + ($kept === [] ? [] : ['kept' => $kept]);

        return new Artifact($target->name, $target->version, array_diff_key($target->files, $kept), $target->groups, $target->bindings, $target->checks, $metadata, $target->requires, $target->hostOwned);
    }

    /** One owned path's bytes, or null when it is absent; a link or non-file is refused. */
    private static function read(string $root, string $path): ?string
    {
        $file = $root.'/'.$path;
        if (is_link($file) || (file_exists($file) && ! is_file($file))) {
            throw new RuntimeException('Owned file must be a regular file: '.$path);
        }

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    /** Whether any file this catalog's feature delivers is already in the checkout. */
    private function onDisk(string $root, string $id): bool
    {
        foreach (array_keys(Blueprint::lineage($id, $this->catalog)['files']) as $path) {
            if (file_exists($root.'/'.$path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every baseline path the target drops, with its Mod policy, and the moved ones with their receiver.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function removals(string $id, Artifact $baseline, Artifact $target): array
    {
        $feature = Blueprint::lineage($id, $this->catalog)['feature'];
        $moved = array_column($feature['moved'] ?? [], 'to', 'path');
        $removed = array_column($feature['removed'] ?? [], 'policy', 'path');
        $removals = [];
        $moves = [];
        $unlisted = [];
        foreach (array_keys(array_diff_key($baseline->files, $target->files)) as $path) {
            if (isset($moved[$path])) {
                if (! isset(Blueprint::lineage($moved[$path], $this->catalog)['files'][$path])) {
                    throw new RuntimeException('Feature '.$id.' moves '.$path.' to '.$moved[$path].', which does not generate it.');
                }
                $removals[$path] = 'release';
                $moves[$path] = $moved[$path];
            } elseif (isset($removed[$path])) {
                $removals[$path] = $removed[$path] === 'delete-if-unchanged' ? 'delete' : 'release';
            } else {
                $unlisted[] = $path;
            }
        }
        if ($unlisted !== []) {
            throw new RuntimeException('Feature '.$id.'\'s update drops '.implode(', ', $unlisted).' with no `moved` or `removed` entry, so nothing was written and '.$id.' stays at its installed version.');
        }

        return [$removals, $moves];
    }

    /**
     * Refuses a move before any write when a moved file differs from the giver's installed bytes, showing the diff.
     *
     * @param  list<string>  $paths
     */
    private function unedited(string $root, string $id, Artifact $baseline, array $paths): void
    {
        $edited = [];
        foreach ($paths as $path) {
            $file = $root.'/'.$path;
            $current = is_file($file) && ! is_link($file) ? file_get_contents($file) : null;
            if ($current !== null && $current !== $baseline->files[$path]) {
                $edited[] = $path."\n".self::diff($baseline->files[$path], $current);
            }
        }
        if ($edited !== []) {
            throw new RuntimeException('Feature '.$id.'\'s update moves files you have edited, so nothing was written and '.$id.' stays at its installed version. For each file below, put your changes aside and restore the installed bytes (the diff runs from the installed file to yours), run the update, then reapply your changes:'."\n".implode("\n", $edited));
        }
    }

    /** A line diff from one text to another: changed lines with up to two lines of context, from a longest common subsequence. */
    private static function diff(string $from, string $to): string
    {
        $a = explode("\n", $from);
        $b = explode("\n", $to);
        // Trim the shared ends first, so the table covers only the changed middle.
        $start = 0;
        while ($start < count($a) && $start < count($b) && $a[$start] === $b[$start]) {
            $start++;
        }
        $end = 0;
        while ($end < count($a) - $start && $end < count($b) - $start && $a[count($a) - 1 - $end] === $b[count($b) - 1 - $end]) {
            $end++;
        }
        $x = array_slice($a, $start, count($a) - $start - $end);
        $y = array_slice($b, $start, count($b) - $start - $end);
        $ops = [];
        if (count($x) * count($y) <= 4_000_000) {
            $lcs = array_fill(0, count($x) + 1, array_fill(0, count($y) + 1, 0));
            for ($i = count($x) - 1; $i >= 0; $i--) {
                for ($j = count($y) - 1; $j >= 0; $j--) {
                    $lcs[$i][$j] = $x[$i] === $y[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
            [$i, $j] = [0, 0];
            while ($i < count($x) || $j < count($y)) {
                if ($i < count($x) && $j < count($y) && $x[$i] === $y[$j]) {
                    $ops[] = ['  ', $x[$i++]];
                    $j++;
                } elseif ($j < count($y) && ($i === count($x) || $lcs[$i][$j + 1] >= $lcs[$i + 1][$j])) {
                    $ops[] = ['+ ', $y[$j++]];
                } else {
                    $ops[] = ['- ', $x[$i++]];
                }
            }
        } else {
            $ops = [...array_map(fn (string $line): array => ['- ', $line], $x), ...array_map(fn (string $line): array => ['+ ', $line], $y)];
        }
        $before = array_map(fn (string $line): array => ['  ', $line], array_slice($a, max(0, $start - 2), min(2, $start)));
        $after = array_map(fn (string $line): array => ['  ', $line], array_slice($a, count($a) - $end, min(2, $end)));
        $lines = ['@@ line '.(max(0, $start - 2) + 1).' @@'];
        foreach ([...$before, ...$ops, ...$after] as [$mark, $line]) {
            $lines[] = rtrim($mark.$line);
        }
        // Collapse long unchanged runs to two lines either side.
        $out = [];
        $run = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '  ') || $line === '') {
                $run[] = $line;

                continue;
            }
            $out = [...$out, ...(count($run) > 5 ? [...array_slice($run, 0, 2), '  …', ...array_slice($run, -2)] : $run), $line];
            $run = [];
        }

        return implode("\n", [...$out, ...array_slice($run, 0, 2)]);
    }

    /**
     * The giver's step-1 artifact: its target, minus requires on receivers not installed yet, carrying the released
     * bytes for the receivers to claim.
     *
     * @param  array<string, string>  $released  path => the giver's installed bytes
     * @param  array<string, string>  $moves  path => receiving feature
     */
    private function interim(Artifact $target, string $version, array $released, array $moves): Artifact
    {
        ksort($released);
        $metadata = $target->metadata + ['released' => ['version' => $version, 'files' => array_map(fn (string $path): array => ['path' => $path, 'to' => $moves[$path], 'bytes' => base64_encode($released[$path])], array_keys($released))]];
        $receivers = array_map(self::name(...), array_values($moves));

        return new Artifact($target->name, $target->version, $target->files, $target->groups, $target->bindings, $target->checks, $metadata, array_diff_key($target->requires, array_flip($receivers)), $target->hostOwned);
    }

    /**
     * The released files an installed giver holds for one receiver, from its interim metadata.
     *
     * @return array{version?: string, files?: array<string, string>}
     */
    private static function released(Artifact $giver, string $receiver): array
    {
        $files = [];
        foreach ($giver->metadata['released']['files'] ?? [] as $file) {
            if (($file['to'] ?? null) === $receiver) {
                $files[$file['path']] = base64_decode($file['bytes'], true);
            }
        }

        return $files === [] ? [] : ['version' => $giver->metadata['released']['version'], 'files' => $files];
    }

    /**
     * The receiver's step-2 artifact: exactly the released files at the giver's bytes and version, so claiming them
     * writes nothing. Everything else comes from the receiver's target.
     *
     * @param  array{version: string, files: array<string, string>}  $released
     */
    private function received(string $id, array $bindings, string $giver, array $released): Artifact
    {
        $target = $this->artifact($id, $bindings);
        $outside = array_diff_key($released['files'], $target->files);
        if ($outside !== []) {
            throw new RuntimeException($giver.' released '.implode(', ', array_keys($outside)).' to '.$id.', which does not own them.');
        }
        $metadata = ['received_from' => $giver] + $target->metadata;

        return new Artifact($target->name, $released['version'], $released['files'], $target->groups, $target->bindings, $target->checks, $metadata, $target->requires, $target->hostOwned);
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
    private function checks(string $id, array $bindings, string $identity, Closure $hostOperationCheck, ?Closure $expected = null): array
    {
        $expected ??= fn (): Artifact => $this->artifact($id, $bindings);
        $verify = function () use ($id, $identity, $expected): bool {
            $this->lifecycle->runtime();
            if (in_array('failed', Lifecycle::compatibility(Blueprint::lineage($id, $this->catalog)['feature']['requires']['packages']), true)) {
                throw new RuntimeException('Incompatible feature dependency: '.$id);
            }
            if ($expected()->identity() !== $identity) {
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
