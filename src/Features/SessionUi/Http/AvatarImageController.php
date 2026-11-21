<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Throwable;
use Tey\Kit\Features\Core\Routing\PublicRoute;
use Tey\Kit\Features\SessionUi\Avatars;

/**
 * `GET /avatars/{token}`: public and unauthenticated, protected only by the token being random. The Media Library item is streamed from its own disk
 * (the private media disk) with Media Library's response API; the response is cached for a year and carries an ETag, because a new avatar is a new token.
 * A file gone from its disk (the original or the conversion asked for) is a 404, and the avatar the token resolved to is deleted, so the UI falls back to
 * initials. Only that item, found by the stored token, is touched: the request chooses nothing else, and a token that names nothing is a plain 404.
 */
#[PublicRoute('An avatar image addressed by an unguessable token', open: true)]
class AvatarImageController
{
    public function show(Request $request, string $token, ?string $size = null): mixed
    {
        $media = Avatars::find($token) ?? abort(404);
        $conversion = $size === '2x' ? 'thumb2x' : 'thumb';
        $etag = '"'.hash('sha256', $token.$conversion).'"';
        $headers = ['Cache-Control' => 'public, max-age=31536000, immutable', 'ETag' => $etag];
        if (in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true)) {
            return response('', 304, $headers);
        }
        try {
            $present = Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())
                && Storage::disk($media->conversions_disk ?: $media->disk)->exists($media->getPathRelativeToRoot($conversion));
        } catch (FilesystemException) {
            $present = false;
        }
        if (! $present) {
            self::forget($media);
            abort(404);
        }
        $response = $media->toInlineResponse($request, $conversion);
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
        // The conversion's own type (a thumbnail may be a different format from the original).
        $response->headers->set('Content-Type', ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($media->getPathRelativeToRoot($conversion), PATHINFO_EXTENSION))] ?? 'application/octet-stream');
        $response->headers->remove('Pragma');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** Deletes the dead avatar; when its files cannot be removed (they are gone or the disk refuses), the row still goes. */
    private static function forget(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): void
    {
        try {
            $media->delete();
        } catch (Throwable) {
            $media->deleteQuietly();
        }
    }
}
