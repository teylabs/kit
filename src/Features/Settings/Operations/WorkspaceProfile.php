<?php

namespace Tey\Kit\Features\Settings\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Audit;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Tey\Kit\Features\Settings\Data\SettingsView;
use Tey\Kit\Features\Settings\WorkspaceSettings;
use Tey\Kit\Features\Workspaces\Workspaces;
use Tey\Kit\Features\Workspaces\WorkspaceUrl;

class WorkspaceProfile
{
    use AsAction;

    // The name has one authoritative value, the workspace's own, which the shell and its switcher show; the `display_name` setting is only a compatibility mirror.
    // The time format is the app's own setting, read and written through Registry::workspaceClockUsing: null when the app keeps none, `auto` when it has no choice.
    public function read(Principal $principal): SettingsView
    {
        $workspace = app(Access::class)->authorize($principal, 'settings.read')->fresh();
        $settings = WorkspaceSettings::current();
        $clock = app(Registry::class)->workspaceClock();
        $clock = $clock === null ? null : (in_array($value = ($clock['get'])(), ['12h', '24h'], true) ? $value : 'auto');

        return new SettingsView($workspace->id, (string) $workspace->name, $settings->timezone, [['label' => 'Workspace settings', 'href' => WorkspaceUrl::to($workspace, 'settings/general', 'settings')]], (int) $workspace->settings_version, $clock);
    }

    public function update(Principal $principal, array $input): SettingsView
    {
        if (array_diff(array_keys($input), ['display_name', 'timezone', 'clock', 'expected_version', 'intent_key']) !== []) {
            throw new \RuntimeException('unknown_arguments');
        }
        $data = Validator::make($input, ['display_name' => ['required', 'string', 'max:120'], 'timezone' => ['required', 'timezone'], 'clock' => ['sometimes', Rule::in(['auto', '12h', '24h'])], 'expected_version' => ['required', 'integer', 'min:1'], 'intent_key' => ['required', 'string', 'max:120']], ['clock.*' => 'Choose a time format.'])->validate();
        // A time format is saved through the app's setter; an app that keeps none refuses one rather than drop it.
        $setClock = app(Registry::class)->workspaceClock()['set'] ?? null;
        if (array_key_exists('clock', $data) && $setClock === null) {
            throw ValidationException::withMessages(['clock' => 'This workspace has no time format setting.']);
        }
        // Without a time format the fingerprint is the one it always was, so an intent recorded before is still recognised.
        $fingerprint = hash('sha256', json_encode([$data['display_name'], $data['timezone'], (int) $data['expected_version'], ...(array_key_exists('clock', $data) ? [$data['clock']] : [])], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($principal, $data, $fingerprint, $setClock): SettingsView {
                $workspace = app(Access::class)->authorize($principal, 'settings.write');
                $locked = Workspaces::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                $previous = DB::table('settings_intents')->where('workspace_id', $workspace->id)->where('actor_id', $principal->actorId)->where('intent_key', $data['intent_key'])->first();
                if ($previous) {
                    if ($previous->fingerprint !== $fingerprint) {
                        throw new \RuntimeException('intent_conflict');
                    }

                    return SettingsView::from(json_decode($previous->result, true, flags: JSON_THROW_ON_ERROR));
                }
                if ((int) $locked->settings_version !== (int) $data['expected_version']) {
                    throw new \RuntimeException('stale_settings');
                }
                $settings = WorkspaceSettings::current();
                $settings->display_name = $data['display_name'];
                $settings->timezone = $data['timezone'];
                $settings->save();
                if (array_key_exists('clock', $data)) {
                    $setClock($principal->actorId, $data['clock'] === 'auto' ? null : $data['clock']);
                }
                $locked->name = $data['display_name'];
                $locked->settings_version++;
                $locked->save();
                app(Audit::class)->write($principal->actorId, $workspace->id, 'workspace.settings.updated', ['fields' => array_key_exists('clock', $data) ? ['display_name', 'timezone', 'clock'] : ['display_name', 'timezone']]);
                $result = $this->read($principal);
                DB::table('settings_intents')->insert(['workspace_id' => $workspace->id, 'actor_id' => $principal->actorId, 'intent_key' => $data['intent_key'], 'fingerprint' => $fingerprint, 'result' => $result->toJson()]);

                return $result;
            });
        } catch (\Throwable $exception) {
            // Settings publishes cache values before the enclosing transaction commits.
            // Refresh from the rolled-back DB and republish using the package API.
            $settings = new WorkspaceSettings;
            $settings->refresh();
            app(SettingsCacheFactory::class)->build()->put($settings);
            throw $exception;
        }
    }
}
