<?php

namespace Tey\Kit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Tey\Kit\BrandAssets;

/** Regenerates the brand's derived assets in public/ from the logomark SVG and the accent. Safe to run again: it only rewrites what changed. */
final class BrandCommand extends Command
{
    protected $signature = 'kit:brand {--check : Report what would change and exit 1 if anything is out of date, writing nothing}';

    protected $description = 'Generate the favicons, touch icon, mail mark and social image from the brand logomark and accent';

    public function handle(): int
    {
        try {
            $assets = BrandAssets::fromConfig();
            if ($this->option('check')) {
                $stale = [];
                foreach ($assets->render() as $file => $bytes) {
                    if (! is_file(public_path($file)) || hash('sha256', (string) file_get_contents(public_path($file))) !== hash('sha256', $bytes)) {
                        $stale[] = $file;
                    }
                }
                $this->line($stale === [] ? 'The brand assets are current.' : 'Out of date: '.implode(', ', $stale));

                return $stale === [] ? self::SUCCESS : self::FAILURE;
            }
            $result = $assets->write(public_path());
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        foreach ($result['written'] as $file) {
            $this->line('wrote public/'.$file);
        }
        foreach ($result['unchanged'] as $file) {
            $this->line('unchanged public/'.$file);
        }

        return self::SUCCESS;
    }
}
