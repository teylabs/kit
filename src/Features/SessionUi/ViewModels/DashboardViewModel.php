<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\Data\WelcomeCardData;

/** Props for pages/Workspace/Dashboard/Page.vue, and for an app's own dashboard component: the workspace, the one obvious next step and the viewer's widgets.
 * Typed as WorkspaceDashboardViewModel, so it never collides with an app's own DashboardViewModel in one flat generated module. */
#[TypeScript('WorkspaceDashboardViewModel')]
final class DashboardViewModel extends PageViewModel
{
    /** toArray() adds the list props; it is not a prop itself. */
    protected array $hidden = ['toArray'];

    /**
     * @param  string  $greeting  "Good morning, {first name}", in the person's timezone
     * @param  list<array<string, mixed>>  $widgets  the resolved widget frames (`Dashboard\Widgets`)
     * @param  array<string, mixed>  $lists  the widgets' deferred ScrollList props, by key: page props beside these, not a prop of their own
     * @param  list<array{id: string, row: int, lg: int, sticky: bool}>  $regions  the dashboard regions that hold a widget, in layout order
     */
    public function __construct(private string $title, private string $workspace, private string $next_label, private string $next_url, private string $greeting = '', private ?WelcomeCardData $welcome = null, private array $widgets = [], private array $lists = [], private array $regions = [['id' => 'main', 'row' => 0, 'lg' => 12, 'sticky' => false]]) {}

    public function title(): string
    {
        return $this->title;
    }

    public function workspace(): string
    {
        return $this->workspace;
    }

    public function next_label(): string
    {
        return $this->next_label;
    }

    public function next_url(): string
    {
        return $this->next_url;
    }

    public function greeting(): string
    {
        return $this->greeting;
    }

    public function welcome(): ?WelcomeCardData
    {
        return $this->welcome;
    }

    /** @return list<array{key: string, title: string, component: string, region: string, span: array{md: int, lg: int, rows: int}, chrome: array{header: bool, padding: string, surface: string, hideWhenEmpty: bool}, state: string, empty: array{title: string, description: string, action: array<string, mixed>|null}|null, error: array{message: string, retry: array<string, mixed>|null}|null, actions: list<array<string, mixed>>}> each visible widget's frame and its own props, in order */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /** @return list<array{id: string, row: int, lg: int, sticky: bool}> */
    public function regions(): array
    {
        return $this->regions;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::toArray(), ...$this->lists];
    }
}
