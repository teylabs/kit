<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Tey\Kit\Blueprint;
use Tey\Kit\CatalogHost;
use Tey\Kit\LifecycleHost;
use Throwable;

final class ListCommand extends Command
{
    protected $signature = 'kit:list {--json : Print the catalog as JSON}';

    protected $description = 'List the catalog features with what each requires and owns (read-only)';

    public function handle(): int
    {
        try {
            $catalog = null;
            if ($this->laravel->bound(LifecycleHost::class)) {
                $host = $this->laravel->make(LifecycleHost::class);
                $catalog = $host instanceof CatalogHost ? $host->catalog() : null;
            }
            $rows = Blueprint::entries($catalog);
            if ($this->option('json')) {
                $this->line(json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }
            foreach ($rows as $row) {
                $this->line($row['id']);
                $this->line('  requires: '.($row['requires'] === [] ? '-' : implode(', ', $row['requires'])));
                $this->line('  owns: '.($row['owns'] === [] ? '-' : implode(', ', $row['owns'])));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
