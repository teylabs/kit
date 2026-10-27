<?php

namespace Tey\Kit\Features\Notes\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/**
 * The quick-add composer: where it posts, the colours (the first is the default), what may be attached, the note's subject when the composer is on a
 * record, and what the app's `Registry::noteComposer()` hooks add (extra `options` and a `subject_search` URL for a "link to record" picker).
 */
#[TypeScript]
final class NoteComposerData extends Data
{
    /**
     * @param  list<array{key: string, label: string}>  $colours
     * @param  array{accept: list<string>, max_files: int, max_kb: int}  $media
     * @param  array{type: string, id: int}|null  $subject
     * @param  list<array{key: string, label: string, icon: string|null, url: string|null}>  $options
     */
    public function __construct(public Action $submit, public string $placeholder, public array $colours, public string $default_colour, public array $media, public ?array $subject = null, public array $options = [], public ?string $subject_search = null) {}
}
