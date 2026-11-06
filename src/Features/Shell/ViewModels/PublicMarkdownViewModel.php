<?php

namespace Tey\Kit\Features\Shell\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Tey\Kit\Features\Shell\PublicMarkdown;

/** Props for pages/Public/Markdown/Page.vue: a legal or help document rendered from markdown (PublicMarkdown::page). */
#[TypeScript]
final class PublicMarkdownViewModel extends ViewModel
{
    public function __construct(private string $title, private string $markdown, private ?string $description = null, private ?string $updated = null) {}

    public function title(): string
    {
        return $this->title;
    }

    /** The meta description, if any. */
    public function description(): ?string
    {
        return $this->description;
    }

    /** "Last updated", as the host formats it. */
    public function updated(): ?string
    {
        return $this->updated;
    }

    /** The body as HTML the server rendered and sanitized (raw HTML stripped, unsafe links refused). */
    public function html(): string
    {
        return PublicMarkdown::html($this->markdown);
    }
}
