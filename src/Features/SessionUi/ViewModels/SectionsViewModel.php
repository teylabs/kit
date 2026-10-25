<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\SettingsSections;

/** Props for pages/Workspace/Sections/Index/Page.vue: a settings landing page's sections, grouped. */
#[TypeScript]
final class SectionsViewModel extends PageViewModel
{
    /** @param list<array{label: string, sections: list<array{key: string, label: string, description: string, icon: string, badge: ?string, url: string}>}> $groups */
    public function __construct(private string $title, private array $groups) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<array{label: string, sections: list<array{key: string, label: string, description: string, icon: string, badge: ?string, url: string}>}> */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * The landing page for a list of visible sections: the Inertia page, or their JSON for an API request.
     *
     * @param  list<array{key: string, label: string, description: string, group: string, icon: string, url: string}>  $sections
     */
    public static function page(Request $request, string $title, array $sections): mixed
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['sections' => array_map(fn (array $section): array => ['key' => $section['key'], 'label' => $section['label'], 'description' => $section['description'], 'group' => $section['group'], 'icon' => $section['icon'], 'url' => $section['url']], $sections)]);
        }

        return Inertia::render('Workspace/Sections/Index/Page', new self($title, SettingsSections::grouped($sections)));
    }
}
