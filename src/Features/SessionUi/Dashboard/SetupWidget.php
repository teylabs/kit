<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\ChecklistItem;
use Tey\Kit\Features\SessionUi\Welcome\Onboarding;

/**
 * "Set up this workspace": the workspace-level onboarding steps (`Onboarding::step(..., scope: 'workspace')`, such as the workspace timezone) as a checklist,
 * last in the aside. Registered behind the workspace settings ability, so only people who manage the workspace's settings see it; hidden once every step is done.
 */
#[TypeScript]
final class SetupWidget extends Widget
{
    /** @var list<ChecklistItem>|null */
    private ?array $items = null;

    public function title(): string
    {
        return 'Set up this workspace';
    }

    public function component(): string
    {
        return 'setup';
    }

    /** @return 'task'|'feed'|'status'|'setup' */
    public function kind(): string
    {
        return 'setup';
    }

    /** @return 'main'|'aside' */
    public function region(): string
    {
        return 'aside';
    }

    public function visible(): bool
    {
        return $this->checklist() !== [];
    }

    /** @return list<ChecklistItem> the workspace's steps and where each stands */
    public function checklist(): array
    {
        return $this->items ??= Onboarding::checklist($this->context->principal->actorId, $this->context->workspace, 'workspace');
    }
}
