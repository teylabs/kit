<?php

namespace Tey\Kit\Features\Notes\Http;

use Illuminate\Http\Request;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;
use Tey\Kit\Features\Workspaces\TenantBoundary;

/**
 * An attachment, or its `?conversion=thumb`: only to someone who may read the note, and never cached or sniffed. Anything else, including another
 * workspace's note, a deleted note or an unknown file, is a 404.
 */
class NoteMediaController
{
    public function show(Request $request, int $workspace, int $note, string $media): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        return app(TenantBoundary::class)->run($workspace, function () use ($request, $user, $note, $media): mixed {
            try {
                app(Access::class)->authorize(new Principal((int) $user->getKey()), 'notes.read');
                $record = app(WorkspaceNotes::class)->visible(new Principal((int) $user->getKey()), $note);
            } catch (RuntimeException) {
                abort(404);
            }
            $attachment = $record->getMedia('attachments')->firstWhere('uuid', $media) ?? abort(404);
            $conversion = $request->query('conversion');
            abort_unless($conversion === null || ($conversion === 'thumb' && $attachment->hasGeneratedConversion('thumb')), 404);
            // Images show in place; anything else (a PDF) downloads, so the browser never renders it under this origin.
            $response = $conversion !== null || str_starts_with((string) $attachment->mime_type, 'image/') ? $attachment->toInlineResponse($request, $conversion ?? '') : $attachment->toResponse($request);
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
            $response->headers->remove('Pragma');

            return $response;
        });
    }
}
