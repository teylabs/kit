<?php

namespace Tey\Kit;

use RuntimeException;

/**
 * A directory of feature manifests and stubs, with the identity each feature must have.
 *
 * The root holds `blueprint.json` and `<feature>/feature.json`. Pins map a feature id to the sha256
 * identity of its manifest and stubs, in canonical order; basePin is the identity of `blueprint.json`.
 * An external catalog is checked exactly as strictly as this package's own.
 */
final readonly class Catalog
{
    /** Selectable features in canonical order, with the pinned identity of each feature's manifest and stubs. */
    private const FEATURES = [
        'core' => 'sha256:20b806a11a12c7136d72c6eab5a54ac7da3f20472273e053779364ea7c2771a6',
        'workspaces' => 'sha256:8aa53c04bc1c0d5248c92dfbe1a825dc86fce15ed0c02b5ac4390e7f6a88717f',
        'access' => 'sha256:a179066fa9e5ca2279c2752ff67644f592ec4c5ee37e8cf263c205ddb7526d98',
        'settings' => 'sha256:d3afc44b0c804a826f345eead563d1174e1db1e68bafdfcc289aae30245aa193',
        'shell' => 'sha256:1b05c57aa866e9d6305f53d5628bb7fca788c85bf4ccf1270f74757fa16d9f97',
        'session-ui' => 'sha256:241edd9be8b7c617531d91319671378084e3d2c7c11091a5fb3141996fdbc934',
        'mcp' => 'sha256:f507a2bb5cf9ed585f4eab60b2d50a9939f7a1df986eaa65b13e0480b932117b',
    ];

    private const BASE = 'sha256:9e6ee91d863b69b35bacfbc7f1ee8934a8763ac95b1a31407e3cd95ed6cc75cf';

    /** @param array<string, string> $pins */
    private function __construct(public string $root, public array $pins, public ?string $basePin) {}

    /** @param array<string, string> $pins feature id => sha256 identity */
    public static function at(string $root, array $pins, string $basePin): self
    {
        $resolved = is_link($root) ? false : realpath($root);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new RuntimeException('Missing or linked catalog root.');
        }
        if ($pins === []) {
            throw new RuntimeException('A catalog pins at least one feature.');
        }
        foreach ($pins as $id => $pin) {
            if (! is_string($id) || ! preg_match('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $id)) {
                throw new RuntimeException('Invalid catalog feature id.');
            }
            if (! is_string($pin) || ! preg_match('/\Asha256:[0-9a-f]{64}\z/', $pin)) {
                throw new RuntimeException('Invalid catalog pin for feature: '.$id);
            }
        }
        if (! preg_match('/\Asha256:[0-9a-f]{64}\z/', $basePin)) {
            throw new RuntimeException('Invalid catalog base pin.');
        }

        return new self($resolved, $pins, $basePin);
    }

    /**
     * This package's own catalog: the starter features (docs/catalog.md). The v0.1 foundation and
     * defaults features are a test fixture (tests/fixtures/v01), not part of it.
     */
    public static function package(): self
    {
        return new self(dirname(__DIR__).'/resources/features', self::FEATURES, self::BASE);
    }

    /** @return list<string> Feature ids in canonical order. */
    public function featureIds(): array
    {
        return array_keys($this->pins);
    }

    public function directory(string $id): string
    {
        if (! isset($this->pins[$id])) {
            throw new RuntimeException('Unknown feature: '.$id);
        }

        return $this->root.'/'.$id;
    }
}
