<?php

namespace Tey\Kit\Features\Notes\Http;

use Illuminate\Http\Request;
use RuntimeException;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Users;
use Tey\Kit\Features\Notes\Operations\WorkspaceNotes;

/**
 * An attachment, or its `?conversion=thumb`: only to someone who may read the note, and never sniffed. The original is never cached; the thumb is
 * cached privately for a day with an ETag (media id, updated_at, conversion), and a matching If-None-Match is a 304, checked only once the reader
 * is authorised. Anything else, including another workspace's note, a deleted note or an unknown file, is a 404.
 */
class NoteMediaController
{
    public function show(Request $request, int $workspace, int $note, string $media): mixed
    {
        $user = $request->user();
        abort_unless(Users::is($user), 401);

        try {
            app(Access::class)->authorize(new Principal((int) $user->getKey()), 'notes.read');
            $record = app(WorkspaceNotes::class)->visible(new Principal((int) $user->getKey()), $note);
        } catch (RuntimeException) {
            abort(404);
        }
        $attachment = $record->getMedia('attachments')->firstWhere('uuid', $media) ?? abort(404);
        $conversion = $request->query('conversion');
        abort_unless($conversion === null || ($conversion === 'thumb' && $attachment->hasGeneratedConversion('thumb')), 404);
        if ($conversion !== null) {
            $etag = '"'.hash('sha256', $attachment->getKey().'|'.$attachment->updated_at?->getTimestamp().'|'.$conversion).'"';
            $headers = ['Cache-Control' => 'private, max-age=86400', 'ETag' => $etag, 'X-Content-Type-Options' => 'nosniff'];
            if (in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true)) {
                return response('', 304, $headers);
            }
        }
        // Images show in place; anything else (a PDF) downloads, so the browser never renders it under this origin.
        $response = $conversion !== null || str_starts_with((string) $attachment->mime_type, 'image/') ? $attachment->toInlineResponse($request, $conversion ?? '') : $attachment->toResponse($request);
        $response->headers->set('Cache-Control', $conversion !== null ? 'private, max-age=86400' : 'private, no-store');
        if ($conversion !== null) {
            $response->headers->set('ETag', $etag);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        $response->headers->remove('Pragma');

        return $response;
    }
}
