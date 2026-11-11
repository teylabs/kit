<?php

namespace Tey\Kit\Features\Core;

/**
 * What one uploaded file can really be: the smallest of the kit's own cap for it, PHP's upload_max_filesize and post_max_size (a browser upload
 * over either never reaches the app), and medialibrary's max_file_size (a file over it is refused when it is kept). A cap reads through this, so
 * its validation and the hint on the page never promise more than the stack accepts.
 */
final class UploadLimit
{
    /** @var array<string, string>|null ini values a check reads instead of PHP's (fake()); null reads the real ini. */
    private static ?array $ini = null;

    /**
     * A seam for checks: read these ini values (upload_max_filesize, post_max_size) instead of PHP's, which can't be changed at runtime. A key left out
     * reads the real value. Null goes back to the real ini; call it in a `finally`.
     *
     * @param  array<string, string>|null  $ini
     */
    public static function fake(?array $ini): void
    {
        self::$ini = $ini;
    }

    /** The effective cap, in bytes, for a kit cap of `$bytes`. */
    public static function cap(int $bytes): int
    {
        return min(array_filter([$bytes, self::php(), self::media()], fn (?int $limit): bool => $limit !== null));
    }

    /** The same in kilobytes, for Laravel's `max:` file rule. */
    public static function capKb(int $kilobytes): int
    {
        return intdiv(self::cap($kilobytes * 1024), 1024);
    }

    /** PHP's own bound on one uploaded file: the lower of upload_max_filesize and post_max_size; null when neither is set. */
    public static function php(): ?int
    {
        $limits = array_filter([self::bytes(self::ini('upload_max_filesize')), self::bytes(self::ini('post_max_size'))], fn (?int $limit): bool => $limit !== null);

        return $limits === [] ? null : min($limits);
    }

    /** medialibrary's max_file_size, when the package is configured. */
    public static function media(): ?int
    {
        $size = config('media-library.max_file_size');

        return is_numeric($size) && (int) $size > 0 ? (int) $size : null;
    }

    /**
     * Each named kit cap the stack would cut down, for the preflight: "notes attachments: 10.0 MB, but PHP's post_max_size allows 8.0 MB".
     *
     * @param  array<string, int>  $caps  name => bytes
     * @return list<string>
     */
    public static function shortfalls(array $caps): array
    {
        $found = [];
        foreach ($caps as $name => $bytes) {
            foreach (['PHP\'s upload_max_filesize' => self::bytes(self::ini('upload_max_filesize')), 'PHP\'s post_max_size' => self::bytes(self::ini('post_max_size')), 'medialibrary\'s max_file_size' => self::media()] as $limit => $allows) {
                if ($allows !== null && $allows < $bytes) {
                    $found[] = sprintf('%s: %s, but %s allows %s', $name, self::megabytes($bytes), $limit, self::megabytes($allows));
                }
            }
        }

        return $found;
    }

    /**
     * The size-limit sentence for a cap of `$kilobytes`, in binary units: "Files can be up to 10 MiB" (10240 KB), "… 1.5 MiB" (1536 KB). The size is
     * MiB (1024² bytes) rounded down to one decimal place, whole numbers without one. The copy is `kit::ui.upload.limit` (`:size`), so an app words it in
     * lang/vendor/kit/{locale}/ui.php; with no translator, or the key missing, the kit's own English.
     */
    public static function message(int $kilobytes): string
    {
        $size = self::mebibytes(max(0, $kilobytes) * 1024);
        // A translator with no locale (a bare container) can't look anything up; the kit's English is the answer there.
        $copy = function_exists('app') && app()->bound('translator') && is_string(config('app.locale')) && config('app.locale') !== '' ? trans('kit::ui.upload.limit', ['size' => $size]) : null;

        return is_string($copy) && $copy !== 'kit::ui.upload.limit' ? $copy : 'Files can be up to '.$size.' MiB';
    }

    /** Bytes as MiB (1024²), rounded down to one decimal place so it never promises more, without a trailing ".0": 10, 1.5. */
    public static function mebibytes(int $bytes): string
    {
        $value = floor($bytes / 1048576 * 10 + 1e-9) / 10;

        return $value == floor($value) ? (string) (int) $value : number_format($value, 1, '.', '');
    }

    /** An ini size ("100M", "2G", "512K", "0") in bytes; null for none or unlimited. */
    public static function bytes(string $size): ?int
    {
        $size = trim($size);
        if ($size === '' || ! preg_match('/^(\d+)\s*([KMG]?)$/i', $size, $match) || (int) $match[1] === 0) {
            return null;
        }

        return (int) $match[1] * match (strtoupper($match[2])) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };
    }

    private static function ini(string $key): string
    {
        return self::$ini[$key] ?? (string) ini_get($key);
    }

    private static function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
