<?php

// An app extends the General settings page's ViewModel (#679): a registered subclass is what the page gets, its props join the kit's,
// a class that is not a subclass is refused, and null restores the kit's own. No HTTP, no database.

use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Tey\Kit\Features\Settings\Data\SettingsView;
use Tey\Kit\Features\Settings\ViewModels\EditSettingsViewModel;

require $argv[1];

function settingsCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

final class AppSettingsViewModel extends EditSettingsViewModel
{
    /** @return 'weekly'|'daily' */
    public function digest(): string
    {
        return 'weekly';
    }
}

try {
    $view = new SettingsView(7, 'Acme', 'UTC', [], 1);
    $save = new Action('Save', '/w/7/settings/general', 'put');

    $kit = EditSettingsViewModel::make('Workspace settings', $view, $save, 'intent-1');
    settingsCheck($kit::class === EditSettingsViewModel::class && ! in_array('digest', ViewModel::propMethods($kit::class), true), "by default the page gets the kit's own ViewModel");

    EditSettingsViewModel::extendWith(AppSettingsViewModel::class);
    $app = EditSettingsViewModel::make('Workspace settings', $view, $save, 'intent-1');
    settingsCheck($app instanceof AppSettingsViewModel && in_array('digest', ViewModel::propMethods($app::class), true) && $app->digest() === 'weekly', "a registered subclass is the page's ViewModel, with the app's prop");
    settingsCheck(array_diff(ViewModel::propMethods($kit::class), ViewModel::propMethods($app::class)) === [] && $app->title() === 'Workspace settings' && $app->settings() === $view && $app->save() === $save && $app->intent_key() === 'intent-1', 'and every kit prop, from the same arguments');
    settingsCheck(array_values(array_intersect(ViewModel::propMethods(AppSettingsViewModel::class), ['title', 'settings', 'save', 'intent_key', 'digest'])) === array_values(array_intersect(['title', 'settings', 'save', 'intent_key', 'digest'], ViewModel::propMethods(AppSettingsViewModel::class))) && count(array_intersect(ViewModel::propMethods(AppSettingsViewModel::class), ['title', 'settings', 'save', 'intent_key', 'digest'])) === 5, "the generated type's props are the kit's plus the app's (one type for the page)");

    $refused = false;
    try {
        EditSettingsViewModel::extendWith(stdClass::class);
    } catch (InvalidArgumentException) {
        $refused = true;
    }
    settingsCheck($refused && EditSettingsViewModel::make('t', $view, null, 'i') instanceof AppSettingsViewModel, 'a class that is not a subclass is refused, keeping the registered one');

    EditSettingsViewModel::extendWith(null);
    settingsCheck(EditSettingsViewModel::make('t', $view, null, 'i')::class === EditSettingsViewModel::class, "null restores the kit's own");
    echo "Settings ViewModel checks: plain objects only; no HTTP, page or database.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
