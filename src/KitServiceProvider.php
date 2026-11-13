<?php

namespace Tey\Kit;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Image\ImageManager;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Tey\Kit\Features\Core\Kit;
use Tey\Kit\Features\Core\KitShares;
use Tey\Kit\Features\Core\Toast;

final class KitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Media is the kit's own, not a feature, so no feature provider registers its upload model: Kit::model('media_upload') resolves through here.
        Kit::defaults(['media_upload' => Media\Models\MediaUpload::class], []);
    }

    public function boot(): void
    {
        // The kit never depends on the host's tey/mod layout: the features boot the same under any layout, and nothing is scaffolded on boot.
        // The kit's UI copy (lang/en/ui.php) as `kit::ui`, shared with every page as `kitUi` for the Vue side; lazy, so only a rendered page reads it.
        // Lazy closures through KitShares: shared now and put back on every web request after a flush, never over a key the host shares.
        $this->loadTranslationsFrom(dirname(__DIR__).'/lang', 'kit');
        if (class_exists(Inertia::class)) {
            KitShares::provide([
                'kitUi' => fn (): array => (array) trans('kit::ui'),
                // Where the shell's Toaster sits (`kit.toasts.position`); an unknown value is `auto`.
                'kitToasts' => fn (): array => Toast::shared(),
                // Money and percent defaults for InputNumber and Amount: the configured currency (`kit.currency`, null when unset) and the app locale as a BCP 47 tag.
                'kitNumber' => fn (): array => ['currency' => config('kit.currency') ?: null, 'locale' => str_replace('_', '-', (string) app()->getLocale())],
            ]);
        }
        $this->registerImageDriver();
        $this->commands([Console\BrandCommand::class, Console\BrandMigrateCommand::class, Console\ScaffoldCommand::class, Media\Console\PruneUploadsCommand::class]);
        // Off by default: a host opts in with `kit.media.prune_schedule` (true for daily at 03:00, or a cron expression).
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => Media\Console\PruneUploadsCommand::schedule($schedule));
    }

    /**
     * The `kit-imagick` driver for Laravel's image API (docs/images.md), registered when the framework has the API and ext-imagick is loaded.
     * It becomes `images.default` while that is still the framework's `gd` and `kit.images.default` is on (the default).
     */
    private function registerImageDriver(): void
    {
        if (! class_exists(ImageManager::class) || ! extension_loaded('imagick')) {
            return;
        }
        $this->callAfterResolving('image', function (ImageManager $images): void {
            $images->extend(Media\Images\SizedImagickDriver::NAME, fn (): Media\Images\SizedImagickDriver => Media\Images\SizedImagickDriver::fromConfig());
        });
        if (config('kit.images.default', true) && config('images.default', 'gd') === 'gd') {
            config(['images.default' => Media\Images\SizedImagickDriver::NAME]);
        }
    }
}
