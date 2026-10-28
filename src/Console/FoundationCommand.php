<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use Tey\Kit\KitServiceProvider;

/** Stands in for the v0.1 kit:foundation scaffold under a layout it can't place in, where the scaffold isn't registered. Writes nothing. */
final class FoundationCommand extends Command
{
    protected $signature = 'kit:foundation';

    protected $description = 'The v0.1 foundation scaffold (requires the laravel layout)';

    public function __construct()
    {
        parent::__construct();
        // Whatever the scaffold would have taken (a name, --dry-run, --json), the answer is the same refusal.
        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        $this->error(KitServiceProvider::FOUNDATION_LAYOUT);

        return self::FAILURE;
    }
}
