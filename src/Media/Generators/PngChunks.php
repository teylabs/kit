<?php

namespace Tey\Kit\Media\Generators;

/**
 * A PNG with its metadata text and colour-profile chunks removed (iCCP, iTXt, zTXt, tEXt): ancillary chunks a malformed copy of which makes libpng warn, and
 * Laravel's error handler turns that warning into an exception, so GD refuses an image whose pixels are fine. Nothing else changes. The file is parsed chunk by
 * chunk: a bad signature, a truncated chunk or a critical chunk whose CRC does not match returns null, so a genuinely corrupt PNG is left to fail loudly.
 */
final class PngChunks
{
    public const SIGNATURE = "\x89PNG\r\n\x1a\n";

    /** The chunks dropped: metadata only. GD's WebP output carries no ICC profile or text, so nothing reaches the conversion either way. */
    public const DROPPED = ['iCCP', 'iTXt', 'zTXt', 'tEXt'];

    /** The PNG without the dropped chunks, the same bytes when it had none, or null when it is not a well-formed PNG. */
    public static function strip(string $png): ?string
    {
        if (! str_starts_with($png, self::SIGNATURE)) {
            return null;
        }
        $out = self::SIGNATURE;
        $offset = strlen(self::SIGNATURE);
        $length = strlen($png);
        $ended = false;
        while ($offset < $length) {
            if ($offset + 12 > $length) {
                return null;
            }
            $size = unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            if ($size > $length - $offset - 12 || preg_match('/\A[A-Za-z]{4}\z/', $type) !== 1) {
                return null;
            }
            $data = substr($png, $offset + 8, $size);
            $crc = unpack('N', substr($png, $offset + 8 + $size, 4))[1];
            // A critical chunk (an upper-case first letter: IHDR, PLTE, IDAT, IEND) must be intact; a damaged ancillary one is dropped only if it is metadata.
            if (ctype_upper($type[0]) && crc32($type.$data) !== $crc) {
                return null;
            }
            if (! in_array($type, self::DROPPED, true)) {
                $out .= substr($png, $offset, 12 + $size);
            }
            $offset += 12 + $size;
            if ($type === 'IEND') {
                $ended = true;
                break;
            }
        }

        return $ended ? $out : null;
    }
}
