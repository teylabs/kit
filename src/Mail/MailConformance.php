<?php

namespace Tey\Kit\Mail;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The mail rules as a scanner, so the kit's check and a host's architecture test share one definition: all mail is a Notification
 * composed with FluentMailMessage, never a Mailable, the Mail facade or a stock MailMessage. Each method returns the offending
 * `path reason` lines relative to the scanned directory; an empty list conforms.
 */
final class MailConformance
{
    /** @return list<string> */
    public static function notificationsOnly(string $directory): array
    {
        $offenders = [];
        foreach (self::files($directory) as $path => $source) {
            if (preg_match('/\bextends\s+\\\\?Illuminate\\\\Mail\\\\Mailable\b/', $source) || (str_contains($source, 'use Illuminate\\Mail\\Mailable;') && preg_match('/\bextends\s+Mailable\b/', $source))) {
                $offenders[] = $path.' extends Mailable';
            }
            if (preg_match('/\bMail::(?:to|send|raw|queue|later|cc|bcc|html|plain)\s*\(/', $source)) {
                $offenders[] = $path.' uses the Mail facade';
            }
        }

        return $offenders;
    }

    /** @return list<string> */
    public static function fluentComposer(string $directory): array
    {
        $offenders = [];
        foreach (self::files($directory) as $path => $source) {
            if (str_contains($source, 'function toMail') && (! str_contains($source, 'FluentMailMessage') || preg_match('/new\s+MailMessage\b/', $source))) {
                $offenders[] = $path.' composes mail without FluentMailMessage';
            }
        }

        return $offenders;
    }

    /** @return list<string> */
    public static function offenders(string $directory): array
    {
        return [...self::notificationsOnly($directory), ...self::fluentComposer($directory)];
    }

    /** @return iterable<string, string> relative path => source */
    private static function files(string $directory): iterable
    {
        $root = rtrim($directory, '/');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                yield substr($file->getPathname(), strlen($root) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }
}
