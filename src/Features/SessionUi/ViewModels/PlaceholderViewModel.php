<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\PageWidth;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;

/** Props for a page that is not built yet (pages/Workspace/Placeholder, and the same inside the settings layout): the real page's title, what it will do, and where to go meanwhile. */
#[TypeScript]
final class PlaceholderViewModel extends PageViewModel
{
    /**
     * @param  ?string  $milestone  words only ("the Orders release"): the client says "Planned for {milestone}"; no version and no date is ever derived
     * @param  list<\Tey\Kit\Features\Core\Data\Action>  $links  descriptors only, each one already authorized; a target the viewer may not open is left out
     * @param  ?PageWidth  $width  the real page's width; null inside the settings layout, which supplies its own
     */
    public function __construct(private string $title, private string $description, private ?string $milestone, private array $links, private ?PageWidth $width, private bool $settings = false) {}

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function milestone(): ?string
    {
        return $this->milestone;
    }

    /** @return list<\Tey\Kit\Features\Core\Data\Action> */
    public function links(): array
    {
        return $this->links;
    }

    /** @return ?string a PageWidth value: xs, sm, md, lg, xl or full */
    public function width(): ?string
    {
        return $this->width?->value;
    }

    protected function section(): ?string
    {
        return $this->settings ? 'workspace' : null;
    }
}
