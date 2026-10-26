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
     * with what to change, and the plan is not executable until it is met.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $claim
     * @return array{plan: string, executable: bool, features: list<array>, claim: list<string>, claimable: list<string>, missing_packages: array<string, string>, host_steps: list<string>, unmet: list<string>}
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
        $unmet = $this->unmet(array_values(array_diff($closure, array_keys($installed))));
        $steps = $unmet;
        foreach ($missing as $package => $constraint) {
            $steps[] = 'composer require '.$package.':"'.$constraint.'"';
        }
        if (array_filter($paths, fn (string $path): bool => str_starts_with($path, 'database/migrations/'))) {
            $steps[] = 'php artisan migrate';
        }
        if (array_filter($paths, fn (string $path): bool => str_starts_with($path, 'resources/js/'))) {
            $steps[] = 'rebuild the frontend';
        }

        return ['plan' => $identity, 'executable' => $unmet === [] && ! in_array(false, array_column($rows, 'executable'), true), 'features' => $rows, 'claim' => $claim, 'claimable' => $claimable, 'missing_packages' => $missing, 'host_steps' => $steps, 'unmet' => $unmet];
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
     * (`{"format": 1, "releases": {"<release>": {"<feature>": {"<path>": "<sha256>"}}}}`). Hashes only: a match
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
            if (! is_string($release) || $release === 'current' || ! is_array($files) || array_filter($files, fn ($hash, $file): bool => ! is_string($file) || ! is_string($hash) || ! preg_match('/\A[0-9a-f]{64}\z/', $hash), ARRAY_FILTER_USE_BOTH) !== []) {
                throw new RuntimeException('Invalid catalog history.');
            }
            if ($files !== []) {
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
            // kit:add names each feature's intent `<intent>:<feature>`; any other intent is a kit:update one.
            $lines[] = str_ends_with($intent, ':'.$feature)
                ? 'Resume: php artisan kit:add '.$feature.' --resume --intent='.substr($intent, 0, -strlen(':'.$feature)).' (name every feature of the original request, and its --claim list, to finish them all)'
                : 'Resume: php artisan kit:update resume --lineage='.$feature.' --intent='.$intent.' --exclusive-checkout';
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
                $message = match ($kind) {
                    'user_model' => self::userModel($step['implements'], $step['uses']),
                    default => throw new RuntimeException('Unsupported host step: '.$id.'.'.$kind),
                };
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

    /** One adopted feature's update plan, from its installed artifact (or an explicit original catalog) to this catalog. */
    public function updatePlan(string $root, string $appId, string $id, array $bindings, array $inputPaths, ?Catalog $original = null): FilePlan
    {
        $runtime = $this->lifecycle->runtime();
        $this->settled($root, $appId);
        [$baseline, $target, $compatibility, $claim] = $this->update($root, $appId, $id, $bindings, $original);
        $this->validate(array_keys(self::adopted($root, $appId, $this->catalog)));
        $plan = Checkout::inspect($root, $appId, $baseline, $target, $inputPaths, $compatibility, $claim);
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

        return Maintenance::apply($plan, $inputPaths, $intent, $this->checks($id, $plan->target->bindings, $this->artifact($id, $plan->target->bindings)->identity(), $hostOperationCheck), true);
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
        [$baseline, $target, $compatibility, $claim] = $this->update($root, $appId, $id, $bindings, $original);

        return Maintenance::resume($root, $appId, $baseline, $target, $inputPaths, $compatibility, $intent, $this->checks($id, $bindings, $this->artifact($id, $bindings)->identity(), $hostOperationCheck), true, $claim);
    }

    /**
     * The original is the installed artifact itself, rebuilt from its record, so an app upgrades after the catalog
     * it was installed from is gone. An explicit original catalog must rebuild exactly that artifact.
     *
     * A feature adopted in place keeps the files the app had edited out of the lineage; see reconcile().
     *
     * @return array{0: Artifact, 1: Artifact, 2: array<string, string>, 3: list<string>}
     */
    private function update(string $root, string $appId, string $id, array $bindings, ?Catalog $original): array
    {
        $installed = self::adopted($root, $appId, $this->catalog);
        if (! isset($installed[$id])) {
            throw new RuntimeException('Feature '.$id.' is not adopted; '.($this->onDisk($root, $id) ? 'its files are already here, so adopt them in place: php artisan kit:adopt '.$id.' plan' : 'add it with kit:add.'));
        }
        $baseline = $original === null ? self::installedArtifact($root, $id, $installed[$id]['identity']) : (new self($this->lifecycle, $original))->artifact($id, $bindings);
        if ($baseline->identity() !== $installed[$id]['identity']) {
            throw new RuntimeException('The original given must be exactly the installed artifact, features and bindings included.');
        }

        [$target, $claim] = $this->reconcile($root, $baseline, $this->artifact($id, $bindings));

        return [$baseline, $target, $this->compatibility($id, $installed, []), $claim];
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
    private function checks(string $id, array $bindings, string $identity, Closure $hostOperationCheck): array
    {
        $verify = function () use ($id, $bindings, $identity): bool {
            $this->lifecycle->runtime();
            if (in_array('failed', Lifecycle::compatibility(Blueprint::lineage($id, $this->catalog)['feature']['requires']['packages']), true)) {
                throw new RuntimeException('Incompatible feature dependency: '.$id);
            }
            if ($this->artifact($id, $bindings)->identity() !== $identity) {
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
