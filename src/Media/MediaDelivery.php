<?php

namespace Tey\Kit\Media;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a Media item, or one of its generated conversions, behind the app's own route (an unguessable token or an authorized path), so the page
 * only ever holds that route and never a bucket key. The app decides what exists: `$resolve` returns the item, or null for a 404 under its own rules.
 *
 * `proxy` (the default) streams the bytes through PHP: a public item is cached for a year, immutable, with an ETag and a 304 for a match; a private
 * one is `private, no-store`. `redirect` answers a 302 to a short-lived signed bucket URL; the redirect itself is cached for at most half the
 * signature's TTL, so a cached redirect never outlives its signature. A disk that cannot sign URLs is proxied. Every response is `nosniff`; anything
 * that is not an image downloads, and under `proxy` carries a sandboxing CSP, so it never renders under the app's origin.
 */
final class MediaDelivery
{
    public const PROXY = 'proxy';

    public const REDIRECT = 'redirect';

    private const IMAGE_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif'];

    /** @param  Closure(): ?Media  $resolve */
    public static function respond(Request $request, Closure $resolve, string $conversion = '', bool $public = false, ?string $mode = null): Response
    {
        $media = $resolve() ?? abort(404);
        abort_unless($conversion === '' || $media->hasGeneratedConversion($conversion), 404);
        $mode ??= (string) config('kit.media.delivery', self::PROXY);
        $path = $media->getPathRelativeToRoot($conversion);
        $disk = Storage::disk($conversion === '' ? $media->disk : ($media->conversions_disk ?: $media->disk));
        $type = $conversion === '' ? (string) $media->mime_type : (self::IMAGE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream');
        $image = str_starts_with($type, 'image/');

        if ($mode === self::REDIRECT && $disk->providesTemporaryUrls()) {
            $ttl = max(2, (int) config('kit.media.redirect_ttl', 3600));
            $url = $disk->temporaryUrl($path, now()->addSeconds($ttl), array_filter([
                'ResponseContentType' => $type,
                'ResponseContentDisposition' => $image ? null : 'attachment; filename="'.self::filename($media).'"',
            ]));

            return response('', 302, [
                'Location' => $url,
                'Cache-Control' => ($public ? 'public' : 'private').', max-age='.intdiv($ttl, 2),
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        $etag = '"'.hash('sha256', $media->uuid.'|'.$conversion.'|'.($media->getCustomProperty('sha256') ?? $media->size)).'"';
        $headers = $public ? ['Cache-Control' => 'public, max-age=31536000, immutable', 'ETag' => $etag] : ['Cache-Control' => 'private, no-store'];
        if ($public && in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true)) {
            return response('', 304, $headers + ['X-Content-Type-Options' => 'nosniff']);
        }
        $response = $image ? $media->toInlineResponse($request, $conversion) : $media->toResponse($request, $conversion);
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
        $response->headers->set('Content-Type', $type);
        $response->headers->remove('Pragma');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $image) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        }

        return $response;
    }

    private static function filename(Media $media): string
    {
        return str_replace(['"', '\\'], "'", Str::ascii($media->getDownloadFilename()));
    }
}
