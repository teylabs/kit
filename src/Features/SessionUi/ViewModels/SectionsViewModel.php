<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Sections/Index/Page.vue: a settings landing page's sections, grouped. */
#[TypeScript]
final class SectionsViewModel extends Data
{
    /** @param list<array{label: string, sections: list<array{key: string, label: string, description: string, icon: string, url: string}>}> $groups */
    public function __construct(public string $title, public array $groups) {}

    /**
     * The landing page for a list of visible sections: the Inertia page, or their JSON for an API request.
     *
     * @param  list<array{key: string, label: string, description: string, group: string, icon: string, url: string}>  $sections
     */
    public static function page(\Illuminate\Http\Request $request, string $title, array $sections): mixed
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['sections' => array_map(fn (array $section): array => ['key' => $section['key'], 'label' => $section['label'], 'description' => $section['description'], 'group' => $section['group'], 'icon' => $section['icon'], 'url' => $section['url']], $sections)]);
        }

        return \Inertia\Inertia::render('Workspace/Sections/Index/Page', new self($title, \Tey\Kit\Features\SessionUi\SettingsSections::grouped($sections)));
    }
}
