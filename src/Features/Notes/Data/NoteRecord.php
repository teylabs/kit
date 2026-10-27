<?php

namespace Tey\Kit\Features\Notes\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One note as both transports see it. `subject` is the registered record it is about (label and url from its resolver), or null. */
#[TypeScript]
final class NoteRecord extends Data
{
    /**
     * @param  array{type: string, id: int, label: string, url: string|null}|null  $subject
     * @param  list<array{id: string, name: string, mime: string, size: int, url: string, thumb_url: string|null}>  $attachments
     */
    public function __construct(public int $id, public int $workspace_id, public ?int $author_id, public ?string $author, public string $body, public string $colour, public bool $pinned, public int $version, public ?array $subject, public array $attachments, public string $created_at, public string $updated_at) {}
}
