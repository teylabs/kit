<?php

namespace Tey\Kit\Features\Notes;

use Tey\Kit\Features\Workspaces\WorkspaceUrl;

/** The notes feature's workspace URLs, in the form the session uses. */
final class NoteUrls
{
    public static function index(int $workspace): string
    {
        return WorkspaceUrl::to($workspace, 'notes', 'notes');
    }

    public static function note(int $workspace, int $note): string
    {
        return WorkspaceUrl::to($workspace, 'notes/'.$note, 'notes');
    }

    public static function media(int $workspace, int $note, string $uuid, bool $thumb = false): string
    {
        return WorkspaceUrl::to($workspace, 'notes/'.$note.'/media/'.$uuid, 'notes').($thumb ? '?conversion=thumb' : '');
    }
}
