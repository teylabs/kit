<?php

namespace Tey\Kit\Features\Shell;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A legal or help document as a public page: markdown rendered on the server and sanitized there (raw HTML in the source is stripped and `javascript:` and other
 * unsafe links are refused), so the page only ever receives HTML the kit produced. The page is `Public/Markdown/Page`, with its own meta; the host's layout callback
 * puts `Public/` pages in the PublicShell.
 *
 *     Route::get('/legal/terms', fn () => PublicMarkdown::page('Terms of service', file_get_contents(resource_path('legal/terms.md'))));
 */
final class PublicMarkdown
{
    public static function html(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public static function page(string $title, string $markdown, ?string $description = null, ?string $updated = null): Response
    {
        return Inertia::render('Public/Markdown/Page', ['title' => $title, 'description' => $description, 'updated' => $updated, 'html' => self::html($markdown)]);
    }
}
