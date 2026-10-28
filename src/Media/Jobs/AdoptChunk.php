<?php

namespace Tey\Kit\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Tey\Kit\Media\Console\AdoptMediaCommand;

/** One chunk of an adopt run: the owners by id, adopted through the app's command. Safe to retry. */
class AdoptChunk implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public $tries = 3;

    /**
     * @param  class-string<AdoptMediaCommand>  $command
     * @param  list<int|string>  $ids
     */
    public function __construct(public string $command, public array $ids) {}

    /** @return array{adopted: int, skipped: int, missing: int} */
    public function handle(): array
    {
        return app($this->command)->adoptChunk($this->ids);
    }
}
