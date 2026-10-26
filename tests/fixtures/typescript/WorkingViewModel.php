<?php

namespace Tey\Kit\Tests\Fixtures\TypeScript;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Explanation\ExplanationDocument;
use Tey\Kit\Explanation\ExplanationNode;
use Tey\Kit\Features\Core\ViewModels\ViewModel;

/** A page view model that carries an explanation's working, for the TypeScript mapping check. */
#[TypeScript]
final class WorkingViewModel extends ViewModel
{
    /** @param list<ExplanationNode> $working */
    public function __construct(private array $working, private ExplanationDocument $document) {}

    /** @return list<ExplanationNode> */
    public function working(): array
    {
        return $this->working;
    }

    public function document(): ExplanationDocument
    {
        return $this->document;
    }
}
