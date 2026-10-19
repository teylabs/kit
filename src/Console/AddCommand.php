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
    protected $signature = 'kit:add {features* : Features to adopt; their requirements are added too} {--apply : Write the reviewed plan} {--expected-plan= : The plan identity the preview printed} {--intent= : Names this adoption, for resume} {--resume : Finish a failed apply under the same intent} {--json : Print the result as JSON}';

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
            if ($this->option('resume')) {
                $intent = (string) $this->option('intent');
                if ($intent === '') {
                    throw new RuntimeException('Resume needs the --intent the apply printed.');
                }
                $result = $lineages->resume($root, $host->appId(), $features, $host->bindings(), $host->inputPaths(), $intent, $host->operationCheck());
            } elseif ($this->option('apply')) {
                $result = $lineages->add($root, $host->appId(), $features, $host->bindings(), $host->inputPaths(), (string) $this->option('expected-plan'), $this->option('intent'), $host->operationCheck());
            } else {
                $preview = $lineages->preview($root, $host->appId(), $features, $host->bindings(), $host->inputPaths());
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
                if ($result['failed'] !== null) {
                    $this->error('Stopped at '.$result['failed'].'. Fix it, then run kit:add '.implode(' ', $features).' --resume --intent='.$result['intent'].'.');
                }
            }

            return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function describe(array $preview): void
    {
        $this->line('plan: '.$preview['plan']);
        foreach ($preview['features'] as $row) {
            $this->line($row['feature'].': '.$row['action'].($row['executable'] ? '' : ' (not executable)'));
            foreach ($row['files'] ?? [] as $path => $file) {
                $this->line('  '.$file['state'].' '.$path.(isset($file['reason']) ? ' ('.$file['reason'].')' : ''));
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
        $this->line($preview['executable'] ? 'Nothing written. Apply with --apply --expected-plan='.$preview['plan'] : 'Nothing written. The plan is not executable.');
    }
}
