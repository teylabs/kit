<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for the welcome steps (pages/Welcome/*): one question, what to do with the answer, and the way to skip it. */
#[TypeScript]
final class WelcomeStepViewModel extends PageViewModel
{
    /** @param  ?string  $value  the current answer (the timezone), when the step has one; the page may replace it with the browser's */
    public function __construct(private string $title, private string $heading, private string $description, private Action $submit, private Action $skip, private ?string $value, private string $initials, private int $max_kb) {}

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

    public function skip(): Action
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
}
