<?php

namespace Tey\Kit\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Tey\Kit\Media\Contracts\MediaUpload;
use Tey\Kit\Media\Operations\AttachUpload;

/**
 * The browser File's own name for a direct upload, kept on the Media item as the `client_file_name` custom property (and its stem as the default
 * `name`). Spatie's `file_name` stays the storage key's last part, since medialibrary finds the object by it. MediaDelivery names a download of the
 * original after it. The name is never trusted: only its last path segment is
 * kept, control and format characters are removed, its own extension is dropped for the one the server chose (the storage key's, from the type
 * check), and its length is capped. Anything left empty or invalid gives null, and AttachUpload keeps its generated name. The storage key, the MIME
 * type and the extension never come from it.
 *
 * `attach()` sets the name for one AttachUpload call, for that upload only, and clears it in `finally`, so it never outlives the call: not into a
 * later attach, a queued job or another request.
 */
final class ClientFileName
{
    /** The custom property that holds the sanitised client file name. */
    public const PROPERTY = 'client_file_name';

    /** The longest kept name, in bytes, before the server's extension. */
    public const MAX_BYTES = 200;

    /** @var array<string, string> upload id => sanitised file name, set only inside attach() */
    private static array $scoped = [];

    /** AttachUpload with the client's file name as the item's `file_name` (null or invalid: the generated name). */
    public static function attach(Model&MediaUpload $upload, HasMedia&Model $owner, string $collection, ?string $clientName, ?string $name = null): Media
    {
        $previous = self::$scoped;
        $fileName = self::sanitise($clientName, $upload->key);
        if ($fileName !== null) {
            self::$scoped[$upload->id] = $fileName;
        }
        try {
            return AttachUpload::run($upload, $owner, $collection, $name);
        } finally {
            self::$scoped = $previous;
        }
    }

    /** The name set for this upload by the attach() in progress; null outside one. */
    public static function current(Model&MediaUpload $upload): ?string
    {
        return self::$scoped[$upload->id] ?? null;
    }

    /**
     * A Content-Disposition for the item's original under its client file name: RFC 6266 (`filename*=UTF-8''…`) with an ASCII `filename` fallback.
     * Null when the item has no usable client name, so the caller keeps its own header. The stored value is sanitised again before use.
     */
    public static function disposition(Media $media, string $type): ?string
    {
        $stored = $media->getCustomProperty(self::PROPERTY);
        $name = is_string($stored) ? self::sanitise($stored, $media->file_name) : null;
        if ($name === null) {
            return null;
        }
        $fallback = trim((string) preg_replace('#[^\x20-\x7E]|["%/\\\\]#', '_', Str::ascii($name)));
        $fallback = $fallback === '' || trim($fallback, '_. ') === '' ? $media->file_name : $fallback;

        return HeaderUtils::makeDisposition($type, $name, $fallback);
    }

    /** A safe display name from the client's: the stem kept, with the storage key's extension; null when nothing usable is left. */
    public static function sanitise(?string $clientName, string $key): ?string
    {
        if ($clientName === null || ! mb_check_encoding($clientName, 'UTF-8')) {
            return null;
        }
        $segments = preg_split('#[/\\\\]#', $clientName) ?: [];
        $base = (string) end($segments);
        $base = (string) preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', '', $base);
        $stem = trim(pathinfo($base, PATHINFO_FILENAME), " \t.");
        $stem = trim(mb_strcut($stem, 0, self::MAX_BYTES, 'UTF-8'), " \t.");
        if ($stem === '') {
            return null;
        }
        $extension = pathinfo($key, PATHINFO_EXTENSION);

        return $extension === '' ? $stem : $stem.'.'.$extension;
    }
}
