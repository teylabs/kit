<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\SessionUi\Data\WelcomeCardData;

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
    private ?WelcomeCardData $welcome = null;

    private array $widgets = [];

    private array $lists = [];

    private string $aside = 'narrow';

    /**
     * Takes both argument orders: up to 0.15.40's (…, $greeting, ?WelcomeCardData $welcome, $widgets, $lists) and 0.15.41's (…, $greeting, $widgets, $lists,
     * $aside). A list in the sixth place is 0.15.41's widgets. Named arguments (`welcome:`, `widgets:`, `lists:`, `aside:`) work either way.
     *
     * @param  WelcomeCardData|list<array<string, mixed>>|null  $welcome  deprecated: the old standalone welcome card, still rendered while an app sends it
     * @param  array<string, mixed>|string  $lists
     */
    public function __construct(private string $title, private string $workspace, private string $next_label, private string $next_url, private string $greeting = '', WelcomeCardData|array|null $welcome = null, array $widgets = [], array|string $lists = [], string $aside = 'narrow')
    {
        if (is_array($welcome)) {
            [$welcome, $aside, $lists, $widgets] = [null, is_string($lists) ? $lists : $aside, $widgets, $welcome];
        }
        $this->welcome = $welcome;
        $this->widgets = $widgets;
        $this->lists = is_array($lists) ? $lists : [];
        $this->aside = is_string($lists) ? $lists : $aside;
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

    /** @deprecated since 0.15.43, removed in 0.16: an app's own welcome card, rendered above the widgets as before 0.15.41. The kit sends none; its steps are in You and the setup card. */
    public function welcome(): ?WelcomeCardData
    {
        return $this->welcome;
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
