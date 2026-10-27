<?php

namespace Tey\Kit\Features\Notes\ViewModels;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\ViewModels\PageViewModel;
use Tey\Kit\Features\Notes\Data\NoteComposerData;
use Tey\Kit\Features\Notes\Lists\NotesList;

/** Props for pages/Workspace/Notes/Index/Page.vue: the composer (absent for someone who may not write), the colour filter and the wall (a ScrollList). */
#[TypeScript]
final class NotesIndexViewModel extends PageViewModel
{
    /** toArray() adds the wall's list prop under its key; it is not a prop itself. */
    protected array $hidden = ['toArray'];

    /** @param  list<array{key: string, label: string}>  $colours */
    public function __construct(private string $title, private ?NoteComposerData $composer, private array $colours, private ?string $colour, private string $index_url, private NotesList $list) {}

    public function title(): string
    {
        return $this->title;
    }

    public function composer(): ?NoteComposerData
    {
        return $this->composer;
    }

    /** @return list<array{key: string, label: string}> */
    public function colours(): array
    {
        return $this->colours;
    }

    /** The colour the wall is filtered to, or null for all. */
    public function colour(): ?string
    {
        return $this->colour;
    }

    public function index_url(): string
    {
        return $this->index_url;
    }

    /** The wall's ScrollList key. */
    public function list(): string
    {
        return $this->list->key();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::toArray(), $this->list->key() => $this->list->items()];
    }
}
