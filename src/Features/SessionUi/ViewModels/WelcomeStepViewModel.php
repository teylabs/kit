<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for the welcome steps (pages/Welcome/*): one question, what to do with the answer, and the way to skip it. */
#[TypeScript]
final class WelcomeStepViewModel extends PageViewModel
{
    /** @param  ?string  $value  the current answer (a timezone), when the step has one; the page falls back to the browser's
     * @param  ?Action  $skip  absent for a step that is not optional */
    public function __construct(private string $title, private string $heading, private string $description, private Action $submit, private ?Action $skip, private ?string $value, private string $initials, private int $max_kb, private ?Action $save = null) {}

    public function title(): string
    {
        return $this->title;
    }

    public function heading(): string
    {
        return $this->heading;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function submit(): Action
    {
        return $this->submit;
    }

    public function skip(): ?Action
    {
        return $this->skip;
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function initials(): string
    {
        return $this->initials;
    }

    public function max_kb(): int
    {
        return $this->max_kb;
    }

    /** Saves the answer as it changes (no Save button), for the steps that ask for one; null for the others. */
    public function save(): ?Action
    {
        return $this->save;
    }
}
