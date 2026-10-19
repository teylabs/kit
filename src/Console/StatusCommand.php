<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Tey\Kit\Lifecycle;
use Throwable;

final class StatusCommand extends Command
{
    protected $signature = 'kit:status {--json : Print the installed status as JSON}';

    protected $description = 'Show adopted features and local edits to owned files (read-only)';

    public function handle(): int
    {
        try {
            $status = (new Lifecycle)->status($this->laravel->basePath());
            if ($this->option('json')) {
                $this->line(json_encode($status ?? ['message' => 'No kit features adopted.', 'blueprint_features' => [], 'lineages' => []], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            } elseif ($status === null) {
                $this->line('No kit features adopted.');
            } else {
                $this->line('Adopted features: '.implode(', ', $status['blueprint_features']));
                foreach ($status['lineages'] as $lineage) {
                    $this->line('Lineage: '.$lineage['name'].' '.$lineage['version']);
                    $this->line('Identity: '.$lineage['identity']);
                    foreach ($lineage['files'] as $path => $state) {
                        $this->line($path.': '.$state.($state === 'edited' ? ' (conflict on next update)' : ''));
                    }
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
