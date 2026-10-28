<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Core\Workbench\WorkbenchKey;
use Tey\Kit\Features\Core\Workbench\WorkbenchPreferences;

/**
 * Saves the signed-in person's layout for one workbench key: `{ layout, version }`. The layout is validated to its known shape (Reka splitter state per group, the
 * zoom, the phone tab) and size, and anything else is refused. A stale `version` is 409 `stale_write` with the stored layout and version, which the page adopts.
 */
class WorkbenchPreferenceController
{
    public const MAX_BYTES = 8192;

    public function update(Request $request, string $key): JsonResponse
    {
        $user = $request->user('web');
        abort_unless(Users::is($user), 401);
        abort_unless(WorkbenchKey::valid($key), 404);
        $validated = validator($request->all(), [
            'version' => ['required', 'integer', 'min:0'],
            'layout' => ['present', 'array:groups,zoom,tab'],
            'layout.groups' => ['sometimes', 'array', 'max:8'],
            'layout.groups.*' => ['string', 'max:2000'],
            'layout.zoom' => ['sometimes', 'array:fit,zoom'],
            'layout.zoom.fit' => ['present_with:layout.zoom', 'nullable', Rule::in(['width', 'page'])],
            'layout.zoom.zoom' => ['required_with:layout.zoom', 'numeric', 'between:0.25,4'],
            'layout.tab' => ['sometimes', Rule::in(['navigator', 'stage', 'inspector', 'footer'])],
        ])->validate();
        $layout = (array) $validated['layout'];
        foreach (array_keys((array) ($layout['groups'] ?? [])) as $group) {
            abort_unless(is_string($group) && preg_match('/^[a-z][a-z0-9-]{0,31}$/', $group) === 1, 422, 'Unknown layout group.');
        }
        abort_if(strlen((string) json_encode($layout)) > self::MAX_BYTES, 422, 'The layout is too large.');

        $result = app(WorkbenchPreferences::class)->save((int) $user->getKey(), $key, $layout, (int) $validated['version']);
        if (! $result['saved']) {
            return response()->json(['error' => 'stale_write', 'layout' => $result['layout'], 'version' => $result['version']], 409);
        }

        return response()->json(['layout' => $result['layout'], 'version' => $result['version']]);
    }
}
