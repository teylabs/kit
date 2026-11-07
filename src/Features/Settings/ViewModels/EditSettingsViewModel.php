<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use InvalidArgumentException;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Settings\Data\SettingsView;

/**
 * Props for pages/Workspace/Settings/General/Page.vue.
 *
 * Extendable: an app that adds fields to General settings subclasses this with its own prop methods (`AppSettingsViewModel extends
 * EditSettingsViewModel`), registers it with `EditSettingsViewModel::extendWith(AppSettingsViewModel::class)` in a service provider, and types
 * its page `defineProps<AppSettingsViewModel>()`: the generated type is the kit's props plus the app's, one type for the page. The kit builds it
 * with the same constructor arguments; the subclass reads anything more from the container in its prop methods.
 */
#[TypeScript]
class EditSettingsViewModel extends PageViewModel
{
    /** @var class-string<self> */
    private static string $class = self::class;

    /** Build the page's ViewModel as $class (a subclass of this one), or the kit's own with null. Anything else is refused. */
    public static function extendWith(?string $class): void
    {
        if ($class !== null && ! is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException('The General settings ViewModel must extend '.self::class.'; got '.$class.'.');
        }
        self::$class = $class ?? self::class;
    }

    /** The registered ViewModel for the page, built from the kit's props. */
    public static function make(string $title, SettingsView $settings, ?Action $save, string $intent_key): self
    {
        return new (self::$class)($title, $settings, $save, $intent_key);
    }

    final public function __construct(private string $title, private SettingsView $settings, private ?Action $save, private string $intent_key) {}

    public function title(): string
    {
        return $this->title;
    }

    public function settings(): SettingsView
    {
        return $this->settings;
    }

    /** Save: absent when the viewer's role can only read the settings. */
    public function save(): ?Action
    {
        return $this->save;
    }

    public function intent_key(): string
    {
        return $this->intent_key;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }
}
