<?php

namespace Tey\Kit;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use RuntimeException;
use Tey\Kit\Features\Core\KitShares;
use Tey\Kit\Features\Core\Toast;
use Tey\Mod\Facades\Mod;
use Tey\Mod\Generation\Stub;
use Tey\Mod\Placement\PlacementContext;
use Tey\Mod\Scaffolds\Scaffold;

final class KitServiceProvider extends ServiceProvider
{
    public const FOUNDATION_LAYOUT = 'The kit:foundation scaffold requires the laravel layout. Kit features (kit:add, kit:adopt, kit:update) work under any layout.';

    public function boot(): void
    {
        // The kit never depends on the host's tey/mod layout: kit:install, kit:add, kit:adopt and kit:update write kit files at their own
        // canonical paths, and the features boot the same under any layout. Only the v0.1 kit:foundation scaffold below places through the
        // host's layout, so it alone refuses another one, when it runs, and isn't registered at all under one unless the host configures it.
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
        $this->commands([Console\AddCommand::class, Console\AdoptCommand::class, Console\InstallCommand::class, Console\LifecycleCommand::class, Console\ListCommand::class, Console\StatusCommand::class, Console\BrandCommand::class]);
        // The v0.1 scaffold fixture; the starter features are adopted through kit.features instead.
        $features = config('kit.scaffold_features');
        // Under another layout the scaffold can't place its members, and Mod would warn on every boot that it's disabled. Unless the host asks for it
        // (`kit.scaffold_features`), it isn't registered: a stand-in `kit:foundation` refuses with the reason instead.
        if (config('mod.layout', 'laravel') !== 'laravel' && ! $features) {
            $this->commands([Console\FoundationCommand::class]);

            return;
        }
        $blueprint = $features ? Blueprint::features($features, $this->catalog()) : Blueprint::release(config('kit.release', 'A'));
        $members = [];
        foreach ($blueprint->manifest['generated'] as $path => $entry) {
            $member = match ($path) {
                'app/Support/Starter/BlueprintPolicy.php' => ['starter-policy', 'BlueprintPolicy', 'policy.php.stub'],
                'app/Support/Starter/Defaults/BlueprintDefaults.php' => ['starter-defaults', 'BlueprintDefaults', 'defaults.php.stub'],
                default => throw new RuntimeException('Unsupported blueprint generated member.'),
            };
            if ($entry['stub'] !== $member[2] || $entry['scaffold'] !== 'kit' || $entry['ownership'] !== 'generated') {
                throw new RuntimeException('Unsupported blueprint member identity.');
            }
            $members[$path] = $member;
            Mod::layout('laravel')->generates($member[0], in: substr(dirname($path), 4), stub: Stub::file($blueprint->stubPath($member[2])));
        }
        // Every member validates the complete ownership set before generation starts.
        $validate = function () use ($members, $blueprint): bool {
            if (config('mod.layout', 'laravel') !== 'laravel') {
                throw new RuntimeException(self::FOUNDATION_LAYOUT);
            }
            $blueprint->sourceReference();
            foreach ($members as $path => [$type, $name]) {
                $placed = Mod::current()->place($type, $name, PlacementContext::none());
                if ($placed->path() !== $path || $placed->fqcn() !== 'App\\'.str_replace('/', '\\', substr($path, 4, -4))) {
                    throw new RuntimeException('Blueprint requires the unmodified laravel layout.');
                }
            }

            return true;
        };
        Mod::vendor('tey')->scaffold('kit', function (Scaffold $recipe) use ($members, $validate): void {
            $recipe->named('BlueprintPolicy');
            foreach ($members as [$type, $name]) {
                $recipe->makes($type, name: $name, as: substr($type, 8), ungrouped: true, when: $validate);
            }
        }, command: 'kit:foundation');
    }

    /** The bound host's catalog when it has one, else the package catalog. */
    private function catalog(): ?Catalog
    {
        if (! $this->app->bound(LifecycleHost::class)) {
            return null;
        }
        $host = $this->app->make(LifecycleHost::class);

        return $host instanceof CatalogHost ? $host->catalog() : null;
    }
}
