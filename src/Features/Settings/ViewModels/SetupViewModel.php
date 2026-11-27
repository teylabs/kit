<?php

namespace Tey\Kit\Features\Settings\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Settings\Setup\SetupRow;

/**
 * Props for pages/Workspace/Settings/Setup/Page.vue.
 *
 * @purpose Owners, admins and operators: see what is set up, what is missing, and how to fix each.
 */
#[TypeScript]
final class SetupViewModel extends PageViewModel
{
    /** @param  list<SetupRow>  $rows */
    public function __construct(private string $title, private array $rows, private bool $appRows, private ?Action $testMail) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<SetupRow> grouped as registered */
    public function rows(): array
    {
        return $this->rows;
    }

    /** Whether the app's own rows are shown: only to an operator. */
    public function appRows(): bool
    {
        return $this->appRows;
    }

    /** Send a test mail to yourself. Absent when the app's rows are hidden. */
    public function testMail(): ?Action
    {
        return $this->testMail;
    }

    protected function section(): ?string
    {
        return 'workspace';
    }
}
