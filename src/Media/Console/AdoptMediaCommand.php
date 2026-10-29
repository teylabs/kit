<?php

namespace Tey\Kit\Media\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;
use Spatie\Multitenancy\Contracts\IsTenant;
use Symfony\Component\Console\Input\InputOption;
use Tey\Kit\Media\Jobs\AdoptChunk;
use Tey\Kit\Media\Operations\AdoptObject;

/**
 * The adopt command pattern: an app subclasses this once per collection, names the command and says which owners to look at and where each one's
 * existing object is. It is tenant-aware (spatie's TenantAware): it runs once per tenant, every tenant unless `--tenant=` names some, and refuses to
 * run with no current tenant. Owners are read in id chunks (`--chunk`, default 500) and each chunk is one queued, tenant-aware job on
 * `kit.media.queue` (`--sync` runs them inline). Re-running is safe: an owner whose object is already adopted is skipped. `--dry-run` writes nothing
 * and prints each tenant's counts only, never a key. A tenant whose source has rows (`sourceRows()`) but where no owner is found is a warning and a
 * failing exit, never a quiet success.
 */
abstract class AdoptMediaCommand extends Command
{
    use TenantAware;

    /** The collection the objects are adopted into. */
    protected string $collection;

    /** @return Builder<Model&HasMedia> The owners to consider. */
    abstract protected function owners(): Builder;

    /** @return array{0: string, 1: string}|null  [disk, key] of the owner's existing object, or null when it has none */
    abstract protected function source(Model $owner): ?array;

    /** Rows in the current tenant's source that hold an object to adopt, to catch an `owners()` that finds none of them; null skips the check. */
    protected function sourceRows(): ?int
    {
        return null;
    }

    public function __construct()
    {
        parent::__construct();
        $this->addOption('tenant', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The tenants to run for (default: all)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count what would be adopted; write nothing');
        $this->addOption('chunk', null, InputOption::VALUE_REQUIRED, 'Owners per job', '500');
        $this->addOption('sync', null, InputOption::VALUE_NONE, 'Run each chunk inline instead of queueing it');
    }

    public function handle(): int
    {
        $tenant = app(IsTenant::class)::current() ?? throw new RuntimeException('media.adopt.tenant_required');
        $label = 'tenant '.$tenant->getKey().': ';
        $chunk = max(1, (int) $this->option('chunk'));
        $found = $this->owners()->count();
        $rows = $this->sourceRows();
        if ($found === 0 && $rows !== null && $rows > 0) {
            $this->warn($label.'0 owners found, but the source has '.$rows.' rows: check owners() and the tenant scope.');

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $counts = ['owners' => 0, 'adopted' => 0, 'would_adopt' => 0, 'missing' => 0, 'no_source' => 0];
            $this->owners()->chunkById($chunk, function ($owners) use (&$counts): void {
                foreach ($owners as $owner) {
                    $counts['owners']++;
                    $counts[$this->classify($owner)]++;
                }
            });
            $this->line($label.implode(', ', array_map(fn (string $name, int $count): string => $name.' '.$count, array_keys($counts), $counts)));

            return self::SUCCESS;
        }
        $jobs = 0;
        $this->owners()->select($this->owners()->getModel()->getQualifiedKeyName())->chunkById($chunk, function ($owners) use (&$jobs): void {
            $job = new AdoptChunk(static::class, $owners->modelKeys());
            $this->option('sync') ? dispatch_sync($job) : dispatch($job->onQueue((string) config('kit.media.queue', 'media')));
            $jobs++;
        });
        $this->line($label.($this->option('sync') ? 'chunks run ' : 'chunks queued ').$jobs);

        return self::SUCCESS;
    }

    /**
     * Adopts each owner's object; already adopted and sourceless owners are skipped, and a missing object is counted, not fatal.
     *
     * @param  list<int|string>  $ids
     * @return array{adopted: int, skipped: int, missing: int}
     */
    public function adoptChunk(array $ids): array
    {
        $counts = ['adopted' => 0, 'skipped' => 0, 'missing' => 0];
        foreach ($this->owners()->whereKey($ids)->get() as $owner) {
            $state = $this->classify($owner);
            if ($state !== 'would_adopt') {
                $counts[$state === 'missing' ? 'missing' : 'skipped']++;

                continue;
            }
            [$disk, $key] = $this->source($owner);
            try {
                AdoptObject::run($owner, $this->collection, $disk, $key);
                $counts['adopted']++;
            } catch (RuntimeException) {
                $counts['missing']++;
            }
        }

        return $counts;
    }

    private function classify(Model $owner): string
    {
        $source = $this->source($owner);
        if ($source === null) {
            return 'no_source';
        }
        [$disk, $key] = $source;
        if (AdoptObject::adopted($owner, $this->collection, $disk, $key) !== null) {
            return 'adopted';
        }

        return Storage::disk($disk)->exists($key) ? 'would_adopt' : 'missing';
    }
}
