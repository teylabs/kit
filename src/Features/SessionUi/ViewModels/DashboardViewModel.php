<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for pages/Workspace/Dashboard/Page.vue, and for an app's own dashboard component: the workspace, the one obvious next step and the viewer's widgets.
 * Typed as WorkspaceDashboardViewModel, so it never collides with an app's own DashboardViewModel in one flat generated module.
 *
 * @purpose Members: see what needs them today and take the next step.
 */
#[TypeScript('WorkspaceDashboardViewModel')]
final class DashboardViewModel extends PageViewModel
{
    /** toArray() adds the list props; it is not a prop itself. */
    protected array $hidden = ['toArray'];

    /**
     * @param  string  $greeting  "Good morning, {first name}", in the person's timezone
     * @param  list<array<string, mixed>>  $widgets  the resolved widget frames (`Dashboard\Widgets`)
     * @param  array<string, mixed>  $lists  the widgets' deferred ScrollList props, by key: page props beside these, not a prop of their own
     */
    private array $lists = [];

    /**
     * The 0.15.41 argument order (…, $greeting, $widgets, $lists, $aside). The pre-0.15.41 `welcome` argument (WelcomeCardData) was removed in 0.17: passing a
     * card in the sixth place is a TypeError, and `welcome:` an unknown named argument.
     *
     * @param  array<string, mixed>  $lists
     */
    public function __construct(private string $title, private string $workspace, private string $next_label, private string $next_url, private string $greeting = '', private array $widgets = [], array $lists = [], private string $aside = 'narrow')
    {
        $this->lists = $lists;
    }

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

    /** @return 'narrow'|'wide' the aside column's width from md: 4 of 12 (narrow) or 5 (wide), from `kit.dashboard.aside` */
    public function aside(): string
    {
        return $this->aside === 'wide' ? 'wide' : 'narrow';
    }

    /** @return list<array{key: string, title: string, component: string, kind: 'task'|'feed'|'status'|'setup', region: 'main'|'aside', span: array{md: int, lg: int}, state: string, empty: array{title: string, description: string, action: array<string, mixed>|null}|null, error: array{message: string, retry: array<string, mixed>|null}|null, actions: list<array<string, mixed>>}> each visible widget's frame and its own props, in order */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::toArray(), ...$this->lists];
    }
}
