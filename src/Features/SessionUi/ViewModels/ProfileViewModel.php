<?php

namespace Tey\Kit\Features\SessionUi\ViewModels;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Props for pages/Workspace/Profile/Edit/Page.vue: who you are and your photo. */
#[TypeScript]
final class ProfileViewModel extends Data
{
    /** @param ?array{url: string, url_2x: string} $avatar */
    public function __construct(public string $title, public string $name, public string $initials, public ?array $avatar, public string $store_url, public int $max_kb) {}
}
