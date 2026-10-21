<?php

namespace Tey\Kit\Features\SessionUi\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/** The dashboard's welcome card: a greeting for a new member and the first-run checklist. `dismiss` is present only once every item is done or skipped. */
#[TypeScript]
final class WelcomeCardData extends Data
{
    /** @param  list<array{id: string, label: string, hint: string, url: string, state: string}>  $checklist  state: done, skipped or pending */
    public function __construct(public string $title, public string $body, public ?Action $dismiss, public array $checklist, public int $done = 0, public int $total = 0) {}
}
