<?php

namespace Tey\Kit\Features\SessionUi\Http;

use Illuminate\Http\Request;
use Tey\Kit\Features\SessionUi\Avatars;

/**
 * `GET /avatars/{token}`: public and unauthenticated, protected only by the token being random. The Media Library item is streamed from its own disk
 * (the private media disk) with Media Library's response API; the response is cached for a year and carries an ETag, because a new avatar is a new token.
 */
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
}
