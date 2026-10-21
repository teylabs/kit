<?php

namespace Tey\Kit;

/**
 * The Passport signing keys as a platform hands them over. A multi-line PEM does not survive every dashboard, so the kit accepts three forms and turns each into the real
 * PEM Passport needs, at boot: a PEM with real newlines (as is), one whose newlines are the two characters backslash and n, and the whole PEM base64-encoded. It only ever
 * reports which form it found, never the key.
 */
final class PassportKeys
{
    public const KEYS = ['passport.private_key' => 'PASSPORT_PRIVATE_KEY', 'passport.public_key' => 'PASSPORT_PUBLIC_KEY'];

    /** @var array<string, string> config key => the form found: pem, escaped-newlines, base64, missing or unrecognised */
    private static array $forms = [];

    /** @return array{0: ?string, 1: string} the PEM (null when the value is not one) and the form the value was in */
    public static function normalize(?string $value): array
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return [null, 'missing'];
        }
        if (str_contains($value, '-----BEGIN ') && str_contains($value, "\n")) {
            return [$value, 'pem'];
        }
        if (str_contains($value, '-----BEGIN ') && str_contains($value, '\\n')) {
            return [str_replace(['\\r\\n', '\\n'], "\n", $value), 'escaped-newlines'];
        }
        if (! str_contains($value, '-----BEGIN ')) {
            $decoded = base64_decode($value, true);
            if (is_string($decoded) && str_contains($decoded, '-----BEGIN ') && str_contains($decoded, "\n")) {
                return [trim($decoded), 'base64'];
            }
        }

        return [null, 'unrecognised'];
    }

    /** Normalizes both keys in the config repository, remembering the form each was in. A key that is not set is left for Passport to read from its files. */
    public static function apply(\Illuminate\Contracts\Config\Repository $config): void
    {
        foreach (array_keys(self::KEYS) as $key) {
            $raw = $config->get($key);
            if (! is_string($raw) || trim($raw) === '') {
                self::$forms[$key] = 'missing';

                continue;
            }
            [$pem, $form] = self::normalize($raw);
            self::$forms[$key] = $form;
            if ($pem !== null && $pem !== $raw) {
                $config->set($key, $pem);
            }
        }
    }

    /** @return array<string, string> the form each key was found in, by environment variable name */
    public static function forms(): array
    {
        $forms = [];
        foreach (self::KEYS as $key => $env) {
            $forms[$env] = self::$forms[$key] ?? 'missing';
        }

        return $forms;
    }
}
