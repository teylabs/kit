<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\CatalogHost;
use Tey\Kit\LifecycleHost;
use Tey\Kit\Lineages;
use Throwable;

final class AddCommand extends Command
{
    protected $signature = 'kit:add {features* : Features to adopt; their requirements are added too} {--apply : Write the reviewed plan} {--expected-plan= : The plan identity the preview printed} {--intent= : Names this adoption, for resume} {--claim=* : Existing identical files to adopt after review; repeat it or separate with commas} {--resume : Finish a failed apply under the same intent, then the rest of the request} {--json : Print the result as JSON}';

    protected $description = 'Adopt features, one lineage each: a preview by default, writes only with --apply';

    public function handle(): int
    {
        try {
            if (! $this->laravel->bound(LifecycleHost::class)) {
                throw new RuntimeException('A trusted LifecycleHost binding is required.');
            }
            if ($this->option('apply') && $this->option('resume')) {
                throw new RuntimeException('Choose --apply or --resume, not both.');
            }
            $host = $this->laravel->make(LifecycleHost::class);
            $lineages = new Lineages($host->lifecycle(), $host instanceof CatalogHost ? $host->catalog() : null);
            $root = $this->laravel->basePath();
            $features = array_values($this->argument('features'));
            $claim = array_merge([], ...array_map(fn (string $paths): array => explode(',', $paths), $this->option('claim')));
            if ($this->option('resume')) {
                $intent = (string) $this->option('intent');
                if ($intent === '') {
                    throw new RuntimeException('Resume needs the --intent the apply printed.');
                }
                $result = $lineages->resume($root, $host->appId(), $features, $host->bindings(), $host->inputPaths(), $intent, $host->operationCheck(), $claim);
            } elseif ($this->option('apply')) {
                $result = $lineages->add($root, $host->appId(), $features, $host->bindings(), $host->inputPaths(), (string) $this->option('expected-plan'), $this->option('intent'), $host->operationCheck(), $claim);
            } else {
                $preview = $lineages->preview($root, $host->appId(), $features, $host->bindings(), $host->inputPaths(), $claim);
                $this->option('json') ? $this->line(json_encode($preview, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) : $this->describe($preview);

                return $preview['executable'] ? self::SUCCESS : self::FAILURE;
            }
            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } else {
                foreach ($result['features'] as $id => $feature) {
                    $this->line($id.': '.$feature['status']);
                }
                $this->line('intent: '.$result['intent']);
                $this->line('done: '.($result['done'] === [] ? 'none' : implode(', ', $result['done'])));
                if ($result['remaining'] !== []) {
                    $this->line('remaining: '.implode(', ', $result['remaining']));
                }
                if ($result['failed'] !== null) {
                    $claims = implode('', array_map(fn (string $path): string => ' --claim='.$path, $claim));
                    $this->error('Stopped at '.$result['failed'].'. Fix it, then run kit:add '.implode(' ', $features).$claims.' --resume --intent='.$result['intent'].'.');
                }
            }

            return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            foreach (Lineages::pendingHint($this->laravel->basePath(), $exception->getMessage()) ?? [] as $line) {
                $this->line($line);
            }

            return self::FAILURE;
        }
    }

    private function describe(array $preview): void
    {
        $this->line('plan: '.$preview['plan']);
        foreach ($preview['features'] as $row) {
            $this->line($row['feature'].': '.$row['action'].($row['executable'] ? '' : ' (not executable)'));
            $claimable = array_diff($row['claimable'] ?? [], $preview['claim']);
            foreach ($row['files'] ?? [] as $path => $file) {
                $this->line('  '.$file['state'].' '.$path.(isset($file['reason']) ? ' ('.$file['reason'].')' : ''));
            }
            foreach ($claimable as $path) {
                $this->line('  claimable '.$path.' (identical existing file; review it, then pass --claim='.$path.')');
            }
            foreach ($row['compatibility'] ?? [] as $requirement => $outcome) {
                if ($outcome !== 'passed') {
                    $this->line('  '.$outcome.' '.$requirement);
                }
            }
        }
        foreach ($preview['missing_packages'] as $package => $constraint) {
            $this->line('missing package: '.$package.' '.$constraint);
        }
        foreach ($preview['host_steps'] as $step) {
            $this->line('host step: '.$step);
        }
        $claims = implode('', array_map(fn (string $path): string => ' --claim='.$path, $preview['claim']));
        $this->line($preview['executable'] ? 'Nothing written. Apply with --apply --expected-plan='.$preview['plan'].$claims : 'Nothing written. The plan is not executable.');
    }
}
